/// Routes du parcours M5 - Profil, unites, parrainage (profile).
///
/// # Pourquoi ce fichier existe
///
/// `app_router.dart` et `database.dart` sont les deux seuls endroits ou les cinq
/// parcours devraient normalement ecrire tous ensemble. On l'evite ainsi : chaque
/// parcours ecrit **son propre fichier** de routes, l'agregateur importe les six
/// **des le socle** avec des listes vides, et personne ne rouvre l'agregateur.
/// Les cinq branches se fusionnent sans conflit (section 8.0).
///
/// # Comment s'en servir
///
/// Deux listes, et le choix entre elles n'est pas cosmetique :
///
/// * [profileShellRoutes] — les ecrans qui portent la **barre basse**. Ils
///   vivent dans la `ShellRoute` a cinq onglets.
/// * [profileRootRoutes] — les ecrans qui ne la portent pas : lecture d'un
///   rapport, d'une leçon, d'un certificat. Ceux-la « ne doivent pas la faire
///   reapparaitre a mi-parcours » (section 1.4).
///
/// Les chemins se prennent dans `VfRoutes` — jamais ecrits a la main, sinon le
/// jour ou un chemin bouge, les liens des quatre autres parcours meurent en
/// silence.
///
/// Les transitions se prennent dans `VfPageTransitions` : `push` pour un
/// empilement, `pop` pour un retour, `fade` pour un changement d'onglet.
///
/// # Le decoupage retenu pour M5, et pourquoi
///
/// Les SIX ecrans du compte portent la barre basse : le simulateur les annote
/// tous `nav: 'profile'`, et `VfShellScaffold.tabForLocation` reconnait
/// deja les six chemins comme appartenant a l'onglet Profil. Les ranger dans les
/// routes racines aurait fait disparaitre la barre a mi-parcours, exactement ce
/// que la regle interdit.
///
/// L'ecran d'etat `/compte-en-suppression`, lui, est une route RACINE : la barre
/// basse y menerait vers quatre onglets qui repondent tous `403`. Montrer quatre
/// sorties qui ne mènent nulle part est pire que n'en montrer aucune.
library;

import 'package:go_router/go_router.dart';
import 'package:flutter/widgets.dart';

import '../../presentation/features/profile/pages/account_being_deleted_page.dart';
import '../../presentation/features/profile/pages/profile_page.dart';
import '../../presentation/features/profile/pages/referral_page.dart';
import '../../presentation/features/profile/pages/rewards_page.dart';
import '../../presentation/features/profile/pages/settings_page.dart';
import '../../presentation/features/profile/pages/stats_page.dart';
import '../../presentation/features/profile/pages/units_page.dart';
import 'vf_page_transitions.dart';
import 'vf_routes.dart';

/// Ecrans de profile portant la barre basse.
final List<RouteBase> profileShellRoutes = <RouteBase>[
  GoRoute(
    path: VfRoutes.profile,
    name: 'profil',
    // `fade` : l'ecran 25 est la racine d'un onglet, pas un empilement.
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.fade<void>(
          key: state.pageKey,
          name: state.name,
          child: const ProfilePage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.wallet,
    name: 'unites',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const UnitsPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.rewards,
    name: 'recompenses',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const RewardsPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.stats,
    name: 'statistiques',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const StatsPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.referrals,
    name: 'parrainage',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const ReferralPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.settings,
    name: 'reglages',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const SettingsPage(),
        ),
  ),
];

/// Ecrans de profile sans barre basse.
final List<RouteBase> profileRootRoutes = <RouteBase>[
  GoRoute(
    path: VfRoutes.accountBeingDeleted,
    name: 'compte-en-suppression',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.fade<void>(
          key: state.pageKey,
          name: state.name,
          child: const AccountBeingDeletedPage(),
        ),
  ),
];
