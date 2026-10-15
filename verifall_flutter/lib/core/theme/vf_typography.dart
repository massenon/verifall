import 'package:flutter/widgets.dart';

import 'vf_colors.dart';

/// Les deux familles du design system, plus la monospace de recopie.
///
/// POURQUOI des ressources locales et jamais une police distante : « un premier
/// lancement qui attend une police distante affiche un écran nu sur une
/// connexion lente, c'est-à-dire dans le cas d'usage principal du produit »
/// (§2, en-tête).
///
/// Les fichiers `.ttf` ne sont pas versionnés ici ; tant qu'ils ne sont pas
/// déposés dans `assets/fonts/` et déclarés dans `pubspec.yaml`, Flutter retombe
/// sur la police système. Voir `assets/fonts/README.md`.
abstract final class VfFonts {
  /// Titres `h1..h4` — et rien d'autre.
  static const String display = 'Instrument Sans';

  /// Tout le reste.
  static const String body = 'Inter';

  /// La monospace n'apparaît que sur trois éléments : la référence d'une
  /// vérification, l'identifiant d'un certificat et le code de parrainage —
  /// c'est-à-dire partout où un humain devra recopier des caractères sans se
  /// tromper (§2.3).
  static const String mono = 'IBM Plex Mono';
}

/// L'échelle typographique (§2.3).
///
/// Les tailles sont en pixels CSS dans le simulateur et se transposent en points
/// logiques Flutter **à l'identique** : le châssis du simulateur est déjà un
/// iPhone 14 (390 x 844) puis un Pixel 8 (412 x 880).
///
/// POURQUOI `height` est exprimé en multiple et non en pixels : c'est
/// l'interligne CSS (`line-height`), et Flutter attend le même rapport.
abstract final class VfTextStyles {
  /// `.h-lg` — titre d'écran. 22 / 1.15, 700.
  static const TextStyle hLg = TextStyle(
    fontFamily: VfFonts.display,
    fontSize: 22,
    height: 1.15,
    fontWeight: FontWeight.w700,
    color: VfColors.ink,
  );

  /// `.h-md` — titre de section forte. 17 / 1.25, 700.
  static const TextStyle hMd = TextStyle(
    fontFamily: VfFonts.display,
    fontSize: 17,
    height: 1.25,
    fontWeight: FontWeight.w700,
    color: VfColors.ink,
  );

  /// `.h-sm` — titre de section. 15 / 1.3, 700.
  static const TextStyle hSm = TextStyle(
    fontFamily: VfFonts.display,
    fontSize: 15,
    height: 1.3,
    fontWeight: FontWeight.w700,
    color: VfColors.ink,
  );

  /// Corps par défaut. 15 / 1.5, 400.
  static const TextStyle body = TextStyle(
    fontFamily: VfFonts.body,
    fontSize: 15,
    height: 1.5,
    fontWeight: FontWeight.w400,
    color: VfColors.ink,
  );

  /// `.t-md` — corps moyen. 14.5.
  static const TextStyle tMd = TextStyle(
    fontFamily: VfFonts.body,
    fontSize: 14.5,
    height: 1.5,
    fontWeight: FontWeight.w400,
    color: VfColors.ink,
  );

  /// `.t-sm` — corps petit. 13.
  static const TextStyle tSm = TextStyle(
    fontFamily: VfFonts.body,
    fontSize: 13,
    height: 1.5,
    fontWeight: FontWeight.w400,
    color: VfColors.slate2,
  );

  /// `.t-xs` — légende. 11.5.
  static const TextStyle tXs = TextStyle(
    fontFamily: VfFonts.body,
    fontSize: 11.5,
    height: 1.4,
    fontWeight: FontWeight.w400,
    color: VfColors.slate2,
  );

  /// `.eyebrow` — sur-titre. 10, interlettrage `.16em`, capitales, 700, primaire.
  ///
  /// `.16em` de 10 px vaut 1.6 point d'interlettrage : Flutter attend une valeur
  /// absolue, pas un ratio. La mise en capitales est du ressort de l'appelant
  /// (`text.toUpperCase()`), Flutter n'ayant pas de `text-transform`.
  static const TextStyle eyebrow = TextStyle(
    fontFamily: VfFonts.body,
    fontSize: 10,
    height: 1.2,
    letterSpacing: 1.6,
    fontWeight: FontWeight.w700,
    color: VfColors.primary,
  );

  /// `.kpi .v` — valeur de KPI. 24 / 1, 800, Instrument Sans.
  static const TextStyle kpiValue = TextStyle(
    fontFamily: VfFonts.display,
    fontSize: 24,
    height: 1,
    fontWeight: FontWeight.w800,
    color: VfColors.ink,
  );

  /// `.kpi .l` — libellé de KPI. 11, 400, ardoise.
  static const TextStyle kpiLabel = TextStyle(
    fontFamily: VfFonts.body,
    fontSize: 11,
    height: 1.3,
    fontWeight: FontWeight.w400,
    color: VfColors.slate2,
  );

  /// `.prose` — texte long. 14.5 / 1.65.
  static const TextStyle prose = TextStyle(
    fontFamily: VfFonts.body,
    fontSize: 14.5,
    height: 1.65,
    fontWeight: FontWeight.w400,
    color: VfColors.proseInk,
  );

  /// Libellé de bouton. Il n'a pas de classe propre dans le CSS ; il reprend le
  /// corps en demi-gras, comme le simulateur.
  static const TextStyle button = TextStyle(
    fontFamily: VfFonts.body,
    fontSize: 15,
    height: 1.2,
    fontWeight: FontWeight.w600,
  );

  /// Chiffres tabulaires : référence de vérification, identifiant de certificat,
  /// code de parrainage, et le solde de la pastille de points.
  ///
  /// POURQUOI `tabular figures` : un nombre qui change de largeur en changeant de
  /// valeur fait sauter la mise en page à chaque rafraîchissement du solde.
  static const TextStyle mono = TextStyle(
    fontFamily: VfFonts.mono,
    fontSize: 13,
    height: 1.4,
    fontWeight: FontWeight.w400,
    color: VfColors.ink,
    fontFeatures: <FontFeature>[FontFeature.tabularFigures()],
  );
}
