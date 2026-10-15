import 'package:flutter/widgets.dart';

/// Les **trois** transitions du design system, et elles suffisent (§2.6).
///
/// POURQUOI trois et pas dix : un vocabulaire d'animation qui grossit devient un
/// vocabulaire qu'on n'applique plus de la même façon d'un écran à l'autre, et
/// c'est ce qui donne l'impression de deux applications collées.
abstract final class VfMotion {
  /// La courbe commune à `push` et `pop` : `cubic-bezier(.2,.7,.3,1)`.
  static const Cubic curve = Cubic(0.2, 0.7, 0.3, 1);

  /// Empilement et retour.
  static const Duration navigation = Duration(milliseconds: 260);

  /// Fondu — changement d'onglet, rendu sur place.
  static const Duration fade = Duration(milliseconds: 220);

  /// Montée d'une feuille inférieure : +40 en 260 ms.
  static const Duration sheet = Duration(milliseconds: 260);
  static const double sheetRise = 40;

  /// Glissement du tiroir : -100 % en 240 ms.
  static const Duration drawer = Duration(milliseconds: 240);

  /// Animation d'une barre de progression.
  static const Duration progress = Duration(milliseconds: 450);

  /// Balayage du squelette de chargement.
  static const Duration skeleton = Duration(milliseconds: 1400);

  /// Décalage d'entrée de `push` (depuis la droite).
  static const double pushOffset = 22;

  /// Décalage d'entrée de `pop` (depuis la gauche).
  static const double popOffset = -16;

  /// Décalage vertical de `fade`.
  static const double fadeOffset = 8;

  /// `prefers-reduced-motion` est respecté par le simulateur et doit l'être par
  /// l'application (§2.6).
  ///
  /// Ce qu'il coupe : l'avance automatique du carrousel du splash, les
  /// confettis, le battement du point actif de la chronologie d'analyse.
  ///
  /// POURQUOI c'est une fonction du socle et pas un test dispersé : « un oubli »
  /// signifie ici qu'une personne sujette au mal des transports ferme
  /// l'application.
  static bool reducedMotion(BuildContext context) =>
      MediaQuery.disableAnimationsOf(context);
}
