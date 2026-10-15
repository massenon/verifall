/// Routes du parcours M4 - Hub et forum (community).
///
/// # Pourquoi ce fichier existe
///
/// `app_router.dart` et `database.dart` sont les deux seuls endroits ou les cinq
/// parcours devraient normalement ecrire tous ensemble. On l'evite ainsi : chaque
/// parcours ecrit **son propre fichier** de routes, l'agregateur importe les six
/// **des le socle**, et personne ne rouvre l'agregateur. Les cinq branches se
/// fusionnent sans conflit (section 8.0).
///
/// # Le partage entre les deux listes n'est pas cosmetique
///
/// * [communityShellRoutes] — les ecrans qui portent la **barre basse** :
///   le forum et le Hub. Ce sont les deux entrees de l'onglet << Communaute >>,
///   et le simulateur les dessine avec la barre.
/// * [communityRootRoutes] — la lecture d'un sujet, d'une offre, d'un article.
///   Le simulateur ne dessine PAS la barre basse sur ces trois ecrans, et la
///   regle du socle est explicite : ceux qui ne la portent pas << ne doivent pas
///   la faire reapparaitre a mi-parcours >> (section 1.4).
///
/// # Les trois chemins portent un SLUG
///
/// `VfRoutes` les declare ainsi, et le contrat d'API a tranche : sujet, breve et
/// opportunite sont identifies par un slug, jamais par un entier (section 4 du
/// contrat, reserve A.8). Un entier sequentiel dans une URL revele le volume et
/// invite a l'enumeration — or ces liens sont partages sur WhatsApp.
///
/// # Le lien profond `verifall://hub/{slug}`
///
/// Il est resolu par `VfDeepLinks` du socle, qui le traduit en
/// `/hub-infos/{slug}`. C'est pourquoi l'article se trouve ici et pas ailleurs :
/// `go_router` doit le resoudre **application fermee**, et retomber sur le repli
/// de `ArticlePage` si le contenu a ete depublie.
library;

import 'package:go_router/go_router.dart';

import '../../presentation/features/community/pages/article_page.dart';
import '../../presentation/features/community/pages/forum_page.dart';
import '../../presentation/features/community/pages/hub_page.dart';
import '../../presentation/features/community/pages/opportunity_page.dart';
import '../../presentation/features/community/pages/thread_page.dart';
import 'vf_page_transitions.dart';
import 'vf_routes.dart';

/// Ecrans de community portant la barre basse.
final List<RouteBase> communityShellRoutes = <RouteBase>[
  GoRoute(
    path: VfRoutes.forum,
    name: 'forum',
    // `fade` : un changement d'onglet, pas un empilement.
    pageBuilder: (_, GoRouterState state) => VfPageTransitions.fade<void>(
      key: state.pageKey,
      name: state.name,
      child: const ForumPage(),
    ),
  ),
  GoRoute(
    path: VfRoutes.hub,
    name: 'hub',
    pageBuilder: (_, GoRouterState state) => VfPageTransitions.fade<void>(
      key: state.pageKey,
      name: state.name,
      child: const HubPage(),
    ),
  ),
];

/// Ecrans de community sans barre basse.
final List<RouteBase> communityRootRoutes = <RouteBase>[
  GoRoute(
    path: '${VfRoutes.forum}/:slug',
    name: 'forum-sujet',
    pageBuilder: (_, GoRouterState state) => VfPageTransitions.push<void>(
      key: state.pageKey,
      name: state.name,
      child: ThreadPage(slug: state.pathParameters['slug'] ?? ''),
    ),
  ),
  GoRoute(
    path: '${VfRoutes.hub}/opportunites/:slug',
    name: 'hub-opportunite',
    pageBuilder: (_, GoRouterState state) => VfPageTransitions.push<void>(
      key: state.pageKey,
      name: state.name,
      child: OpportunityPage(slug: state.pathParameters['slug'] ?? ''),
    ),
  ),
  GoRoute(
    path: '/hub-infos/:slug',
    name: 'hub-article',
    pageBuilder: (_, GoRouterState state) => VfPageTransitions.push<void>(
      key: state.pageKey,
      name: state.name,
      child: ArticlePage(slug: state.pathParameters['slug'] ?? ''),
    ),
  ),
];
