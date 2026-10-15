import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../../domain/entities/verification_rapport.dart';
import '../../../../domain/entities/verification_statut.dart';
import '../../../../domain/usecases/verify/drapeaux_de_fonctionnalite.dart';
import '../../../../domain/usecases/verify/valider_la_saisie.dart';
import '../../../shared/vf_app_bar.dart';
import '../../../shared/vf_bottom_sheet.dart';
import '../../../shared/vf_buttons.dart';
import '../../../shared/vf_card.dart';
import '../../../shared/vf_display.dart';
import '../../../shared/vf_inputs.dart';
import '../../../shared/vf_toast.dart';
import '../../../../data/repositories/verify/verification_providers.dart';
import '../providers/verify_providers.dart';
import '../widgets/report_blocks.dart';
import '../widgets/share_actions.dart';

/// Écran 11 · **Rapport de vérification** — `#/report`.
///
/// # Les deux blocs du simulateur qui ne sont pas là, et pourquoi
///
/// - **« Moteurs d'analyse mobilisés »** : le contrat n'expose **jamais** le
///   détail des exécutions de moteurs — « publier quel moteur a répondu quoi
///   revient à publier notre grille d'achat » (§9, arbitrage n° 74). Le champ
///   `scores` existe mais porte une répartition **par verdict**, dont les clés
///   sont des valeurs techniques **sans libellé** : les afficher obligerait à
///   fabriquer un libellé, ce que le contrat interdit. `RapportResource` sert
///   bien `moteurs` depuis la Phase 11, mais c'est une décision éditoriale de
///   ne pas l'afficher — pas un oubli, voir `verification_rapport.dart` ;
/// - **« Ce rapport vous a-t-il été utile ? »** : `POST /verifications/{uuid}/
///   feedback` n'existe pas. Le bloc est derrière un drapeau et **masqué** :
///   « un remerciement pour un avis qui n'est stocké nulle part est un mensonge
///   poli, et c'est pire qu'une question non posée ».
///
/// **« Sources et preuves consultées »** n'est plus dans cette liste : la
/// Phase 11 a exposé `preuves` sur `RapportResource`, câblé le 9 octobre 2026
/// (`CarteSourcesEtPreuves`) — audit Flutter/Laravel du 8 octobre 2026.
///
/// Les retraits restants sont consignés dans `docs/ARBITRAGES.md`, avec ce
/// qu'il faudrait côté serveur pour les rétablir.
class ReportPage extends ConsumerWidget {
  const ReportPage({required this.uuid, super.key});

  final String uuid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Verification> etat = ref.watch(rapportProvider(uuid));

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: VfAppBar(
        title: 'Rapport',
        // Pas de pastille de points : « montrer un solde pendant une lecture
        // n'apporte rien et ajoute une cible tactile parasite ».
        actions: <Widget>[
          if (etat.hasValue)
            VfIconButton(
              icon: Icons.ios_share_rounded,
              tooltip: 'Partager ce rapport',
              onPressed: () => _ouvrirLePartage(context, etat.value!),
            ),
        ],
      ),
      body: etat.when(
        loading: () => const _Squelette(),
        error: (Object echec, StackTrace _) => _repli(context, echec),
        data: (Verification v) => _corps(context, ref, v),
      ),
    );
  }

  Widget _corps(BuildContext context, WidgetRef ref, Verification v) {
    final VerificationRapport? rapport = v.rapport;

    // ANALYSE NON TERMINEE : on renvoie vers le suivi, JAMAIS un rapport a
    // moitie rempli. La bascule se fait sur la NULLITE de `rapport`, jamais sur
    // sa vacuite — « rapport: {} ferait croire a un verdict vide, ce qui est le
    // pire malentendu possible sur une plateforme de fact-checking ».
    if (rapport == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (context.mounted) {
          context.pushReplacement(VfRoutes.verification(v.uuid));
        }
      });
      return const _Squelette();
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        // Un rapport en ARBITRAGE HUMAIN n'est pas publie : il existe, il est
        // consultable par son auteur, il n'engage pas encore la plateforme. Le
        // dire en tete evite qu'un verdict provisoire soit lu comme definitif.
        if (v.statut == StatutVerification.arbitrageHumain)
          Padding(
            padding: const EdgeInsets.only(bottom: VfSpacing.stack),
            child: _Bandeau(
              couleur: VfColors.verdictInvestigate,
              texte:
                  '${v.statutLibelle ?? 'En arbitrage'} — ce rapport n est pas '
                  'publie : un journaliste tranche.',
            ),
          ),
        if (v.estUneReprise)
          Padding(
            padding: const EdgeInsets.only(bottom: VfSpacing.stack),
            child: _Bandeau(
              couleur: VfColors.primary,
              texte:
                  'Cette question avait déjà ete vérifiée : la réponse vous a '
                  'ete servie sans debiter d unite.',
            ),
          ),
        BlocDeVerdict(verification: v),
        const SizedBox(height: VfSpacing.stack),
        CarteDuContenuSoumis(affirmation: v.affirmation, contexte: v.contexte),
        const SizedBox(height: VfSpacing.stack),
        CarteSourcesEtPreuves(preuves: rapport.preuves),
        if (rapport.indiceHaine != null) ...<Widget>[
          const SizedBox(height: VfSpacing.stack),
          CarteIndiceDeHaine(rapport: rapport),
        ],
        const SizedBox(height: VfSpacing.stack),
        CartePourquoiCeResultat(rapport: rapport),
        if (rapport.deroule.isNotEmpty) ...<Widget>[
          const SizedBox(height: VfSpacing.stack),
          CarteTracabilite(deroule: rapport.deroule),
        ],
        const SizedBox(height: VfSpacing.stack),
        const CarteAvertissementIA(),
        const SizedBox(height: VfSpacing.stack),
        _CarteContestation(uuid: uuid),
        // Le bloc d'utilite est ECRIT mais MASQUE : voir DrapeauxM2. Pas de
        // boutons inertes, pas de « merci » sans destinataire.
        if (DrapeauxM2.retourDUtilite) ...<Widget>[
          const SizedBox(height: VfSpacing.stack),
          const _CarteUtilite(),
        ],
        const SizedBox(height: VfSpacing.stack),
        _CartePartage(verification: v),
      ],
    );
  }

  /// Le repli d'un rapport introuvable.
  ///
  /// **Il ne dit jamais « supprimé ».** « Le contrat répond 404 sur ce qui
  /// n'appartient pas au demandeur, pour qu'un uuid deviné ne renseigne sur
  /// rien » : écrire « ce rapport a été supprimé » serait une affirmation que le
  /// client n'a pas les moyens de faire.
  Widget _repli(BuildContext context, Object echec) => VfEmptyState(
    kind: VfEmptyKind.rienEncore,
    icon: Icons.description_outlined,
    title: echec is NotFoundFailure
        ? 'Ce rapport n est pas disponible'
        : 'Impossible d ouvrir ce rapport',
    message: echec is NotFoundFailure
        ? 'Il a peut-être ete produit depuis un autre compte.'
        : 'Verifiez votre connexion, puis reessayez.',
    action: VfGhostButton(
      label: 'Mes vérifications',
      expanded: false,
      onPressed: () => context.go(VfRoutes.myVerifications),
    ),
  );

  Future<void> _ouvrirLePartage(BuildContext context, Verification v) async {
    await VfBottomSheet.show<void>(
      context: context,
      title: 'Partager',
      builder: (BuildContext sheet) => FeuilleDePartage(
        onWhatsApp: () {
          Navigator.of(sheet).pop();
          partagerSurWhatsApp(context, v);
        },
        onCopier: () async {
          Navigator.of(sheet).pop();
          await copierLeTexte(v);
          if (context.mounted) {
            VfToast.show(context, message: 'Texte copie.');
          }
        },
        onImage: () {
          Navigator.of(sheet).pop();
          _partagerLImage(context, v);
        },
      ),
    );
  }

  /// Rend la carte 9:16 **hors écran** puis la partage.
  ///
  /// POURQUOI un `Overlay` hors du viewport plutôt qu'un widget masqué :
  /// `RepaintBoundary.toImage` a besoin que la carte soit **réellement peinte**.
  /// Un `Offstage` ne l'est pas, et l'image en sortirait vide.
  void _partagerLImage(BuildContext context, Verification v) {
    final GlobalKey cle = GlobalKey();
    late final OverlayEntry entree;
    entree = OverlayEntry(
      builder: (BuildContext _) => Positioned(
        // Hors de l'ecran : peint, mais invisible.
        left: -CarteDePartage.dimensions.width * 2,
        top: 0,
        child: RepaintBoundary(
          key: cle,
          child: MediaQuery(
            // La carte est dessinee a 1080 x 1920 : on neutralise le facteur
            // d'echelle du telephone, sinon un reglage d'accessibilite a 200 %
            // deborderait la carte partagee.
            data: const MediaQueryData(),
            child: Directionality(
              textDirection: TextDirection.ltr,
              child: CarteDePartage(verification: v),
            ),
          ),
        ),
      ),
    );

    Overlay.of(context).insert(entree);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await partagerLImage(cle, texte: texteDePartage(v));
      entree.remove();
    });
  }
}

/// La demande de contre-expertise.
class _CarteContestation extends ConsumerWidget {
  const _CarteContestation({required this.uuid});

  final String uuid;

  @override
  Widget build(BuildContext context, WidgetRef ref) => VfCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Ce verdict ne vous semble pas correct ?',
          style: VfTextStyles.hSm,
        ),
        const SizedBox(height: VfSpacing.x6),
        Text(
          'Demandez une contre-expertise par nos journalistes.',
          style: VfTextStyles.tXs,
        ),
        const SizedBox(height: VfSpacing.x12),
        VfInkButton(
          label: 'Demander un expert',
          onPressed: () => _ouvrir(context, ref),
        ),
      ],
    ),
  );

  Future<void> _ouvrir(BuildContext context, WidgetRef ref) async {
    final TextEditingController champ = TextEditingController();
    final ValueNotifier<String?> erreur = ValueNotifier<String?>(null);

    await VfBottomSheet.show<void>(
      context: context,
      title: 'Demander une contre-expertise',
      builder: (BuildContext sheet) => Padding(
        padding: EdgeInsets.fromLTRB(
          VfSpacing.x16,
          0,
          VfSpacing.x16,
          VfSpacing.x16,
        ),
        child: ValueListenableBuilder<String?>(
          valueListenable: erreur,
          builder: (BuildContext context, String? message, Widget? _) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                'Expliquez brievement pourquoi le résultat merite une nouvelle '
                'analyse.',
                style: VfTextStyles.tSm,
              ),
              const SizedBox(height: VfSpacing.x12),
              VfTextArea(
                // 10 caracteres, et non les 20 du simulateur : « un formulaire
                // ne doit pas exiger plus que ce qui sera accepte » — le contrat
                // en demande 10.
                label: 'Votre argumentaire (10 caractères minimum)...',
                value: champ.text,
                controller: champ,
                maxLength: ValiderLaSaisie.detailMaximum,
                errorText: message,
                onChanged: (_) {},
              ),
              const SizedBox(height: VfSpacing.x12),
              VfPrimaryButton(
                label: 'Envoyer la demande',
                onPressed: () async {
                  final ErreurDeSaisie? refus = ValiderLaSaisie.contestation(
                    champ.text,
                  );
                  if (refus != null) {
                    erreur.value = refus.message;
                    return;
                  }
                  Navigator.of(sheet).pop();
                  await _envoyer(context, ref, champ.text);
                },
              ),
            ],
          ),
        ),
      ),
    );
    champ.dispose();
    erreur.dispose();
  }

  Future<void> _envoyer(
    BuildContext context,
    WidgetRef ref,
    String detail,
  ) async {
    try {
      await ref
          .read(verificationRepositoryProvider)
          .contester(uuid, detail: detail);
      if (context.mounted) {
        // Le simulateur promet « reponse sous 48 h ». Ce delai n'est engage par
        // personne dans le contrat : on ne le reprend pas — « ne conserver ce
        // delai que si l'equipe s'y engage ».
        VfToast.show(context, message: 'Demande transmise a la redaction.');
      }
    } on Failure catch (echec) {
      if (context.mounted) {
        VfToast.error(
          context,
          echec.serverMessage ?? 'La demande n a pas pu partir.',
        );
      }
    }
  }
}

/// « Ce rapport vous a-t-il été utile ? » — **écrit, jamais affiché en v1**.
///
/// Il est conservé pour la même raison que l'écran 08 : le jour où la table
/// `verification_feedback` existera, on lève le drapeau, on ne réécrit pas.
class _CarteUtilite extends StatelessWidget {
  const _CarteUtilite();

  @override
  Widget build(BuildContext context) => VfCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text('Ce rapport vous a-t-il ete utile ?', style: VfTextStyles.hSm),
        const SizedBox(height: VfSpacing.x12),
        Row(
          children: <Widget>[
            Expanded(
              child: VfOutlinedButton(label: '👍 Oui', onPressed: () {}),
            ),
            const SizedBox(width: VfSpacing.x9),
            Expanded(
              child: VfGhostButton(label: '👎 Pas vraiment', onPressed: () {}),
            ),
          ],
        ),
      ],
    ),
  );
}

class _CartePartage extends StatelessWidget {
  const _CartePartage({required this.verification});

  final Verification verification;

  @override
  Widget build(BuildContext context) => VfCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text('📣 Diffuser la verite', style: VfTextStyles.hSm),
        const SizedBox(height: VfSpacing.x6),
        Text(
          'Partagez ce rapport pour stopper la propagation.',
          style: VfTextStyles.tXs,
        ),
        const SizedBox(height: VfSpacing.x12),
        VfButton(
          label: 'Partager sur WhatsApp',
          variant: VfButtonVariant.primary,
          onPressed: () => partagerSurWhatsApp(context, verification),
        ),
      ],
    ),
  );
}

class _Bandeau extends StatelessWidget {
  const _Bandeau({required this.couleur, required this.texte});

  final Color couleur;
  final String texte;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(VfSpacing.x12),
    decoration: BoxDecoration(
      color: couleur.withValues(alpha: 0.08),
      borderRadius: VfRadii.mdAll,
      border: Border.all(color: couleur.withValues(alpha: 0.35)),
    ),
    child: Text(texte, style: VfTextStyles.tSm.copyWith(color: VfColors.ink)),
  );
}

class _Squelette extends StatelessWidget {
  const _Squelette();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.all(VfSpacing.screenInline),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        VfSkeleton(height: 240),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 120),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 160),
      ],
    ),
  );
}
