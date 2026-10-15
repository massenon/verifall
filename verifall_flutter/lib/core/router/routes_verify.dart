/// Routes du parcours M2 - Verifier (verify).
///
/// # Le choix entre les deux listes n'est pas cosmetique
///
/// * [verifyShellRoutes] — les ecrans qui portent la **barre basse** : la
///   saisie d'une vérification, l'historique et le repertoire d'outils. Le
///   simulateur les dessine tous les trois avec la barre.
/// * [verifyRootRoutes] — ceux qui ne la portent pas : le suivi d'une analyse et
///   le rapport. Ce sont des lectures, et elles « ne doivent pas faire
///   reapparaitre la barre a mi-parcours » — le simulateur ne l'affiche
///   d'ailleurs sur aucun des deux.
///
/// Les chemins se prennent dans `VfRoutes` : c'est ce qui permet aux quatre
/// autres parcours de renvoyer ici **par la navigation**, sans jamais d'import
/// croise entre deux dossiers `features/`.
library;

import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import '../../presentation/features/verify/pages/report_page.dart';
import '../../presentation/features/verify/pages/tools_directory_page.dart';
import '../../presentation/features/verify/pages/verification_history_page.dart';
import '../../presentation/features/verify/pages/verification_progress_page.dart';
import '../../presentation/features/verify/pages/verify_page.dart';
import 'vf_page_transitions.dart';
import 'vf_routes.dart';

/// Ecrans de verify portant la barre basse.
final List<RouteBase> verifyShellRoutes = <RouteBase>[
  GoRoute(
    path: VfRoutes.verify,
    name: 'verifier',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.fade<void>(
          key: state.pageKey,
          name: state.name,
          child: const VerifyPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.myVerifications,
    name: 'mes-verifications',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const VerificationHistoryPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.tools,
    name: 'outils',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const ToolsDirectoryPage(),
        ),
  ),
];

/// Ecrans de verify sans barre basse.
final List<RouteBase> verifyRootRoutes = <RouteBase>[
  GoRoute(
    // `/verification/:uuid` — jamais un identifiant auto-incremente en URL.
    path: '/verification/:uuid',
    name: 'verification',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: VerificationProgressPage(
            uuid: state.pathParameters['uuid'] ?? '',
          ),
        ),
  ),
  GoRoute(
    path: '/rapport/:uuid',
    name: 'rapport',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: ReportPage(uuid: state.pathParameters['uuid'] ?? ''),
        ),
  ),
];
