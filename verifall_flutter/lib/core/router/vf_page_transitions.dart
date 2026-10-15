import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import '../theme/vf_motion.dart';

/// Les trois transitions de page, sous forme de `Page` `go_router`.
///
/// `push` pour un empilement, `pop` pour un retour, `fade` pour un changement
/// d'onglet ou un rendu sur place.
abstract final class VfPageTransitions {
  static CustomTransitionPage<T> push<T>({
    required Widget child,
    LocalKey? key,
    String? name,
  }) => _slide<T>(
    child: child,
    key: key,
    name: name,
    beginX: VfMotion.pushOffset,
  );

  static CustomTransitionPage<T> pop<T>({
    required Widget child,
    LocalKey? key,
    String? name,
  }) =>
      _slide<T>(child: child, key: key, name: name, beginX: VfMotion.popOffset);

  static CustomTransitionPage<T> fade<T>({
    required Widget child,
    LocalKey? key,
    String? name,
  }) {
    return CustomTransitionPage<T>(
      key: key,
      name: name,
      child: child,
      transitionDuration: VfMotion.fade,
      reverseTransitionDuration: VfMotion.fade,
      transitionsBuilder:
          (
            BuildContext context,
            Animation<double> animation,
            Animation<double> secondary,
            Widget child,
          ) {
            if (VfMotion.reducedMotion(context)) {
              return child;
            }
            return FadeTransition(
              opacity: animation,
              child: Transform.translate(
                offset: Offset(0, VfMotion.fadeOffset * (1 - animation.value)),
                child: child,
              ),
            );
          },
    );
  }

  static CustomTransitionPage<T> _slide<T>({
    required Widget child,
    required double beginX,
    LocalKey? key,
    String? name,
  }) {
    return CustomTransitionPage<T>(
      key: key,
      name: name,
      child: child,
      transitionDuration: VfMotion.navigation,
      reverseTransitionDuration: VfMotion.navigation,
      transitionsBuilder:
          (
            BuildContext context,
            Animation<double> animation,
            Animation<double> secondary,
            Widget child,
          ) {
            if (VfMotion.reducedMotion(context)) {
              return child;
            }
            final Animation<double> eased = CurvedAnimation(
              parent: animation,
              curve: VfMotion.curve,
            );
            return FadeTransition(
              opacity: eased,
              child: AnimatedBuilder(
                animation: eased,
                builder: (BuildContext context, Widget? inner) =>
                    Transform.translate(
                      offset: Offset(beginX * (1 - eased.value), 0),
                      child: inner,
                    ),
                child: child,
              ),
            );
          },
    );
  }
}
