import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// Les variantes de bouton du design system (§2.7, « Actions »).
enum VfButtonVariant {
  /// `.btn-primary` — fond primaire, texte blanc.
  primary,

  /// `.btn-red` — fond rouge de marque, ombre portée rouge.
  danger,

  /// `.btn-ink` — fond encre.
  ink,

  /// `.btn-ghost` — fond blanc, filet gris, texte encre.
  ghost,

  /// `.btn-outline` — fond transparent, filet et texte primaires.
  outline,

  /// Vert WhatsApp — **réservé aux boutons de partage**.
  ///
  /// POURQUOI cette variante existe alors que le design system n'a pas de
  /// couleur par instance : deux parcours avaient redessiné le même bouton vert
  /// à la main, avec le même jeton `VfBrandColors.whatsapp` — M4 au bas d'un
  /// article, M5 sur l'invitation de parrainage (arbitrages n° 92 et 116). Deux
  /// implémentations du même bouton, c'est le socle (arbitrage n° 139).
  ///
  /// POURQUOI une variante **nommée** plutôt qu'un paramètre de couleur libre :
  /// « ce sont des logos, pas des jetons », et le socle range d'ailleurs cette
  /// teinte dans `VfBrandColors`. Une couleur libre autoriserait n'importe quel
  /// bouton à sortir de la palette ; une variante nommée n'autorise que
  /// celui-ci.
  whatsapp,
}

/// Le bouton du design system.
///
/// # Deux règles qui viennent du simulateur, et qui se testent
///
/// - **82 % d'opacité au contact, 45 % désactivé.**
/// - **Un bouton désactivé ne réagit pas au toucher, mais il reste lisible** :
///   le simulateur ne le masque jamais. Un bouton qui disparaît fait chercher
///   une fonction qu'on croit perdue ; un bouton grisé fait comprendre qu'il
///   manque quelque chose.
///
/// POURQUOI la hauteur est un **minimum** et pas une hauteur fixe : à 200 % de
/// taille de texte, une hauteur fixe coupe le libellé (§2.3).
///
/// # [expanded] ne peut PAS forcer une largeur infinie
///
/// Le défaut est `true`, et c'est le bon défaut : la quasi-totalité des boutons
/// du simulateur occupe toute la largeur. Mais tel qu'il était écrit, il posait
/// `width: double.infinity` sans regarder les contraintes reçues — et **deux de
/// ces boutons dans une `Row` produisaient une mise en page infinie**, c'est-à-dire
/// un plantage à l'exécution que `flutter analyze` ne voit pas. M3 l'a rencontré
/// et l'a contourné par des `Expanded` (arbitrage n° 105).
///
/// La cause est corrigée ici : [expanded] signifie désormais « prends toute la
/// largeur **disponible** », et là où il n'y en a pas de bornée — une `Row`, une
/// `Wrap`, un `ListView` horizontal — le bouton prend sa largeur naturelle. Un
/// bouton un peu plus étroit vaut mieux qu'un écran blanc, et le `Expanded` de
/// qui en voulait un large continue de fonctionner à l'identique.
///
/// Voir `_RemplitSiBorne` en bas de fichier pour le comment, et l'arbitrage
/// n° 133 pour le pourquoi.
class VfButton extends StatefulWidget {
  const VfButton({
    required this.label,
    required this.onPressed,
    this.variant = VfButtonVariant.primary,
    this.icon,
    this.expanded = true,
    this.busy = false,
    this.semanticsLabel,
    super.key,
  });

  final String label;

  /// `null` désactive le bouton. C'est le seul moyen de le désactiver : pas de
  /// booléen `enabled` séparé qui pourrait contredire le rappel.
  final VoidCallback? onPressed;

  final VfButtonVariant variant;
  final IconData? icon;
  final bool expanded;

  /// Pendant un envoi. Le bouton reste visible et annonce son état.
  final bool busy;

  /// Ce qui va se passer, pour le lecteur d'écran — pas ce que le bouton
  /// représente (§2.9).
  final String? semanticsLabel;

  @override
  State<VfButton> createState() => _VfButtonState();
}

class _VfButtonState extends State<VfButton> {
  bool _pressed = false;

  bool get _enabled => widget.onPressed != null && !widget.busy;

  @override
  Widget build(BuildContext context) {
    final _VfButtonSkin skin = _VfButtonSkin.of(widget.variant);
    final double opacity = !_enabled
        ? 0.45
        : _pressed
        ? 0.82
        : 1.0;

    final Widget content = Row(
      // TOUJOURS `min` : c'est `_RemplitSiBorne` qui elargit, et lui sait si la
      // largeur recue est bornee. Un `max` ici forcerait l'infini dans une `Row`
      // sans contrainte — le defaut que cette classe corrige.
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        if (widget.busy)
          Padding(
            padding: const EdgeInsets.only(right: VfSpacing.x8),
            child: SizedBox.square(
              dimension: 16,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: skin.foreground,
              ),
            ),
          )
        else if (widget.icon != null)
          Padding(
            padding: const EdgeInsets.only(right: VfSpacing.x8),
            // L'icone d'un bouton est decorative : le libelle porte deja le sens.
            child: ExcludeSemantics(child: Icon(widget.icon, size: 18)),
          ),
        Flexible(
          child: Text(
            widget.label,
            textAlign: TextAlign.center,
            overflow: TextOverflow.ellipsis,
            style: VfTextStyles.button.copyWith(color: skin.foreground),
          ),
        ),
      ],
    );

    final Widget bouton = Semantics(
      button: true,
      enabled: _enabled,
      label: widget.semanticsLabel,
      child: GestureDetector(
        onTapDown: _enabled ? (_) => setState(() => _pressed = true) : null,
        onTapUp: _enabled ? (_) => setState(() => _pressed = false) : null,
        onTapCancel: _enabled ? () => setState(() => _pressed = false) : null,
        onTap: _enabled ? widget.onPressed : null,
        child: Opacity(
          opacity: opacity,
          child: Container(
            constraints: const BoxConstraints(
              minHeight: VfTouch.buttonMinHeight,
            ),
            padding: const EdgeInsets.symmetric(
              horizontal: VfTouch.buttonInlinePadding,
              vertical: VfSpacing.x12,
            ),
            decoration: BoxDecoration(
              color: skin.background,
              borderRadius: VfRadii.mdAll,
              border: skin.border == null
                  ? null
                  : Border.all(color: skin.border!),
              boxShadow: _enabled ? skin.shadow : null,
            ),
            child: content,
          ),
        ),
      ),
    );

    return widget.expanded ? _RemplitSiBorne(child: bouton) : bouton;
  }
}

/// Prend **toute la largeur disponible**, et la largeur naturelle quand il n'y
/// en a pas de bornée.
///
/// # Pourquoi un objet de rendu, et pas un `LayoutBuilder`
///
/// La première correction employait un `LayoutBuilder` : il donne bien la
/// réponse, mais **il interdit les dimensions intrinsèques** à tout ce qui
/// l'entoure. L'écran de connexion de M1 pose un `IntrinsicHeight` autour de ses
/// boutons, et quatorze tests sont tombés d'un coup — ce qui est exactement ce
/// qu'on attend d'une suite de tests.
///
/// Un [RenderProxyBox] n'a pas ce défaut : il délègue ses intrinsèques à son
/// enfant, et ne décide qu'une chose — resserrer la largeur quand elle est
/// bornée, laisser passer les contraintes quand elle ne l'est pas.
class _RemplitSiBorne extends SingleChildRenderObjectWidget {
  const _RemplitSiBorne({required Widget super.child});

  @override
  RenderObject createRenderObject(BuildContext context) =>
      _RenderRemplitSiBorne();
}

class _RenderRemplitSiBorne extends RenderProxyBox {
  static BoxConstraints _pour(BoxConstraints contraintes) =>
      contraintes.hasBoundedWidth
      ? contraintes.tighten(width: contraintes.maxWidth)
      : contraintes;

  @override
  void performLayout() {
    final RenderBox? enfant = child;
    if (enfant == null) {
      size = constraints.smallest;
      return;
    }
    enfant.layout(_pour(constraints), parentUsesSize: true);
    size = enfant.size;
  }

  @override
  Size computeDryLayout(BoxConstraints constraints) =>
      child?.getDryLayout(_pour(constraints)) ?? constraints.smallest;
}

/// Le bouton principal : fond primaire, texte blanc.
class VfPrimaryButton extends StatelessWidget {
  const VfPrimaryButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.expanded = true,
    this.busy = false,
    this.semanticsLabel,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;
  final bool busy;
  final String? semanticsLabel;

  @override
  Widget build(BuildContext context) => VfButton(
    label: label,
    onPressed: onPressed,
    icon: icon,
    expanded: expanded,
    busy: busy,
    semanticsLabel: semanticsLabel,
  );
}

/// Le bouton de danger : rouge de marque, ombre portée rouge.
///
/// Employé par le bouton « Vérifier » et par les zones de danger — ce sont deux
/// intentions différentes servies par la même couleur, et c'est le prototype qui
/// en décide, pas ce composant.
class VfDangerButton extends StatelessWidget {
  const VfDangerButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.expanded = true,
    this.busy = false,
    this.semanticsLabel,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;
  final bool busy;
  final String? semanticsLabel;

  @override
  Widget build(BuildContext context) => VfButton(
    label: label,
    onPressed: onPressed,
    variant: VfButtonVariant.danger,
    icon: icon,
    expanded: expanded,
    busy: busy,
    semanticsLabel: semanticsLabel,
  );
}

/// Le bouton encre.
class VfInkButton extends StatelessWidget {
  const VfInkButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.expanded = true,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;

  @override
  Widget build(BuildContext context) => VfButton(
    label: label,
    onPressed: onPressed,
    variant: VfButtonVariant.ink,
    icon: icon,
    expanded: expanded,
  );
}

/// Le bouton fantôme : fond blanc, filet gris, texte encre.
class VfGhostButton extends StatelessWidget {
  const VfGhostButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.expanded = true,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;

  @override
  Widget build(BuildContext context) => VfButton(
    label: label,
    onPressed: onPressed,
    variant: VfButtonVariant.ghost,
    icon: icon,
    expanded: expanded,
  );
}

/// Le bouton cerclé : fond transparent, filet et texte primaires.
class VfOutlinedButton extends StatelessWidget {
  const VfOutlinedButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.expanded = true,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;

  @override
  Widget build(BuildContext context) => VfButton(
    label: label,
    onPressed: onPressed,
    variant: VfButtonVariant.outline,
    icon: icon,
    expanded: expanded,
  );
}

/// Le bouton de partage WhatsApp — vert de marque, texte blanc.
///
/// # Ce qu'il ouvre, et ce qu'il n'ouvre pas
///
/// Les deux parcours qui l'emploient ouvrent **la feuille de partage du
/// système**, pas WhatsApp directement, et pour la même raison : « ouvrir
/// l'application exigerait un schéma d'URL que ni le contrat ni le simulateur ne
/// fournissent, et un bouton qui échoue sur un téléphone sans WhatsApp serait
/// pire que le détour d'un choix ». Ce composant ne décide pas de la cible : il
/// rend le rappel qu'on lui donne.
class VfWhatsAppButton extends StatelessWidget {
  const VfWhatsAppButton({
    required this.label,
    required this.onPressed,
    this.semanticsLabel,
    this.busy = false,
    this.expanded = true,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final String? semanticsLabel;
  final bool busy;
  final bool expanded;

  @override
  Widget build(BuildContext context) => VfButton(
    label: label,
    onPressed: onPressed,
    variant: VfButtonVariant.whatsapp,
    busy: busy,
    expanded: expanded,
    semanticsLabel: semanticsLabel ?? label,
  );
}

/// `.iconbtn` — 44 x 44, rond, pastille rouge optionnelle.
///
/// POURQUOI [tooltip] est obligatoire : « tout bouton-icône a un libellé qui dit
/// **ce qui va se passer**, pas ce qu'il représente » (§2.9). Une cloche ne dit
/// pas « ouvrir les notifications » à un lecteur d'écran.
class VfIconButton extends StatelessWidget {
  const VfIconButton({
    required this.icon,
    required this.tooltip,
    required this.onPressed,
    this.badge = false,
    this.foreground = VfColors.ink,
    super.key,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;

  /// La pastille rouge de notification non lue.
  final bool badge;

  /// Blanc en variante `on-forest`.
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: tooltip,
      child: SizedBox.square(
        dimension: VfTouch.iconButtonSize,
        child: Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
            Positioned.fill(
              child: Material(
                color: Colors.transparent,
                shape: const CircleBorder(),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: onPressed,
                  child: Icon(icon, size: 22, color: foreground),
                ),
              ),
            ),
            if (badge)
              const Positioned(
                right: 8,
                top: 8,
                child: ExcludeSemantics(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: VfColors.brandRed,
                      shape: BoxShape.circle,
                    ),
                    child: SizedBox.square(dimension: 8),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// La peau d'une variante : couleurs et ombre.
@immutable
class _VfButtonSkin {
  const _VfButtonSkin({
    required this.background,
    required this.foreground,
    this.border,
    this.shadow,
  });

  final Color background;
  final Color foreground;
  final Color? border;
  final List<BoxShadow>? shadow;

  static _VfButtonSkin of(VfButtonVariant variant) => switch (variant) {
    VfButtonVariant.primary => const _VfButtonSkin(
      background: VfColors.primary,
      foreground: VfColors.surface,
    ),
    VfButtonVariant.danger => const _VfButtonSkin(
      background: VfColors.brandRed,
      foreground: VfColors.surface,
      shadow: VfShadows.dangerButton,
    ),
    VfButtonVariant.ink => const _VfButtonSkin(
      background: VfColors.ink,
      foreground: VfColors.surface,
    ),
    VfButtonVariant.ghost => const _VfButtonSkin(
      background: VfColors.surface,
      foreground: VfColors.ink,
      border: VfColors.line,
    ),
    VfButtonVariant.outline => const _VfButtonSkin(
      background: Color(0x00000000),
      foreground: VfColors.primary,
      border: VfColors.primary,
    ),
    VfButtonVariant.whatsapp => const _VfButtonSkin(
      background: VfBrandColors.whatsapp,
      foreground: VfColors.surface,
    ),
  };
}
