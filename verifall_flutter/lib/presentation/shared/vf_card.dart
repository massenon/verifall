import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// `.card` — blanc, filet 1, rayon 16, marge 16 (13 en variante serrée).
///
/// POURQUOI la bordure compte plus que l'ombre : « le produit se tient par ses
/// bordures, pas par ses ombres » (§2.2). Une carte sans filet et avec une ombre
/// marquée est un autre produit.
class VfCard extends StatelessWidget {
  const VfCard({
    required this.child,
    this.tight = false,
    this.onTap,
    this.background = VfColors.surface,
    this.border = VfColors.line,
    this.padding,
    super.key,
  });

  final Widget child;

  /// Variante serrée : marge intérieure de 13 au lieu de 16.
  final bool tight;

  final VoidCallback? onTap;

  /// Variante sombre : `--forest` pour la carte de solde, `--sidebarbg` pour les
  /// encarts du tiroir.
  final Color background;

  final Color? border;

  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) {
    final Widget content = Padding(
      padding:
          padding ?? EdgeInsets.all(tight ? VfSpacing.x12 + 1 : VfSpacing.x16),
      child: child,
    );

    return DecoratedBox(
      decoration: BoxDecoration(
        color: background,
        borderRadius: VfRadii.lgAll,
        border: border == null ? null : Border.all(color: border!),
        boxShadow: VfShadows.card,
      ),
      child: onTap == null
          ? content
          : Material(
              color: Colors.transparent,
              borderRadius: VfRadii.lgAll,
              clipBehavior: Clip.antiAlias,
              child: InkWell(onTap: onTap, child: content),
            ),
    );
  }
}

/// `sectionHead()` — titre `.h-sm` + lien texte primaire à droite, optionnel.
class VfSectionHeader extends StatelessWidget {
  const VfSectionHeader({
    required this.title,
    this.actionLabel,
    this.onAction,
    super.key,
  });

  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            title,
            style: VfTextStyles.hSm,
            // L'en-tete de section est un titre pour le lecteur d'ecran : sans
            // cela, il n'y a aucun repere de structure sur un ecran qui defile.
            semanticsLabel: title,
          ),
        ),
        if (actionLabel != null && onAction != null)
          TextButton(
            onPressed: onAction,
            child: Text(
              actionLabel!,
              style: VfTextStyles.tSm.copyWith(
                color: VfColors.primary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
      ],
    );
  }
}

/// Le sur-titre `.eyebrow` : 10, interlettrage 1.6, capitales, primaire.
///
/// POURQUOI la mise en capitales se fait ici et pas dans la feuille de style :
/// Flutter n'a pas de `text-transform`. La faire au composant évite que chaque
/// écran écrive son propre `toUpperCase()` — ou l'oublie.
class VfEyebrow extends StatelessWidget {
  const VfEyebrow(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text.toUpperCase(),
    style: VfTextStyles.eyebrow,
    // Le lecteur d'ecran recoit le texte d'origine : des capitales sont
    // parfois epelees lettre par lettre.
    semanticsLabel: text,
  );
}
