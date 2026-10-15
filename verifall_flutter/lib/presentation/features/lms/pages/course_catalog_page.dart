import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../domain/entities/course_enrollment.dart';
import '../../../../domain/entities/course_level.dart';
import '../../../../domain/entities/course_summary.dart';
import '../../../../domain/repositories/course_repository.dart';
import '../../../shared/shared.dart';
import '../providers/catalog_provider.dart';
import '../widgets/course_card.dart';
import '../widgets/lms_banners.dart';
import '../../../../core/session/solde_unites.dart';

/// Écran 14 — Catalogue de cours (`#/academy`).
///
/// # Le filtre par niveau part au SERVEUR
///
/// « On ne filtre jamais côté client une liste paginée » (arbitrage n° 82) : le
/// catalogue est paginé par curseur, et un filtre local ne porterait que sur la
/// page déjà chargée. Chaque pastille relance donc `GET /courses?level=…`.
///
/// Conséquence hors ligne, et elle est honnête : un niveau qu'on n'a jamais
/// chargé n'a pas de cache. L'écran le **dit** au lieu de montrer une liste
/// filtrée localement — qui serait plausible et fausse.
///
/// # Ce que la carte n'affiche pas
///
/// Ni note, ni nombre d'inscrits : l'API ne les sérialise pas. « Nouveau »
/// (arbitrage n° 61), et la ligne d'inscrits disparaît (arbitrage n° 67).
class CourseCatalogPage extends ConsumerStatefulWidget {
  const CourseCatalogPage({super.key});

  @override
  ConsumerState<CourseCatalogPage> createState() => _CourseCatalogPageState();
}

class _CourseCatalogPageState extends ConsumerState<CourseCatalogPage> {
  final ScrollController _defilement = ScrollController();

  @override
  void initState() {
    super.initState();
    _defilement.addListener(_surDefilement);
  }

  @override
  void dispose() {
    _defilement
      ..removeListener(_surDefilement)
      ..dispose();
    super.dispose();
  }

  void _surDefilement() {
    if (!_defilement.hasClients) {
      return;
    }
    final double reste =
        _defilement.position.maxScrollExtent - _defilement.position.pixels;
    if (reste < 400) {
      ref.read(catalogProvider.notifier).pageSuivante();
    }
  }

  @override
  Widget build(BuildContext context) {
    final EtatDuCatalogue etat = ref.watch(catalogProvider);
    final AsyncValue<List<CourseEnrollment>> inscriptions = ref.watch(
      enrollmentProvider,
    );
    final AsyncValue<CourseResume?> reprise = ref.watch(resumeProvider);

    return Scaffold(
      appBar: VfAppBar(
        title: 'Académie',
        leading: context.canPop()
            ? VfAppBarLeading.retour
            : VfAppBarLeading.aucun,
        pointsPill: VfPointsPill(
          // LA source du socle, jamais le cache Drift (contrat §7.1) et plus
          // `session.utilisateur.unites`, qui est le solde A L EMISSION DU JETON
          // et vieillit des la seconde suivante (arbitrage n° 135). `null`
          // affiche un tiret : « mieux vaut un tiret honnete qu'un zero qui
          // ferait croire a un compte vide ».
          solde: ref.watch(soldeUnitesProvider),
          onTap: () => context.go(VfRoutes.wallet),
        ),
        actions: <Widget>[
          VfIconButton(
            icon: Icons.videocam_outlined,
            tooltip: 'Live Classes',
            onPressed: () => context.go(VfRoutes.liveClasses),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref
            ..invalidate(enrollmentProvider)
            ..invalidate(resumeProvider);
          await ref.read(catalogProvider.notifier).charger();
        },
        child: ListView(
          controller: _defilement,
          padding: const EdgeInsets.fromLTRB(
            VfSpacing.screenInline,
            VfSpacing.screenTopExtra,
            VfSpacing.screenInline,
            VfSpacing.screenBottomExtra,
          ),
          children: <Widget>[
            if (etat.vientDuCache && etat.lueLe != null) ...<Widget>[
              VfStaleDataBanner(lueLe: etat.lueLe!),
              const SizedBox(height: VfSpacing.stack),
            ],
            inscriptions.when(
              data: (List<CourseEnrollment> liste) =>
                  ProgressionSummaryCard(inscriptions: liste),
              // Le squelette annonce la FORME de ce qui arrive ; l'indicateur
              // circulaire est reserve aux trois ecrans ou le cache est interdit.
              loading: () =>
                  const VfSkeleton(height: 96, width: double.infinity),
              // Les compteurs viennent de `GET /me/enrollments`, qui exige le
              // reseau : sans lui, la carte disparait plutot que d'afficher
              // « 0 termine », qui serait faux.
              error: (Object _, StackTrace _) => const SizedBox.shrink(),
            ),
            const SizedBox(height: VfSpacing.stack),
            ...reprise.when(
              data: (CourseResume? valeur) => valeur == null
                  // `data: null` n'est pas une erreur : c'est l'etat vide.
                  ? const <Widget>[]
                  : <Widget>[
                      ResumeCard(
                        reprise: valeur,
                        onReprendre: () =>
                            context.push(VfRoutes.course(valeur.cours.slug)),
                      ),
                      const SizedBox(height: VfSpacing.stack),
                    ],
              loading: () => const <Widget>[],
              error: (Object _, StackTrace _) => const <Widget>[],
            ),
            _Pastilles(filtre: etat.filtre),
            const SizedBox(height: VfSpacing.stack),
            ..._corps(etat, inscriptions.value ?? const <CourseEnrollment>[]),
          ],
        ),
      ),
    );
  }

  List<Widget> _corps(
    EtatDuCatalogue etat,
    List<CourseEnrollment> inscriptions,
  ) {
    if (etat.chargement) {
      return <Widget>[
        for (int i = 0; i < 3; i++) ...<Widget>[
          const VfSkeleton(height: 214, width: double.infinity),
          const SizedBox(height: VfSpacing.stackSm),
        ],
      ];
    }

    if (etat.messageDErreur != null) {
      // Aucun cache pour CETTE combinaison de filtres. On ne se rabat pas sur
      // la liste non filtree : servir une autre liste sous le meme titre serait
      // pire qu'une erreur — ce serait une erreur invisible.
      return <Widget>[
        EncartReseauRequis(
          titre: 'Le catalogue a besoin d une connexion',
          raison: etat.filtre.estVide
              ? 'Aucun catalogue n a encore ete telecharge sur cet appareil.'
              : 'Ce niveau n a jamais ete charge : le filtre est applique par '
                    'le serveur, pour que la deuxieme page reste juste.',
          // `Expanded` sur chacun : un bouton du design system prend toute la
          // largeur par defaut, et deux dans une `Row` sans contrainte
          // produisent une mise en page infinie.
          action: Row(
            children: <Widget>[
              Expanded(
                child: VfGhostButton(
                  label: 'Réessayer',
                  onPressed: () => ref.read(catalogProvider.notifier).charger(),
                ),
              ),
              if (!etat.filtre.estVide) ...<Widget>[
                const SizedBox(width: VfSpacing.x8),
                Expanded(
                  child: VfGhostButton(
                    label: 'Revenir a Tous',
                    onPressed: () =>
                        ref.read(catalogProvider.notifier).choisirNiveau(null),
                  ),
                ),
              ],
            ],
          ),
        ),
      ];
    }

    if (etat.estVide) {
      return <Widget>[
        VfEmptyState(
          kind: etat.filtre.estVide
              ? VfEmptyKind.rienEncore
              : VfEmptyKind.aucunResultat,
          title: etat.filtre.estVide
              ? 'Le catalogue est vide'
              : 'Aucun cours a ce niveau',
          message: etat.filtre.estVide
              ? 'La redaction n a pas encore publie de cours. '
                    'En attendant, le Hub d Infos et la vérification vous attendent.'
              : 'Essayez un autre niveau, ou revenez a « Tous ».',
          action: etat.filtre.estVide
              ? VfGhostButton(
                  label: 'Ouvrir le Hub d Infos',
                  onPressed: () => context.go(VfRoutes.hub),
                )
              : VfGhostButton(
                  label: 'Revenir a Tous',
                  onPressed: () =>
                      ref.read(catalogProvider.notifier).choisirNiveau(null),
                ),
        ),
      ];
    }

    return <Widget>[
      for (final CourseSummary cours in etat.cours) ...<Widget>[
        CourseCard(
          cours: cours,
          inscription: _inscriptionDe(cours.slug, inscriptions),
          onTap: () => context.push(VfRoutes.course(cours.slug)),
        ),
        const SizedBox(height: VfSpacing.stackSm),
      ],
      if (etat.chargementDeSuite)
        const Padding(
          padding: EdgeInsets.symmetric(vertical: VfSpacing.x16),
          child: VfSkeleton(height: 214, width: double.infinity),
        ),
    ];
  }

  static CourseEnrollment? _inscriptionDe(
    String slug,
    List<CourseEnrollment> inscriptions,
  ) {
    for (final CourseEnrollment i in inscriptions) {
      if (i.coursSlug == slug) {
        return i;
      }
    }
    return null;
  }
}

/// `Tous · Débutant · Intermédiaire · Avancé`.
///
/// Les libellés viennent de l'énumération du domaine, qui les tient du contrat.
class _Pastilles extends ConsumerWidget {
  const _Pastilles({required this.filtre});

  final FiltreCatalogue filtre;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    const List<String> libelles = <String>[
      'Tous',
      'Debutant',
      'Intermediaire',
      'Avance',
    ];
    final int index = filtre.niveau == null
        ? 0
        : NiveauCours.filtrables.indexOf(filtre.niveau!) + 1;

    return VfChips(
      labels: libelles,
      selectedIndex: index < 0 ? 0 : index,
      onSelected: (int choisi) {
        // Le filtre part au SERVEUR : chaque pastille relance la requete avec
        // `level`, et le curseur est jete (il designe une position dans l'ordre
        // de l'ancienne liste).
        ref
            .read(catalogProvider.notifier)
            .choisirNiveau(
              choisi == 0 ? null : NiveauCours.filtrables[choisi - 1],
            );
      },
    );
  }
}
