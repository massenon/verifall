/// Routes du parcours M3 - Academie (lms).
///
/// # Pourquoi ce fichier existe
///
/// `app_router.dart` et `database.dart` sont les deux seuls endroits ou les cinq
/// parcours devraient normalement ecrire tous ensemble. On l'evite ainsi : chaque
/// parcours ecrit **son propre fichier** de routes, l'agregateur importe les six
/// **des le socle**, et personne ne rouvre l'agregateur.
///
/// # Le partage entre les deux listes, et pourquoi il n'est pas cosmetique
///
/// * [lmsShellRoutes] — le catalogue et les Live Classes portent la **barre
///   basse** : ce sont deux entrees de l'onglet « Apprendre », et on y revient.
/// * [lmsRootRoutes] — la fiche d'un cours, une lecon, un quiz et un certificat
///   ne la portent pas. Ce sont des ecrans de **lecture** : « ceux qui ne la
///   portent pas ne doivent pas la faire reapparaitre a mi-parcours » (§1.4), et
///   `VfPointsPill` n'y apparait pas non plus — « montrer un solde pendant une
///   lecture n'apporte rien et ajoute une cible tactile parasite ».
///
/// Les chemins se prennent dans `VfRoutes`, jamais ecrits a la main : c'est ce
/// qui permet aux quatre autres parcours d'atterrir ici par la navigation, sans
/// aucun import croise entre deux dossiers `features/` (§8.1, regle 4).
library;

import 'package:go_router/go_router.dart';

import '../../presentation/features/lms/pages/certificate_page.dart';
import '../../presentation/features/lms/pages/course_catalog_page.dart';
import '../../presentation/features/lms/pages/course_detail_page.dart';
import '../../presentation/features/lms/pages/lesson_page.dart';
import '../../presentation/features/lms/pages/live_classes_page.dart';
import '../../presentation/features/lms/pages/quiz_page.dart';
import 'vf_page_transitions.dart';
import 'vf_routes.dart';

/// Ecrans de lms portant la barre basse.
final List<RouteBase> lmsShellRoutes = <RouteBase>[
  GoRoute(
    path: VfRoutes.catalogue,
    name: 'lms.catalogue',
    // `fade` : c'est un changement d'onglet, pas un empilement.
    pageBuilder: (_, _) =>
        VfPageTransitions.fade<void>(child: const CourseCatalogPage()),
  ),
  GoRoute(
    path: VfRoutes.liveClasses,
    name: 'lms.liveClasses',
    pageBuilder: (_, _) =>
        VfPageTransitions.fade<void>(child: const LiveClassesPage()),
  ),
];

/// Ecrans de lms sans barre basse.
final List<RouteBase> lmsRootRoutes = <RouteBase>[
  GoRoute(
    // `/cours/:slug` — l'identifiant d'un cours est son SLUG, jamais un entier.
    path: '/cours/:slug',
    name: 'lms.cours',
    pageBuilder: (_, GoRouterState state) => VfPageTransitions.push<void>(
      child: CourseDetailPage(slug: state.pathParameters['slug'] ?? ''),
    ),
    routes: <RouteBase>[
      GoRoute(
        // `/cours/:slug/lecon/:uuid` — l'identifiant d'une lecon est un UUID.
        path: 'lecon/:uuid',
        name: 'lms.lecon',
        pageBuilder: (_, GoRouterState state) => VfPageTransitions.push<void>(
          child: LessonPage(
            slug: state.pathParameters['slug'] ?? '',
            leconUuid: state.pathParameters['uuid'] ?? '',
          ),
        ),
      ),
      GoRoute(
        path: 'quiz',
        name: 'lms.quiz',
        pageBuilder: (_, GoRouterState state) => VfPageTransitions.push<void>(
          child: QuizPage(slug: state.pathParameters['slug'] ?? ''),
        ),
      ),
    ],
  ),
  GoRoute(
    // `/certificat/:code` — par le CODE PUBLIC, pas par un uuid : « il est
    // concu pour etre lu a voix haute et recopie » (§4 du contrat).
    path: '/certificat/:code',
    name: 'lms.certificat',
    pageBuilder: (_, GoRouterState state) => VfPageTransitions.push<void>(
      child: CertificatePage(code: state.pathParameters['code'] ?? ''),
    ),
  ),
];
