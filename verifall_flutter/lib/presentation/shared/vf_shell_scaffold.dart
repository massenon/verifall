import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/router/vf_routes.dart';
import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';
import '../../data/datasources/local/pending_ops.dart';
import 'vf_app_drawer.dart';
import 'vf_bottom_nav.dart';

/// La coque des cinq onglets.
///
/// POURQUOI elle appartient au socle : la barre basse est identique sur les cinq
/// onglets, et « un composant partagé qui manque ne se crée pas dans un
/// parcours » (§8.1, règle 3). Si chacun des cinq posait la sienne, elles
/// diverge­raient en une semaine.
///
/// Elle ne contient **aucun écran** : `child` est la page rendue par la route,
/// et c'est le parcours propriétaire qui l'écrit.
class VfShellScaffold extends ConsumerStatefulWidget {
  const VfShellScaffold({required this.child, super.key});

  final Widget child;

  @override
  ConsumerState<VfShellScaffold> createState() => _VfShellScaffoldState();

  /// L'onglet courant, déduit du chemin.
  ///
  /// POURQUOI la déduction se fait ici et pas dans un provider d'état : l'URL
  /// **est** l'état de navigation. Un second état à synchroniser finit toujours
  /// par diverger le jour d'un lien profond.
  static VfTab tabForLocation(String location) {
    if (location.startsWith(VfRoutes.catalogue) ||
        location.startsWith('/cours') ||
        location.startsWith(VfRoutes.liveClasses) ||
        location.startsWith('/certificat')) {
      return VfTab.apprendre;
    }
    if (location.startsWith(VfRoutes.verify) ||
        location.startsWith('/verification') ||
        location.startsWith('/rapport')) {
      return VfTab.verifier;
    }
    if (location.startsWith(VfRoutes.forum) ||
        location.startsWith(VfRoutes.hub) ||
        location.startsWith('/hub-infos')) {
      return VfTab.communaute;
    }
    if (location.startsWith(VfRoutes.profile) ||
        location.startsWith(VfRoutes.wallet) ||
        location.startsWith(VfRoutes.rewards) ||
        location.startsWith(VfRoutes.stats) ||
        location.startsWith(VfRoutes.referrals) ||
        location.startsWith(VfRoutes.settings)) {
      return VfTab.profil;
    }
    return VfTab.accueil;
  }

  static String pathForTab(VfTab tab) => switch (tab) {
    VfTab.accueil => VfRoutes.dashboard,
    VfTab.apprendre => VfRoutes.catalogue,
    VfTab.verifier => VfRoutes.verify,
    VfTab.communaute => VfRoutes.forum,
    VfTab.profil => VfRoutes.profile,
  };
}

class _VfShellScaffoldState extends ConsumerState<VfShellScaffold> {
  /// POURQUOI une cle et pas `Scaffold.of` : plusieurs ecrans de parcours posent
  /// **leur propre** `Scaffold` sous celui-ci. `Scaffold.of` depuis leur barre
  /// superieure trouverait le leur, qui n'a pas de tiroir — et le bouton
  /// n'ouvrirait rien, en silence. La cle designe le seul qui en porte un.
  final GlobalKey<ScaffoldState> _scaffold = GlobalKey<ScaffoldState>();

  @override
  Widget build(BuildContext context) {
    final String location = GoRouterState.of(context).uri.path;
    final VfTab current = VfShellScaffold.tabForLocation(location);

    return VfShellScope(
      openDrawer: () => _scaffold.currentState?.openDrawer(),
      child: Scaffold(
        key: _scaffold,
        backgroundColor: VfColors.canvas,
        // L'HOTE DU TIROIR. Il vit ici et pas dans un parcours : trois d'entre
        // eux posent un bouton de tiroir, et l'assembler dans l'un aurait
        // duplique le menu dans les deux autres (arbitrage n° 137).
        drawer: const VfAppDrawer(),
        body: Column(
          children: <Widget>[
            const _PendingOpsBanner(),
            Expanded(child: widget.child),
          ],
        ),
        bottomNavigationBar: VfBottomNav(
          current: current,
          onSelected: (VfTab tab) =>
              context.go(VfShellScaffold.pathForTab(tab)),
        ),
      ),
    );
  }
}

/// Le point d'ouverture du tiroir, accessible de **tout** l'arbre de la coque.
///
/// POURQUOI il existe : le bouton de tiroir vit dans la barre superieure d'un
/// ecran, souvent sous un `Scaffold` propre a cet ecran. Sans cette portee, il
/// ne trouverait que ce `Scaffold`-la — celui qui n'a pas de tiroir.
class VfShellScope extends InheritedWidget {
  const VfShellScope({
    required this.openDrawer,
    required super.child,
    super.key,
  });

  final VoidCallback openDrawer;

  /// Ouvre le tiroir de la coque, s'il y en a une.
  ///
  /// Rend `false` quand l'ecran n'est pas dans la coque — un rapport, une lecon,
  /// un certificat. C'est ce qui permet a l'appelant de ne pas poser un bouton
  /// qui n'ouvrirait rien.
  static bool openDrawerFrom(BuildContext context) {
    final VfShellScope? scope = context
        .getInheritedWidgetOfExactType<VfShellScope>();
    if (scope == null) {
      return false;
    }
    scope.openDrawer();
    return true;
  }

  static bool isInsideShell(BuildContext context) =>
      context.getInheritedWidgetOfExactType<VfShellScope>() != null;

  @override
  bool updateShouldNotify(VfShellScope oldWidget) => false;
}

/// Le bandeau « `<n>` action(s) en attente d'envoi ».
///
/// POURQUOI il est **permanent** tant que la file n'est pas vide : « ne jamais
/// faire croire qu'une écriture a abouti » (05-HORS-LIGNE §5.7). Un bandeau qui
/// disparaît au bout de trois secondes laisse penser que c'est parti.
class _PendingOpsBanner extends ConsumerWidget {
  const _PendingOpsBanner();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final int count = ref.watch(pendingOpsCountProvider).value ?? 0;
    if (count == 0) {
      return const SizedBox.shrink();
    }

    return Material(
      color: VfColors.primarySoft,
      child: InkWell(
        // Le detail — reessayer, annuler operation par operation — appartient a
        // l'ecran qui le dessinera. Le socle porte le bandeau, pas la feuille.
        onTap: () => ref.read(pendingOpsQueueProvider).replayAll(),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: VfSpacing.x16,
            vertical: VfSpacing.x8,
          ),
          child: Row(
            children: <Widget>[
              const Icon(
                Icons.cloud_upload_outlined,
                size: 18,
                color: VfColors.primaryDark,
              ),
              const SizedBox(width: VfSpacing.x8),
              Expanded(
                child: Text(
                  count == 1
                      ? '1 action en attente d envoi'
                      : '$count actions en attente d envoi',
                  style: VfTextStyles.tSm.copyWith(color: VfColors.primaryDark),
                ),
              ),
              Text(
                'Réessayer',
                style: VfTextStyles.tSm.copyWith(
                  color: VfColors.primaryDark,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
