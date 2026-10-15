import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/certificate_summary.dart';
import '../../../../domain/entities/course_detail.dart';
import '../../../../domain/entities/course_enrollment.dart';
import '../../../../domain/entities/course_summary.dart';
import '../../../../domain/entities/lesson_summary.dart';
import '../../../../domain/entities/quiz_summary.dart';
import '../../../../domain/repositories/course_repository.dart';
import '../../../shared/shared.dart';
import '../providers/course_download_provider.dart';
import '../providers/course_provider.dart';
import '../widgets/course_card.dart';
import '../widgets/lesson_tile.dart';
import '../widgets/lms_banners.dart';
import '../../../../core/format/vf_formats.dart';

/// Écran 15 — Fiche de cours (`#/course`).
///
/// # Trois règles portées par cet écran
///
/// 1. **Le déverrouillage est lu du serveur** (`ouverte`, `acquise`), jamais
///    recalculé — et **un élément verrouillé affiche sa condition**.
/// 2. **Aucune note fabriquée** : l'API ne sérialise ni note ni inscrits. La
///    troisième pastille dit « Nouveau ».
/// 3. **Le téléchargement est visible, mesuré et annulable**, et il ne demande
///    que les leçons que le serveur a ouvertes — les autres répondraient `403`.
class CourseDetailPage extends ConsumerWidget {
  const CourseDetailPage({required this.slug, super.key});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<FicheLue> fiche = ref.watch(courseProvider(slug));

    return Scaffold(
      appBar: VfAppBar(
        // Le titre de la barre est la CATEGORIE du cours, pas son titre :
        // le titre vit sur la couverture (simulateur, ecran 15).
        title: fiche.value?.fiche.cours.categorie ?? '',
        // Pas de pastille de points : « montrer un solde pendant une lecture
        // n'apporte rien et ajoute une cible tactile parasite ».
      ),
      body: fiche.when(
        loading: () => const _SqueletteDeFiche(),
        error: (Object erreur, StackTrace _) => _Erreur(erreur: erreur),
        data: (FicheLue lue) => _Contenu(lue: lue, slug: slug),
      ),
    );
  }
}

class _Contenu extends ConsumerWidget {
  const _Contenu({required this.lue, required this.slug});

  final FicheLue lue;
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final CourseDetail fiche = lue.fiche;
    final CourseEnrollment? inscription = fiche.inscription;
    final AsyncValue<CertificateSummary?> certificat = ref.watch(
      certificatDuCoursProvider(slug),
    );

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        if (lue.vientDuCache) ...<Widget>[
          VfStaleDataBanner(lueLe: lue.lueLe),
          const SizedBox(height: VfSpacing.stack),
        ],
        CoverAvecLibelle(
          cleDeDegrade: fiche.cours.slug,
          imageUrl: fiche.cours.couvertureUrl,
          libelle: fiche.cours.titre,
          hauteurSurchargee: 140,
        ),
        const SizedBox(height: VfSpacing.stack),
        _EnTete(cours: fiche.cours),
        const SizedBox(height: VfSpacing.stack),
        _LigneFormateur(fiche: fiche),
        const SizedBox(height: VfSpacing.stack),
        _BlocDAction(fiche: fiche, slug: slug, certificat: certificat.value),
        const SizedBox(height: VfSpacing.stack),
        _Telechargement(slug: slug, fiche: fiche),
        if (fiche.cours.objectifs.isNotEmpty) ...<Widget>[
          const SizedBox(height: VfSpacing.stack),
          _Objectifs(objectifs: fiche.cours.objectifs),
        ],
        const SizedBox(height: VfSpacing.stack),
        _Programme(
          fiche: fiche,
          slug: slug,
          leconsHorsLigne: lue.leconsHorsLigne,
          horsLigne: lue.vientDuCache,
          inscription: inscription,
        ),
      ],
    );
  }
}

class _EnTete extends StatelessWidget {
  const _EnTete({required this.cours});

  final CourseSummary cours;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: <Widget>[
            if (cours.niveau.estRenseigne) ...<Widget>[
              VfTag(label: cours.niveau.libelle),
              const SizedBox(width: VfSpacing.x6),
            ],
            VfTag(
              label: cours.estGratuit
                  ? 'Gratuit'
                  : '${cours.coutUnites} unités',
              variant: cours.estGratuit
                  ? VfTagVariant.prim
                  : VfTagVariant.neutre,
            ),
            const SizedBox(width: VfSpacing.x6),
            // Le simulateur met ici « ★ 4,8 · 1 245 ». L'API ne sert ni note ni
            // nombre d'inscrits : « Nouveau », et rien d'autre (arbitrages
            // n° 61 et n° 67).
            const VfTag(label: 'Nouveau'),
          ],
        ),
        const SizedBox(height: VfSpacing.x8),
        Text(cours.titre, style: VfTextStyles.hLg),
        if (cours.description.isNotEmpty) ...<Widget>[
          const SizedBox(height: 7),
          Text(cours.description, style: VfTextStyles.tSm),
        ],
        if (cours.estOffert) ...<Widget>[
          const SizedBox(height: VfSpacing.x8),
          const VfTag(label: 'Offert en ce moment', variant: VfTagVariant.prim),
        ],
      ],
    );
  }
}

/// La ligne formateur.
///
/// **Sans rôle.** `AuteurResource` porte uuid, nom d'affichage et initiales —
/// rien d'autre : « publier "untel est modérateur" sur chaque message dresserait
/// la liste nominative de l'équipe » (§9 du contrat). Le « Formateur principal »
/// du simulateur disparaît donc, et l'écart est consigné.
class _LigneFormateur extends StatelessWidget {
  const _LigneFormateur({required this.fiche});

  final CourseDetail fiche;

  @override
  Widget build(BuildContext context) {
    final Intervenant? formateur = fiche.cours.formateur;
    if (formateur == null) {
      return const SizedBox.shrink();
    }
    return VfCard(
      tight: true,
      child: Row(
        children: <Widget>[
          VfAvatar(
            initials: formateur.initiales,
            size: VfAvatarSize.petit,
            prim: true,
          ),
          const SizedBox(width: VfSpacing.x10),
          Expanded(
            child: Text(
              formateur.nomAffichage,
              style: VfTextStyles.tSm.copyWith(
                color: VfColors.ink,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          if (fiche.cours.duree.isNotEmpty)
            Text(fiche.cours.duree, style: VfTextStyles.tXs),
        ],
      ),
    );
  }
}

class _BlocDAction extends ConsumerWidget {
  const _BlocDAction({
    required this.fiche,
    required this.slug,
    required this.certificat,
  });

  final CourseDetail fiche;
  final String slug;
  final CertificateSummary? certificat;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<IssueDInscription?> inscription = ref.watch(
      inscriptionProvider,
    );

    if (!fiche.estInscrit) {
      return Column(
        children: <Widget>[
          VfPrimaryButton(
            label: fiche.cours.estGratuit
                ? 'S inscrire gratuitement'
                : 'S inscrire — ${fiche.cours.coutUnites} unités',
            expanded: true,
            busy: inscription.isLoading,
            onPressed: inscription.isLoading
                ? null
                : () => _sInscrire(context, ref),
          ),
          if (inscription.hasError) ...<Widget>[
            const SizedBox(height: VfSpacing.x10),
            _RefusDInscription(erreur: inscription.error!),
          ],
        ],
      );
    }

    final int avancement = fiche.inscription?.avancement ?? 0;
    final LessonSummary? prochaine = fiche.prochaineLecon;

    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: <Widget>[
              Text('Progression', style: VfTextStyles.tXs),
              Text(
                '$avancement %',
                style: VfTextStyles.tXs.copyWith(
                  color: VfColors.ink,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: VfSpacing.x6),
          VfProgressBar(value: avancement / 100),
          const SizedBox(height: 13),
          if (certificat != null)
            VfPrimaryButton(
              label: 'Voir mon certificat',
              expanded: true,
              onPressed: () =>
                  context.push(VfRoutes.certificate(certificat!.code)),
            )
          else
            VfPrimaryButton(
              label: 'Reprendre',
              // « Un bouton annonce ce qui va se passer — "Reprendre a la
              // lecon 4" plutôt que "Reprendre" ».
              semanticsLabel: prochaine == null
                  ? 'Reprendre le cours'
                  : 'Reprendre a ${prochaine.titre}',
              expanded: true,
              onPressed: prochaine == null
                  ? null
                  : () => context.push(VfRoutes.lesson(slug, prochaine.uuid)),
            ),
        ],
      ),
    );
  }

  Future<void> _sInscrire(BuildContext context, WidgetRef ref) async {
    final IssueDInscription? issue = await ref
        .read(inscriptionProvider.notifier)
        .sInscrire(slug);
    if (!context.mounted || issue == null) {
      return;
    }
    VfToast.show(
      context,
      message: issue.enFile
          // « L'ecran doit dire clairement que le debit n'est pas encore
          // effectue — le solde affiche ne bouge pas avant la reponse du
          // serveur » (§ hors ligne de l'ecran 14).
          ? 'Inscription en attente d envoi : rien n a encore ete debite.'
          : 'Vous etes inscrit. Bonne lecture.',
    );
  }
}

/// Le refus d'inscription.
///
/// « Inscription refusée faute d'unités : l'écran doit **proposer la recharge**
/// plutôt que de s'arrêter au refus. »
class _RefusDInscription extends StatelessWidget {
  const _RefusDInscription({required this.erreur});

  final Object erreur;

  @override
  Widget build(BuildContext context) {
    final bool soldeInsuffisant = erreur is InsufficientBalanceFailure;
    final String message = erreur is Failure
        ? ((erreur as Failure).serverMessage ?? 'L inscription n a pas abouti.')
        : 'L inscription n a pas abouti.';

    return EncartReseauRequis(
      titre: soldeInsuffisant
          ? 'Il vous manque des unités'
          : 'Inscription refusee',
      // Le message du SERVEUR, affiche tel quel : il dit combien d'unites
      // manquent, et le client n'a pas ce contexte.
      raison: message,
      action: soldeInsuffisant
          ? VfPrimaryButton(
              label: 'Recharger mes unités',
              onPressed: () => context.go(VfRoutes.wallet),
            )
          : null,
    );
  }
}

class _Objectifs extends StatelessWidget {
  const _Objectifs({required this.objectifs});

  final List<String> objectifs;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text('Ce que vous saurez faire', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x10),
          for (final String objectif in objectifs)
            Padding(
              padding: const EdgeInsets.only(bottom: VfSpacing.x9),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Icon(
                    Icons.check_circle_rounded,
                    size: 18,
                    color: VfColors.primary,
                  ),
                  const SizedBox(width: VfSpacing.x8),
                  Expanded(
                    child: Text(
                      objectif,
                      style: VfTextStyles.tSm.copyWith(color: VfColors.ink),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _Programme extends StatelessWidget {
  const _Programme({
    required this.fiche,
    required this.slug,
    required this.leconsHorsLigne,
    required this.horsLigne,
    required this.inscription,
  });

  final CourseDetail fiche;
  final String slug;
  final Set<String> leconsHorsLigne;
  final bool horsLigne;
  final CourseEnrollment? inscription;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Programme · ${fiche.lecons.length} leçons',
          style: VfTextStyles.hSm,
        ),
        const SizedBox(height: VfSpacing.x10),
        VfMenuList(
          children: <Widget>[
            for (int i = 0; i < fiche.lecons.length; i++)
              LessonTile(
                lecon: fiche.lecons[i],
                numero: i + 1,
                disponibleHorsLigne: leconsHorsLigne.contains(
                  fiche.lecons[i].uuid,
                ),
                horsLigne: horsLigne,
                onTap: () =>
                    context.push(VfRoutes.lesson(slug, fiche.lecons[i].uuid)),
              ),
            for (final QuizSummary quiz in fiche.quiz)
              QuizTile(
                quiz: quiz,
                onTap: () => context.push(VfRoutes.quiz(slug)),
              ),
          ],
        ),
      ],
    );
  }
}

/// Le bloc de téléchargement hors ligne.
///
/// # Ce qu'il annonce, et ce qu'il refuse d'annoncer
///
/// Il annonce **le nombre de leçons qui vont partir** et ce qu'elles
/// contiennent. Il n'annonce **pas** une taille avant de commencer : l'API ne
/// donne le poids d'aucune leçon, et « environ 2 Mo » serait un chiffre inventé.
/// La taille s'affiche pendant, puis mesurée à la fin.
///
/// # Pourquoi toutes les leçons ne partent pas
///
/// `GET /courses/{slug}/lessons/{uuid}` répond `403` sur une leçon verrouillée.
/// Le téléchargement suit donc la progression : il grossit à mesure qu'on
/// avance, et le bloc le dit — plutôt que de laisser croire que le cours entier
/// tient dans la poche.
class _Telechargement extends ConsumerWidget {
  const _Telechargement({required this.slug, required this.fiche});

  final String slug;
  final CourseDetail fiche;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!fiche.estInscrit) {
      // Rien a telecharger tant qu'on n'est pas inscrit : le serveur
      // n'ouvrirait aucune lecon.
      return const SizedBox.shrink();
    }

    final EtatDeTelechargementDuCours etat = ref.watch(
      telechargementProvider(slug),
    );
    final int disponibles = fiche.leconsTelechargeables.length;
    final int total = fiche.lecons.length;

    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(
                Icons.download_for_offline_outlined,
                size: 18,
                color: VfColors.primaryDark,
              ),
              const SizedBox(width: VfSpacing.x8),
              Expanded(
                child: Text('Lire sans connexion', style: VfTextStyles.hSm),
              ),
            ],
          ),
          const SizedBox(height: VfSpacing.x8),
          Text(
            etat.estTelecharge
                ? '$disponibles leçon(s) sur $total disponibles hors ligne · '
                      '${VfFormats.octets(etat.octetsStockes)}'
                : '$disponibles leçon(s) sur $total peuvent partir maintenant. '
                      'Les suivantes se debloquent au fur et a mesure.',
            style: VfTextStyles.tXs,
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(
            'Texte des leçons uniquement — les vidéos ne sont pas '
            'telechargees. La taille exacte s affiche pendant le '
            'telechargement.',
            style: VfTextStyles.tXs,
          ),
          if (etat.enCours) ...<Widget>[
            const SizedBox(height: VfSpacing.x12),
            VfProgressBar(value: etat.progression?.avancement ?? 0),
            const SizedBox(height: VfSpacing.x6),
            Text(
              '${etat.progression?.leconsFaites ?? 0} / '
              '${etat.progression?.leconsAFaire ?? disponibles} · '
              '${VfFormats.octets(etat.progression?.octets ?? 0)}',
              style: VfTextStyles.tXs,
            ),
            const SizedBox(height: VfSpacing.x10),
            VfGhostButton(
              label: 'Annuler',
              expanded: true,
              onPressed: () =>
                  ref.read(telechargementProvider(slug).notifier).annuler(),
            ),
          ] else ...<Widget>[
            const SizedBox(height: VfSpacing.x12),
            if (etat.estTelecharge)
              VfGhostButton(
                label: 'Supprimer le téléchargement',
                expanded: true,
                onPressed: () =>
                    ref.read(telechargementProvider(slug).notifier).supprimer(),
              )
            else
              VfPrimaryButton(
                label: 'Télécharger pour lire hors ligne',
                expanded: true,
                onPressed: disponibles == 0
                    ? null
                    : () => ref
                          .read(telechargementProvider(slug).notifier)
                          .telecharger(),
              ),
          ],
          if (etat.progression?.echec != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x10),
            Text(
              'Le téléchargement s est arrete avant la fin. Ce qui est déjà '
              'la reste lisible hors ligne.',
              style: VfTextStyles.tXs.copyWith(color: VfColors.verdictFalse),
            ),
          ],
        ],
      ),
    );
  }
}

class _SqueletteDeFiche extends StatelessWidget {
  const _SqueletteDeFiche();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        VfSkeleton(height: 140, width: double.infinity),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 24, width: 220),
        SizedBox(height: VfSpacing.x8),
        VfSkeleton(height: 60, width: double.infinity),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 46, width: double.infinity),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 180, width: double.infinity),
      ],
    );
  }
}

/// L'erreur de chargement d'une fiche.
///
/// « Cours dépublié atteint par lien profond : écran de repli explicite. » Et le
/// mot ne dit jamais « supprimé » : un 404 signifie « pas pour vous, ou pas
/// là ».
class _Erreur extends StatelessWidget {
  const _Erreur({required this.erreur});

  final Object erreur;

  @override
  Widget build(BuildContext context) {
    final bool introuvable = erreur is NotFoundFailure;
    return Padding(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      child: VfEmptyState(
        kind: VfEmptyKind.aucunResultat,
        title: introuvable
            ? 'Ce cours n est pas disponible'
            : 'Chargement impossible',
        message: introuvable
            ? 'Il n est plus publie, ou il ne vous est pas accessible.'
            : 'Le cours n a pas pu être charge, et rien n en est encore '
                  'enregistre sur cet appareil.',
        action: VfGhostButton(
          label: 'Revenir au catalogue',
          onPressed: () => context.go(VfRoutes.catalogue),
        ),
      ),
    );
  }
}
