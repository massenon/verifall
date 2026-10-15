import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// Les cinq entrées de la barre basse, **identiques au prototype web** (§2.5).
///
/// POURQUOI une énumération et pas une liste passée par l'écran : la barre est la
/// même partout. Un écran qui pourrait la composer finirait par en composer une
/// autre.
enum VfTab {
  accueil('Accueil', outlined: Icons.home_outlined, filled: Icons.home_rounded),
  apprendre(
    'Apprendre',
    outlined: Icons.school_outlined,
    filled: Icons.school_rounded,
  ),

  /// Le bouton central. Il n'est pas un onglet comme les autres : c'est un rond
  /// primaire remonté au-dessus de la barre.
  verifier(
    'Vérifier',
    outlined: Icons.search_rounded,
    filled: Icons.search_rounded,
  ),
  communaute(
    'Communauté',
    outlined: Icons.forum_outlined,
    filled: Icons.forum_rounded,
  ),
  profil(
    'Profil',
    outlined: Icons.person_outline_rounded,
    filled: Icons.person_rounded,
  );

  const VfTab(this.label, {required this.outlined, required this.filled});

  final String label;

  /// Contour par défaut.
  final IconData outlined;

  /// Rempli pour l'onglet actif — c'est l'axe `FILL` de Material Symbols
  /// piloté (§2.8).
  final IconData filled;
}

/// `.bottomnav` — cinq entrées, hauteur totale 76.
///
/// POURQUOI ce n'est **pas** un `BottomNavigationBar` : le bouton central
/// déborde vers le haut de 26, et un `BottomNavigationBar` rogne ce qui dépasse.
/// D'où le `Stack` avec `clipBehavior: Clip.none` (§2.7).
class VfBottomNav extends StatelessWidget {
  const VfBottomNav({
    required this.current,
    required this.onSelected,
    super.key,
  });

  final VfTab current;
  final ValueChanged<VfTab> onSelected;

  @override
  Widget build(BuildContext context) {
    final double bottomInset = MediaQuery.paddingOf(context).bottom;

    return Container(
      height: VfChrome.bottomNavHeight + bottomInset,
      padding: EdgeInsets.only(bottom: bottomInset),
      decoration: const BoxDecoration(
        color: VfColors.surface,
        border: Border(top: BorderSide(color: VfColors.line)),
      ),
      child: Stack(
        clipBehavior: Clip.none,
        alignment: Alignment.topCenter,
        children: <Widget>[
          Row(
            children: <Widget>[
              for (final VfTab tab in VfTab.values)
                Expanded(
                  child: tab == VfTab.verifier
                      // La place du bouton central est reservee dans la rangee ;
                      // le bouton lui-meme est pose par-dessus, plus haut.
                      ? const SizedBox.shrink()
                      : _VfNavEntry(
                          tab: tab,
                          active: tab == current,
                          onTap: () => onSelected(tab),
                        ),
                ),
            ],
          ),
          Positioned(
            top: -VfChrome.centerButtonLift,
            child: _VfCenterButton(
              active: current == VfTab.verifier,
              onTap: () => onSelected(VfTab.verifier),
            ),
          ),
        ],
      ),
    );
  }
}

class _VfNavEntry extends StatelessWidget {
  const _VfNavEntry({
    required this.tab,
    required this.active,
    required this.onTap,
  });

  final VfTab tab;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final Color color = active ? VfColors.primary : VfColors.slate2;
    return Semantics(
      button: true,
      selected: active,
      label: tab.label,
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(
            minHeight: VfTouch.navItemMinHeight,
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              Icon(active ? tab.filled : tab.outlined, size: 22, color: color),
              const SizedBox(height: VfSpacing.x4),
              Text(
                tab.label,
                style: VfTextStyles.tXs.copyWith(color: color),
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le bouton central « Vérifier » : 56 x 56, remonté de 26, cerclé de 4 de blanc.
class _VfCenterButton extends StatelessWidget {
  const _VfCenterButton({required this.active, required this.onTap});

  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: active,
      // Le libelle dit CE QUI VA SE PASSER, pas ce que l'icone represente.
      label: 'Vérifier un contenu',
      excludeSemantics: true,
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          width: VfChrome.centerButtonSize + VfChrome.centerButtonRing * 2,
          height: VfChrome.centerButtonSize + VfChrome.centerButtonRing * 2,
          decoration: const BoxDecoration(
            color: VfColors.surface,
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Container(
              width: VfChrome.centerButtonSize,
              height: VfChrome.centerButtonSize,
              decoration: const BoxDecoration(
                color: VfColors.primary,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.search_rounded,
                color: VfColors.surface,
                size: 26,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
