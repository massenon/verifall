import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../data/datasources/remote/community/ouvreur_de_lien_systeme.dart';
import '../../../../domain/entities/opportunity_entity.dart';
import '../../../../domain/entities/thread_entity.dart' show IssueEcriture;
import '../../../../domain/usecases/community/ouvrir_une_candidature.dart';
import '../../../shared/shared.dart';
import '../providers/hub_providers.dart';
import '../support/liens_m4.dart';
import '../widgets/etats_m4.dart';
import '../widgets/feuille_signalement.dart';
import '../widgets/hub_widgets.dart';

/// Écran 23 — **Détail d'une offre**.
///
/// # Les trois champs qui décident de l'écran viennent tous du serveur
///
/// | Champ | Rôle |
/// |---|---|
/// | `data.verifiee` | pilote « EMPLOYEUR VERIFIE ». « Ça ne se déduit d'aucun autre champ » |
/// | `data.expiree` | pilote « CLOTUREE » **et le retrait du bouton de candidature** |
/// | `data.jours_restants` | le compte à rebours, jamais calculé localement |
///
/// # La candidature s'ouvre en onglet système
///
/// Point de vigilance, mot pour mot : « jamais en WebView interne — le citoyen
/// doit voir le vrai domaine dans la barre d'adresse avant de saisir ses
/// données ». Sur une page qui met en garde contre les fausses offres, masquer
/// le domaine serait une contradiction complète.
///
/// # L'avertissement est systématique
///
/// Il vient de `meta.avertissement` et s'affiche sur **toutes** les fiches :
/// « une offre frauduleuse ne se signale pas d'elle-même, et c'est la plus
/// dangereuse ». Le client ne décide pas de le montrer ; il montre ce qu'il
/// reçoit.
class OpportunityPage extends ConsumerWidget {
  const OpportunityPage({required this.slug, super.key});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<EtatOpportunite> etat = ref.watch(
      opportuniteProvider(slug),
    );

    return Scaffold(
      backgroundColor: VfColors.canvas,
      body: Column(
        children: <Widget>[
          VfAppBar(
            // « Titre = Opportunite Benin ou Opportunite internationale. »
            // Sans portee servie, on garde le nom generique de l'ecran plutot
            // que d'affirmer une origine.
            title:
                etat.value?.fiche.opportunite.portee?.libelleEcran ??
                'Opportunite',
            actions: <Widget>[
              VfIconButton(
                icon: Icons.ios_share_rounded,
                tooltip: 'Partager cette offre',
                onPressed: etat.hasValue
                    ? () => ref
                          .read(partageurDeLienProvider)
                          .partager(
                            texte:
                                '${etat.value!.fiche.opportunite.titre}\n'
                                '${VfRoutes.hubOpportunity(slug)}',
                          )
                    : null,
              ),
            ],
          ),
          Expanded(
            child: etat.when(
              loading: () => const _Squelette(),
              error: (Object erreur, StackTrace _) =>
                  _erreur(context, ref, erreur),
              data: (EtatOpportunite valeur) => _Contenu(etat: valeur),
            ),
          ),
        ],
      ),
    );
  }

  Widget _erreur(BuildContext context, WidgetRef ref, Object erreur) {
    if (erreur is NotFoundFailure) {
      // LE CAS LE PLUS FREQUENT : « un lien partage sur WhatsApp survit a
      // l'offre ». Le repli n'affirme rien — le 404 couvre aussi « pas a vous ».
      return ReplIntrouvable(
        titre: 'Cette offre n est plus disponible',
        libelleSortie: 'Revenir au Hub',
        onSortir: () => context.go(VfRoutes.hub),
      );
    }
    return EtatEchec(
      echec: erreur is Failure ? erreur : const UnexpectedFailure(),
      onReessayer: () async => ref.invalidate(opportuniteProvider(slug)),
      messageHorsLigne:
          'Cette offre n a pas encore ete consultee sur cet appareil : elle ne '
          'peut pas être lue hors ligne.',
    );
  }
}

class _Contenu extends ConsumerStatefulWidget {
  const _Contenu({required this.etat});

  final EtatOpportunite etat;

  @override
  ConsumerState<_Contenu> createState() => _ContenuState();
}

class _ContenuState extends ConsumerState<_Contenu> {
  Opportunite get _offre => widget.etat.fiche.opportunite;

  Future<void> _postuler() async {
    final bool ouvert = await OuvrirUneCandidature(
      ref.read(ouvreurDeLienProvider),
    )(_offre);
    if (!mounted || ouvert) {
      return;
    }
    // « La candidature demande le reseau et le dit » (05-HORS-LIGNE, ecran 23).
    VfToast.error(
      context,
      'La page de candidature n a pas pu être ouverte. Verifiez votre '
      'connexion.',
    );
  }

  Future<void> _signaler() async {
    final SignalementSaisi? saisi = await FeuilleSignalement.ouvrir(context);
    if (saisi == null || !mounted) {
      return;
    }
    try {
      final IssueEcriture issue = await ref
          .read(opportuniteProvider(_offre.slug).notifier)
          .signaler(motif: saisi.motif, detail: saisi.detail);
      if (!mounted) {
        return;
      }
      VfToast.show(
        context,
        message: issue == IssueEcriture.envoyee
            // POURQUOI le delai de 24 h du simulateur n'est PAS repris :
            // « ne conserver ce delai que si l'equipe s'y engage »
            // (03-ECRANS-05 §3.23). Rien ne l'atteste, donc on ne le promet
            // pas.
            ? 'Offre signalee. La redaction va l examiner.'
            : 'Signalement en attente d envoi. Il partira des le retour du '
                  'reseau.',
      );
    } on Failure catch (echec) {
      if (mounted) {
        VfToast.error(
          context,
          echec.hasServerMessage
              ? echec.serverMessage!
              : 'Le signalement n a pas pu être transmis.',
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final Opportunite offre = _offre;
    final String? domaine = OuvrirUneCandidature.domaine(offre.lienCandidature);

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        if (widget.etat.recupereLe != null) ...<Widget>[
          VfStaleDataBanner(
            lueLe: widget.etat.recupereLe!,
            tone: VfStaleTone.echeance,
            onRetry: () async =>
                ref.invalidate(opportuniteProvider(offre.slug)),
          ),
          const SizedBox(height: VfSpacing.stack),
        ],

        Wrap(
          spacing: VfSpacing.x6,
          runSpacing: VfSpacing.x6,
          children: <Widget>[
            if (offre.typeLibelle != null)
              VfTypeChip(
                libelle: offre.typeLibelle!,
                famille: offre.type?.famille,
              ),
            // `data.verifiee` et RIEN d'autre.
            if (offre.verifiee) const VfVerifiedBadge(complet: true),
            // `data.expiree` et RIEN d'autre.
            if (offre.expiree) const VfTypeChip.cloturee(),
          ],
        ),
        const SizedBox(height: VfSpacing.stack),
        Text(offre.titre, style: VfTextStyles.hLg.copyWith(height: 1.2)),
        if (offre.chapo != null) ...<Widget>[
          const SizedBox(height: VfSpacing.x10),
          Text(
            offre.chapo!,
            style: VfTextStyles.tMd.copyWith(fontWeight: FontWeight.w600),
          ),
        ],

        const SizedBox(height: VfSpacing.stack),
        VfFactList(offre: offre),

        if (offre.corps != null) ...<Widget>[
          const SizedBox(height: VfSpacing.stack),
          VfCard(
            child: VfProseBody(
              html: offre.corps!,
              onLinkTap: (String url) => ouvrirLienDeProse(ref, url),
            ),
          ),
        ],

        const SizedBox(height: VfSpacing.stack),
        // UNE OFFRE CLOTUREE N'AFFICHE AUCUN BOUTON DE CANDIDATURE. Ce n'est
        // pas un bouton grise : c'est un encart a la place.
        if (offre.expiree)
          const VfClosedNotice()
        else if (domaine != null)
          VfPrimaryButton(
            label: 'Postuler sur $domaine',
            icon: Icons.open_in_new_rounded,
            // Le bouton annonce le domaine cible ET le fait qu'il quitte
            // l'application (accessibilite de l'ecran 23).
            semanticsLabel:
                'Postuler sur $domaine. Ouvre un onglet hors de l application.',
            onPressed: _postuler,
          ),

        const SizedBox(height: VfSpacing.stack),
        // L'AVERTISSEMENT, SYSTEMATIQUE, ET SON TEXTE VIENT DU SERVEUR.
        if (widget.etat.fiche.avertissement != null)
          VfScamWarning(texte: widget.etat.fiche.avertissement!),

        const SizedBox(height: VfSpacing.stack),
        VfGhostButton(label: 'Signaler cette offre', onPressed: _signaler),
      ],
    );
  }
}

class _Squelette extends StatelessWidget {
  const _Squelette();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        SqueletteDeCarte(lignes: 4),
        SizedBox(height: VfSpacing.stackSm),
        SqueletteDeCarte(),
      ],
    );
  }
}
