import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/verification_brouillon.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../../domain/entities/verification_type_contenu.dart';
import '../../../../domain/repositories/verification_repository.dart';
import '../../../../domain/usecases/verify/valider_la_saisie.dart';
import '../../../shared/vf_app_bar.dart';
import '../../../shared/vf_bottom_sheet.dart';
import '../../../shared/vf_buttons.dart';
import '../../../shared/vf_card.dart';
import '../../../shared/vf_display.dart';
import '../../../shared/vf_inputs.dart';
import '../../../shared/vf_points_pill.dart';
import '../../../shared/vf_toast.dart';
import '../providers/verify_providers.dart';
import '../widgets/verify_form_widgets.dart';
import '../../../../core/session/solde_unites.dart';

/// Écran 09 · **Vérifier un contenu** — `#/verify`.
///
/// C'est l'écran où une unité se dépense, donc celui où la moindre approximation
/// coûte de l'argent à quelqu'un. Trois règles le gouvernent, et elles sont
/// toutes des règles d'honnêteté :
///
/// - **le coût n'est jamais annoncé par le client** : il n'est servi par aucun
///   endpoint (voir [EncartDeCout]) ;
/// - **format et poids sont validés avant l'envoi**, avec les plafonds du
///   contrat ;
/// - **hors ligne, la demande est mise en file et l'écran le dit** — sans
///   simuler d'attente.
class VerifyPage extends ConsumerStatefulWidget {
  const VerifyPage({super.key});

  @override
  ConsumerState<VerifyPage> createState() => _VerifyPageState();
}

class _VerifyPageState extends ConsumerState<VerifyPage> {
  final TextEditingController _saisie = TextEditingController();
  final TextEditingController _contexte = TextEditingController();
  String? _erreur;

  @override
  void initState() {
    super.initState();
    final VerificationBrouillon brouillon = ref.read(verifyFormProvider);
    _saisie.text = brouillon.saisie;
    _contexte.text = brouillon.contexte;
  }

  @override
  void dispose() {
    _saisie.dispose();
    _contexte.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final VerificationBrouillon brouillon = ref.watch(verifyFormProvider);
    final int? solde = ref.watch(soldeUnitesProvider);
    final bool envoiEnCours =
        ref.watch(soumissionProvider) is SoumissionEnCours;

    ref.listen<EtatDeSoumission>(soumissionProvider, _reagir);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: VfAppBar(
        title: 'Vérifier',
        leading: context.canPop()
            ? VfAppBarLeading.retour
            : VfAppBarLeading.aucun,
        pointsPill: VfPointsPill(
          solde: solde,
          onTap: () => context.push(VfRoutes.wallet),
        ),
        // La cloche de notifications N'EST PAS POSEE : aucun endpoint ne sert de
        // compteur, et « une cloche qui ouvre un ecran vide se lit comme un
        // defaut ». Voir DrapeauxM2.
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          const IndicateurDEtapes(),
          const SizedBox(height: VfSpacing.stack),
          VfCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                _Numerote('① Quel type de contenu ?'),
                const SizedBox(height: VfSpacing.x10),
                SelecteurDeType(
                  choisi: brouillon.type,
                  onChoisi: (TypeContenu type) {
                    ref.read(verifyFormProvider.notifier).choisirLeType(type);
                    _saisie.clear();
                    setState(() => _erreur = null);
                  },
                ),
                const SizedBox(height: VfSpacing.stack),
                _Numerote('② Donnez-nous le contenu'),
                const SizedBox(height: VfSpacing.x10),
                // L'AnimatedSwitcher du simulateur : la zone change selon le
                // type, sans que l'ecran ne se recompose entierement.
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 180),
                  child: KeyedSubtree(
                    key: ValueKey<TypeContenu>(brouillon.type),
                    child: _zoneDeSaisie(brouillon),
                  ),
                ),
                const SizedBox(height: VfSpacing.stack),
                _Numerote('③ Un contexte a ajouter ? (facultatif)'),
                const SizedBox(height: VfSpacing.x10),
                VfTextField(
                  label: 'Ou l avez-vous vu ? Depuis quand circule-t-il ?',
                  controller: _contexte,
                  maxLength: VerificationBrouillon.longueurContexteMax,
                  onChanged: ref.read(verifyFormProvider.notifier).contexte,
                ),
              ],
            ),
          ),
          const SizedBox(height: VfSpacing.stack),
          // L'encart de cout est annonce AVANT le bouton d'envoi dans l'ordre
          // de lecture : « personne ne doit decouvrir un debit apres l'avoir
          // declenche » (accessibilite de l'ecran 09).
          EncartDeCout(solde: solde),
          if (_erreur != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x10),
            Text(
              _erreur!,
              style: VfTextStyles.tSm.copyWith(color: VfColors.brandRed),
            ),
          ],
          const SizedBox(height: VfSpacing.stack),
          VfPrimaryButton(
            label: 'Lancer la vérification',
            icon: Icons.travel_explore_rounded,
            busy: envoiEnCours,
            onPressed: envoiEnCours ? null : _lancer,
          ),
          const SizedBox(height: VfSpacing.stack),
          VfCard(
            tight: true,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                const Text('🧰', style: TextStyle(fontSize: 18)),
                const SizedBox(width: VfSpacing.x9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Vérifier soi-meme ?',
                        style: VfTextStyles.tSm.copyWith(
                          color: VfColors.ink,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      Text(
                        'Le répertoire d outils OSINT vous forme a l autonomie.',
                        style: VfTextStyles.tXs,
                      ),
                    ],
                  ),
                ),
                VfGhostButton(
                  label: 'Voir',
                  expanded: false,
                  onPressed: () => context.push(VfRoutes.tools),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _zoneDeSaisie(VerificationBrouillon brouillon) {
    switch (brouillon.type) {
      case TypeContenu.texte:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            VfTextArea(
              label: 'Collez le message, la rumeur ou l affirmation...',
              value: brouillon.saisie,
              controller: _saisie,
              maxLength: VerificationBrouillon.longueurTexteAffichee,
              onChanged: ref.read(verifyFormProvider.notifier).saisir,
            ),
            const SizedBox(height: VfSpacing.x6),
            Text('Minimum 10 caractères.', style: VfTextStyles.tXs),
          ],
        );
      case TypeContenu.lien:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            VfTextField(
              label: 'https://exemple.com/article-suspect',
              controller: _saisie,
              keyboardType: TextInputType.url,
              onChanged: ref.read(verifyFormProvider.notifier).saisir,
            ),
            const SizedBox(height: VfSpacing.x6),
            Text(
              'Nous analysons la source, le domaine, la date et le contenu.',
              style: VfTextStyles.tXs,
            ),
          ],
        );
      case TypeContenu.image:
      case TypeContenu.video:
        return ZoneDeDepot(
          type: brouillon.type,
          fichier: brouillon.fichier,
          onChoisir: () => _choisirUnMedia(brouillon.type),
        );
      case TypeContenu.audio:
        // L'IMPORT AUDIO N'EST PAS DISPONIBLE dans cette version, et l'ecran le
        // dit plutot que d'ouvrir un selecteur qui n'existe pas. `image_picker`
        // ne sait ouvrir ni un fichier audio ni un enregistreur ; les deux
        // demandent une dependance que le socle ne declare pas, et « un besoin
        // de modification du socle est une demande, pas une modification ».
        // Voir docs/ARBITRAGES.md et le compte rendu de M2.
        return VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.mic_off_rounded,
          title: 'Import audio indisponible',
          message:
              'Cette version de l application ne sait pas encore ouvrir un '
              'fichier audio. Les autres types de contenu restent analysables.',
          action: VfGhostButton(
            label: 'Analyser un texte a la place',
            expanded: false,
            onPressed: () => ref
                .read(verifyFormProvider.notifier)
                .choisirLeType(TypeContenu.texte),
          ),
        );
    }
  }

  /// Ouvre la feuille « appareil photo / galerie ».
  Future<void> _choisirUnMedia(TypeContenu type) async {
    final ImageSource? source = await VfBottomSheet.show<ImageSource>(
      context: context,
      title: type == TypeContenu.video
          ? 'Ajouter une vidéo'
          : 'Ajouter une image',
      builder: (BuildContext sheet) => Padding(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.x16,
          0,
          VfSpacing.x16,
          VfSpacing.x16,
        ),
        child: VfMenuList(
          children: <Widget>[
            VfNavTile(
              label: 'Appareil photo',
              icon: Icons.photo_camera_outlined,
              onTap: () => Navigator.of(sheet).pop(ImageSource.camera),
            ),
            VfNavTile(
              label: 'Galerie',
              icon: Icons.photo_library_outlined,
              onTap: () => Navigator.of(sheet).pop(ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) {
      return;
    }

    final ImagePicker selecteur = ImagePicker();
    final XFile? choisi = type == TypeContenu.video
        ? await selecteur.pickVideo(source: source)
        : await selecteur.pickImage(
            source: source,
            // La compression avant envoi est un point de vigilance du
            // simulateur : sur un reseau mobile, une photo de 8 Mo envoyee
            // telle quelle coute cinq fois plus longtemps qu'une photo
            // reechantillonnee, pour une analyse identique.
            maxWidth: 2000,
            imageQuality: 85,
          );
    if (choisi == null || !mounted) {
      return;
    }

    final FichierChoisi fichier = await copierDansLeStockagePrive(
      cheminSource: choisi.path,
      nomAffiche: choisi.name,
    );

    // Format et poids sont valides AVANT l'envoi, avec le plafond en clair :
    // « envoyer 120 Mo pour se faire refuser apres trois minutes est le pire
    // scenario possible ».
    final ErreurDeSaisie? refus = ValiderLaSaisie.fichierChoisi(
      type.televersement!,
      fichier,
    );
    if (!mounted) {
      return;
    }
    if (refus != null) {
      setState(() => _erreur = refus.message);
      return;
    }
    setState(() => _erreur = null);
    ref.read(verifyFormProvider.notifier).fichier(fichier);
  }

  void _lancer() {
    final VerificationBrouillon brouillon = ref.read(verifyFormProvider);
    final ErreurDeSaisie? refus = ValiderLaSaisie.brouillon(brouillon);
    if (refus != null) {
      setState(() => _erreur = refus.message);
      return;
    }
    setState(() => _erreur = null);
    ref.read(soumissionProvider.notifier).lancer(brouillon);
  }

  /// Ce que l'écran fait de chaque réponse du serveur.
  void _reagir(EtatDeSoumission? avant, EtatDeSoumission apres) {
    switch (apres) {
      case SoumissionPartie(:final ResultatSoumission resultat):
        ref.read(soumissionProvider.notifier).reprendre();
        ref.read(verifyFormProvider.notifier).vider();
        _saisie.clear();
        _contexte.clear();
        _ouvrirLaSuite(resultat);

      case SoumissionEnEchec(:final Object echec):
        ref.read(soumissionProvider.notifier).reprendre();
        _direLEchec(echec);

      case SoumissionAuRepos() || SoumissionEnCours():
        break;
    }
  }

  void _ouvrirLaSuite(ResultatSoumission resultat) {
    switch (resultat) {
      case SoumissionEnAttente():
        // « Ne pas simuler une attente » : pas de chronologie d'analyse tant
        // que rien n'est parti. On reste sur l'ecran et on le dit.
        VfToast.show(
          context,
          message:
              'Sans connexion — votre demande partira des le retour du réseau.',
        );

      case SoumissionEnvoyee(:final SoumissionAboutie aboutie):
        final Verification v = aboutie.verification;

        if (v.estUneReprise) {
          // Arbitrage n. 25 : une reponse deja connue revient instantanement,
          // unites RENDUES. On explique pourquoi rien n'a ete paye — la
          // personne a vu un cout annonce.
          VfToast.show(
            context,
            message:
                'Cette question avait déjà ete vérifiée : aucune unite debitee.',
          );
          context.pushReplacement(VfRoutes.report(v.uuid));
          return;
        }

        VfToast.show(
          context,
          message: v.coutUnites == null
              ? 'Analyse lancee.'
              : '${v.coutUnites} unite(s) debitee(s) — analyse lancee.',
        );
        context.push(
          v.aUnRapport
              ? VfRoutes.report(v.uuid)
              : VfRoutes.verification(v.uuid),
        );
    }
  }

  void _direLEchec(Object echec) {
    switch (echec) {
      // 402 : « le seul code HTTP qui dise il manque de quoi payer ». On OUVRE
      // LA RECHARGE — l'ecran ne se contente pas de refuser, il emmene vers la
      // solution.
      case InsufficientBalanceFailure(:final String? serverMessage):
        VfToast.error(
          context,
          serverMessage ?? 'Unités insuffisantes : rechargez votre solde.',
        );
        context.push(VfRoutes.wallet);

      // 429 : on affiche le message du serveur ET le delai. AUCUN debit.
      case RateLimitedFailure(
        :final String? serverMessage,
        :final Duration? retryAfter,
      ):
        VfToast.error(
          context,
          <String>[
            serverMessage ?? 'Vous avez lance beaucoup d analyses.',
            if (retryAfter != null)
              'Reessayez dans ${retryAfter.inMinutes < 1 ? '${retryAfter.inSeconds} s' : '${retryAfter.inMinutes} min'}.',
          ].join(' '),
        );

      // 422 : le message porte sur un champ. Le serveur redige deja la phrase.
      case final ValidationFailure erreur:
        setState(
          () => _erreur =
              erreur.firstMessageFor('televersement') ??
              erreur.firstMessageFor('affirmation') ??
              erreur.serverMessage ??
              'Verifiez votre saisie.',
        );

      // 404 sur le televersement : il a expire (deux heures) ou n'existe pas.
      case NotFoundFailure():
        setState(
          () => _erreur =
              'Le fichier televersé n est plus disponible. '
              'Choisissez-le a nouveau.',
        );
        ref.read(verifyFormProvider.notifier).fichier(null);

      case Failure(:final String? serverMessage):
        VfToast.error(
          context,
          serverMessage ?? 'L envoi n a pas abouti. Reessayez.',
        );

      case _:
        VfToast.error(context, 'L envoi n a pas abouti. Reessayez.');
    }
  }
}

class _Numerote extends StatelessWidget {
  const _Numerote(this.texte);

  final String texte;

  @override
  Widget build(BuildContext context) => Text(
    texte,
    style: VfTextStyles.tSm.copyWith(
      color: VfColors.ink,
      fontWeight: FontWeight.w600,
    ),
  );
}
