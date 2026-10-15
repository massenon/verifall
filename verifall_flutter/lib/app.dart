import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/config/app_config.dart';
import 'core/config/config_providers.dart';
import 'core/logging/app_logger.dart';
import 'core/logging/logging_providers.dart';
import 'core/network/auth_controller.dart';
import 'core/router/app_router.dart';
import 'core/theme/vf_colors.dart';
import 'core/theme/vf_theme.dart';
import 'core/theme/vf_typography.dart';
import 'data/datasources/local/pending_ops.dart';

/// La racine de l'application.
///
/// Elle ne fait que trois choses, et elle n'en fera jamais plus : poser le
/// thème, brancher le routeur, et suivre le cycle de vie.
class VerifAllApp extends ConsumerStatefulWidget {
  const VerifAllApp({super.key});

  @override
  ConsumerState<VerifAllApp> createState() => _VerifAllAppState();
}

class _VerifAllAppState extends ConsumerState<VerifAllApp>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // La restauration de session est lancee apres la premiere image : lire le
    // stockage securise avant de peindre allongerait le demarrage sans rien
    // gagner, puisque l'etat « inconnu » evite deja le clignotement.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(authControllerProvider.notifier).restore();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    ref
        .read(appLoggerProvider)
        .debug(LogChannel.lifecycle, 'Cycle de vie : ${state.name}');

    if (state == AppLifecycleState.resumed) {
      // Le retour au premier plan est l'un des trois declencheurs du rejeu de
      // la file (05-HORS-LIGNE 5.7). C'est aussi le moment ou le solde d'unites
      // doit etre relu — mais le solde appartient a M5, pas au socle.
      ref.read(pendingOpsQueueProvider).replayAll();
    }
  }

  @override
  Widget build(BuildContext context) {
    final GoRouter router = ref.watch(appRouterProvider);
    final AppConfig config = ref.watch(appConfigProvider);

    return MaterialApp.router(
      title: 'Vérif All',
      debugShowCheckedModeBanner: false,
      theme: VfTheme.light(),
      // « L'application reste en theme clair, conforme au DS » (2). Fixer
      // `themeMode` empeche le systeme d'imposer une palette sombre qui
      // n'existe pas.
      themeMode: ThemeMode.light,
      routerConfig: router,
      builder: (BuildContext context, Widget? child) {
        final Widget scaled = MediaQuery.withClampedTextScaling(
          maxScaleFactor: VfTheme.maxTextScaleFactor,
          child: child ?? const SizedBox.shrink(),
        );
        // Le bandeau de session de recette. Il n'existe QUE hors production —
        // `hasUsableDevSession` ne peut pas etre vrai autrement — et il est la
        // pour qu'une session de recette ne soit jamais prise pour une vraie.
        return config.hasUsableDevSession
            ? _DeveloperSessionRibbon(child: scaled)
            : scaled;
      },
    );
  }
}

/// Le repère visuel de la session de développement.
///
/// POURQUOI il est voyant : une capture d'écran envoyée par un testeur, prise
/// sur un compte de recette et prise pour un compte réel, fait chercher un
/// défaut qui n'existe pas.
class _DeveloperSessionRibbon extends StatelessWidget {
  const _DeveloperSessionRibbon({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.ltr,
      child: Stack(
        children: <Widget>[
          child,
          Positioned(
            top: MediaQuery.paddingOf(context).top,
            right: 0,
            child: IgnorePointer(
              child: Container(
                color: VfColors.xp,
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                child: Text(
                  'SESSION DE RECETTE',
                  style: VfTextStyles.tXs.copyWith(
                    color: VfColors.surface,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
