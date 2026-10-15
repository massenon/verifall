import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_theme.dart';
import '../../core/theme/vf_typography.dart';
import 'vf_buttons.dart';
import 'vf_shell_scaffold.dart';

/// Le bouton de gauche de la barre supérieure : **l'un ou l'autre, jamais les
/// deux** (§2.5).
enum VfAppBarLeading { retour, tiroir, aucun }

/// `.appbar` — hauteur `zone sûre + 52`, fond blanc, filet bas.
///
/// # L'ordre des actions est imposé
///
/// À droite, dans cet ordre : **la pastille de points (unités) puis la cloche de
/// notifications**. Ce n'est pas une préférence — le README du simulateur le
/// consigne comme un alignement fait exprès sur le prototype web mis à jour, et
/// l'inverser recréerait l'écart qui vient d'être corrigé (§2.5).
///
/// [pointsPill] est donc un paramètre **distinct** de [actions] : le mettre dans
/// la liste laisserait un écran le poser au mauvais endroit.
///
/// # La variante `on-forest`
///
/// Fond vert profond, texte et icônes blancs, pastille translucide. Elle n'est
/// employée par aucun écran du simulateur : « c'est une capacité du composant,
/// pas un état actuel ». Ne pas l'utiliser sans une maquette qui la demande.
class VfAppBar extends StatelessWidget implements PreferredSizeWidget {
  const VfAppBar({
    this.title,
    this.leading = VfAppBarLeading.retour,
    this.onLeadingPressed,
    this.pointsPill,
    this.actions = const <Widget>[],
    this.onForest = false,
    super.key,
  });

  final String? title;
  final VfAppBarLeading leading;
  final VoidCallback? onLeadingPressed;

  /// La pastille de points, posée **avant** les actions.
  final Widget? pointsPill;

  final List<Widget> actions;
  final bool onForest;

  @override
  Size get preferredSize => const Size.fromHeight(VfChrome.appBarHeight);

  @override
  Widget build(BuildContext context) {
    final Color foreground = onForest ? VfColors.surface : VfColors.ink;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // Barre d'etat claire sur fond clair, blanche sur fond sombre. En Flutter
      // cela se pilote par `SystemUiOverlayStyle`, et il faut le REMETTRE en
      // fermant une superposition — un oubli laisse des icones blanches sur
      // fond blanc (§2.5).
      value: onForest ? VfTheme.statusBarOnDark : VfTheme.statusBarOnLight,
      child: Container(
        padding: EdgeInsets.only(top: MediaQuery.paddingOf(context).top),
        decoration: BoxDecoration(
          color: onForest ? VfColors.forest : VfColors.surface,
          border: Border(
            bottom: BorderSide(
              color: onForest
                  ? VfColors.surface.withValues(alpha: 0.14)
                  : VfColors.line,
            ),
          ),
        ),
        child: SizedBox(
          height: VfChrome.appBarHeight,
          child: Row(
            children: <Widget>[
              const SizedBox(width: VfSpacing.x4),
              _leading(context, foreground),
              Expanded(
                child: title == null
                    ? const SizedBox.shrink()
                    : Text(
                        title!,
                        style: VfTextStyles.hMd.copyWith(color: foreground),
                        overflow: TextOverflow.ellipsis,
                      ),
              ),
              if (pointsPill != null) ...<Widget>[
                pointsPill!,
                const SizedBox(width: VfSpacing.x8),
              ],
              ...actions,
              const SizedBox(width: VfSpacing.x4),
            ],
          ),
        ),
      ),
    );
  }

  Widget _leading(BuildContext context, Color foreground) {
    return switch (leading) {
      VfAppBarLeading.aucun => const SizedBox(width: VfSpacing.x12),
      VfAppBarLeading.retour => VfIconButton(
        icon: Icons.arrow_back_rounded,
        tooltip: 'Revenir a l ecran précédent',
        foreground: foreground,
        onPressed: onLeadingPressed ?? () => Navigator.of(context).maybePop(),
      ),
      VfAppBarLeading.tiroir => VfIconButton(
        icon: Icons.menu_rounded,
        tooltip: 'Ouvrir le menu',
        foreground: foreground,
        // PAS `Scaffold.of` : plusieurs ecrans posent leur propre `Scaffold`
        // sous la coque, et celui-la n'a pas de tiroir — le bouton n'ouvrirait
        // rien, en silence. `VfShellScope` designe celui qui en porte un
        // (arbitrage n° 137).
        onPressed:
            onLeadingPressed ?? () => VfShellScope.openDrawerFrom(context),
      ),
    };
  }
}
