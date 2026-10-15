/// Routes du parcours M2 - Accueil (home).
///
/// # Pourquoi ce fichier existe
///
/// `app_router.dart` et `database.dart` sont les deux seuls endroits ou les cinq
/// parcours devraient normalement ecrire tous ensemble. On l'evite ainsi : chaque
/// parcours ecrit **son propre fichier** de routes, l'agregateur importe les six
/// **des le socle**, et personne ne rouvre l'agregateur.
///
/// # Le choix entre les deux listes n'est pas cosmetique
///
/// * [homeShellRoutes] — les ecrans qui portent la **barre basse** ;
/// * [homeRootRoutes] — ceux qui ne la portent pas, et qui « ne doivent pas la
///   faire reapparaitre a mi-parcours ».
///
/// Le tableau de bord est un onglet : il porte la barre. Les notifications la
/// conservent aussi — « on n'a pas quitte la section » (fiche de l'ecran 08).
library;

import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import '../../presentation/features/home/pages/dashboard_page.dart';
import '../../presentation/features/home/pages/notifications_page.dart';
import 'vf_page_transitions.dart';
import 'vf_routes.dart';

/// Ecrans de home portant la barre basse.
final List<RouteBase> homeShellRoutes = <RouteBase>[
  GoRoute(
    path: VfRoutes.dashboard,
    name: 'dashboard',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.fade<void>(
          key: state.pageKey,
          name: state.name,
          child: const DashboardPage(),
        ),
  ),

  // L'ecran 08 est ECRIT ET ROUTE, mais RIEN N'Y MENE : la cloche du tableau de
  // bord est masquee tant qu'aucun endpoint n'emet de compteur. La route existe
  // pour que le jour ou les endpoints arrivent, on leve un drapeau au lieu de
  // reecrire un ecran (voir DrapeauxM2 et la fiche de l'ecran 08).
  GoRoute(
    path: VfRoutes.notifications,
    name: 'notifications',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const NotificationsPage(),
        ),
  ),
];

/// Ecrans de home sans barre basse.
///
/// Il n'y en a aucun : les deux ecrans de l'accueil restent dans la section.
final List<RouteBase> homeRootRoutes = <RouteBase>[];
