import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../shared/shared.dart';

/// Les petits assemblages propres aux six écrans d'authentification.
///
/// # Pourquoi ils ne s'appellent pas `Vf…`
///
/// Le préfixe `Vf` désigne `presentation/shared/` — le socle. Ces widgets-ci
/// n'existent que dans M1 : le simulateur ne les emploie sur aucun autre écran
/// (`VfInfoCard` et `VfIconBadge` n'apparaissent que sur les écrans 04, 05 et
/// 06). Les nommer `Vf…` laisserait croire qu'un autre parcours peut s'en
/// servir, et « un composant dupliqué dans deux dossiers diverge en une
/// semaine ».
///
/// # Pourquoi ils composent `VfCard` au lieu de redessiner une carte
///
/// L'encart d'information du simulateur est littéralement une `.card tight`
/// avec une icône et un paragraphe. Le redessiner aurait produit une deuxième
/// définition du filet, du rayon et de la marge — c'est-à-dire une dérive.

/// Le carré de 52 à rayon 16, fond vert clair, icône en vert foncé.
///
/// Écrans 04 (`lock_reset`) et 05 (`mark_email_read`).
class AuthIconBadge extends StatelessWidget {
  const AuthIconBadge({required this.icon, super.key});

  final IconData icon;

  static const double taille = 52;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: taille,
      height: taille,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: VfColors.primarySoft,
        borderRadius: BorderRadius.circular(VfRadii.lg),
      ),
      // L'icone est purement decorative : le titre qui suit dit la meme chose,
      // et un lecteur d'ecran qui annoncerait « enveloppe » avant « Confirmez
      // votre adresse » ferait entendre deux fois la meme information.
      child: ExcludeSemantics(
        child: Icon(icon, size: 26, color: VfColors.primaryDark),
      ),
    );
  }
}

/// L'encart d'information : icône colorée à gauche, phrase à droite.
///
/// Trois occurrences, trois couleurs, et elles ne sont pas interchangeables :
/// bleu pour une explication (écran 04), vert pour une consigne de sécurité
/// (écran 05), orange pour une conséquence (écran 06).
class AuthInfoCard extends StatelessWidget {
  const AuthInfoCard({
    required this.icon,
    required this.texte,
    this.accent = VfColors.info,
    this.surFondVert = false,
    super.key,
  });

  final IconData icon;
  final String texte;
  final Color accent;

  /// La variante vert clair de l'écran 05.
  final bool surFondVert;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      background: surFondVert ? VfColors.primarySoft : VfColors.surface,
      border: surFondVert
          ? VfColors.primary.withValues(alpha: 0.25)
          : VfColors.line,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          ExcludeSemantics(child: Icon(icon, size: 19, color: accent)),
          const SizedBox(width: VfSpacing.x9),
          Expanded(
            child: Text(
              texte,
              style: VfTextStyles.tXs.copyWith(
                color: surFondVert ? VfColors.primaryDark : VfColors.slate2,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// L'en-tête de marque de l'écran 02 : le carré vert au **V**, le titre, la
/// phrase.
class AuthBrandHeader extends StatelessWidget {
  const AuthBrandHeader({required this.titre, required this.phrase, super.key});

  final String titre;
  final String phrase;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Container(
          width: 56,
          height: 56,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: VfColors.primary,
            borderRadius: BorderRadius.circular(18),
          ),
          child: ExcludeSemantics(
            child: Text(
              'V',
              style: VfTextStyles.kpiValue.copyWith(
                fontSize: 25,
                fontWeight: FontWeight.w700,
                color: VfColors.surface,
              ),
            ),
          ),
        ),
        const SizedBox(height: VfSpacing.x16),
        Text(
          titre,
          style: VfTextStyles.hLg.copyWith(
            fontSize: 26,
            height: 1.15,
            color: VfColors.surface,
          ),
        ),
        const SizedBox(height: VfSpacing.x8),
        Text(
          phrase,
          style: VfTextStyles.tSm.copyWith(
            color: VfColors.surface.withValues(alpha: 0.7),
          ),
        ),
      ],
    );
  }
}

/// Le lien texte en vert primaire.
///
/// POURQUOI c'est un bouton et pas un `GestureDetector` sur du texte : « "Mot de
/// passe oublié ?" et "Créer un compte citoyen" sont des boutons, pas du texte
/// cliquable » (§3.2). Un lecteur d'écran ne propose pas d'activer un
/// paragraphe.
///
/// POURQUOI la hauteur minimale est celle du design system et non les 32 du
/// simulateur : la règle des 44 s'applique à toute cible tactile (§2.3), et le
/// simulateur lui-même la respecte partout ailleurs. C'est le seul écart de
/// dimension de M1, et il va dans le sens de l'accessibilité.
class AuthLinkButton extends StatelessWidget {
  const AuthLinkButton({
    required this.label,
    required this.onPressed,
    this.style,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    return TextButton(
      onPressed: onPressed,
      style: TextButton.styleFrom(
        minimumSize: const Size(0, VfTouch.minimum),
        padding: const EdgeInsets.symmetric(horizontal: VfSpacing.x4),
        tapTargetSize: MaterialTapTargetSize.padded,
        foregroundColor: VfColors.primary,
      ),
      child: Text(
        label,
        style: (style ?? VfTextStyles.tXs).copyWith(
          color: VfColors.primary,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

/// La ligne centrée de bas de carte : « Pas encore de compte ? » + lien.
class AuthFooterLine extends StatelessWidget {
  const AuthFooterLine({
    required this.question,
    required this.label,
    required this.onPressed,
    super.key,
  });

  final String question;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        Flexible(
          child: Text(
            question,
            style: VfTextStyles.tXs.copyWith(color: VfColors.slate2),
          ),
        ),
        AuthLinkButton(label: label, onPressed: onPressed),
      ],
    );
  }
}
