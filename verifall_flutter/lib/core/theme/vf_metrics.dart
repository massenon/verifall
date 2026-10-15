import 'package:flutter/widgets.dart';

/// Rayons du design system (§2.2).
///
/// POURQUOI des constantes nommées plutôt que des nombres posés à l'écran : les
/// valeurs du simulateur ont été choisies pour tenir dans 390 points de large.
/// Un 12 recopié en 14 ne se voit pas à la relecture, et se voit sur un
/// téléphone.
abstract final class VfRadii {
  /// `--r-sm` — petits boutons, pastilles rectangulaires.
  static const double sm = 10;

  /// `--r-md` — boutons, champs, encarts de liste.
  static const double md = 12;

  /// `--r-lg` — cartes.
  static const double lg = 16;

  /// Pastilles, avatars, interrupteurs, badges de verdict.
  static const double pill = 999;

  /// Feuille inférieure : coins hauts seulement.
  static const double sheetTop = 22;

  static const BorderRadius smAll = BorderRadius.all(Radius.circular(sm));
  static const BorderRadius mdAll = BorderRadius.all(Radius.circular(md));
  static const BorderRadius lgAll = BorderRadius.all(Radius.circular(lg));
  static const BorderRadius pillAll = BorderRadius.all(Radius.circular(pill));
  static const BorderRadius sheet = BorderRadius.only(
    topLeft: Radius.circular(sheetTop),
    topRight: Radius.circular(sheetTop),
  );
}

/// Ombres.
///
/// POURQUOI l'ombre de carte est si basse : « le produit se tient par ses
/// bordures, pas par ses ombres » (§2.2). Une ombre plus marquée casse la
/// parenté visuelle avec le prototype web.
abstract final class VfShadows {
  /// `--card-shadow` : `0 4px 18px rgba(17,24,39,.05)`.
  static const List<BoxShadow> card = <BoxShadow>[
    BoxShadow(color: Color(0x0D111827), blurRadius: 18, offset: Offset(0, 4)),
  ];

  /// Ombre portée rouge du bouton de danger (`.btn-red`).
  static const List<BoxShadow> dangerButton = <BoxShadow>[
    BoxShadow(color: Color(0x33D6293E), blurRadius: 14, offset: Offset(0, 6)),
  ];
}

/// La grille est de 4. Les valeurs ci-dessous sont **exactement** celles que le
/// simulateur emploie (§2.2) ; il n'y en a pas d'autres.
abstract final class VfSpacing {
  static const double x4 = 4;
  static const double x6 = 6;
  static const double x8 = 8;
  static const double x9 = 9;
  static const double x10 = 10;
  static const double x12 = 12;
  static const double x14 = 14;
  static const double x16 = 16;
  static const double x22 = 22;
  static const double x26 = 26;

  /// Gouttière de la pile verticale ordinaire (`.stack`).
  static const double stack = x14;

  /// Gouttière de la pile serrée (`.stack-sm`).
  static const double stackSm = x9;

  /// Marges latérales de la zone défilante.
  static const double screenInline = x16;

  /// Marge haute de la zone défilante, en plus de `zone sûre + barre supérieure`.
  static const double screenTopExtra = x16;

  /// Marge basse de la zone défilante, en plus de la barre basse.
  static const double screenBottomExtra = x26;
}

/// Cibles tactiles (§2.4) : `>= 44` partout, sans exception.
///
/// POURQUOI ces hauteurs sont des **minimums** et jamais des hauteurs fixes : le
/// texte suit le réglage d'accessibilité du téléphone et doit rester lisible à
/// 200 % (§2.3). Une hauteur fixe coupe une ligne au lieu de grandir.
abstract final class VfTouch {
  /// `.btn` — bouton principal.
  static const double buttonMinHeight = 46;
  static const double buttonInlinePadding = 18;

  /// `.btn-sm` — uniquement dans une carte, jamais comme action principale.
  static const double smallButtonHeight = 38;

  /// `.iconbtn` — rond, dans la barre supérieure.
  static const double iconButtonSize = 44;

  /// `.mrow` — ligne de menu.
  static const double menuRowMinHeight = 52;
  static const double menuRowVerticalPadding = 13;
  static const double menuRowInlinePadding = 15;

  /// `.chip` — pastille de filtre.
  static const double chipHeight = 38;
  static const double chipGutter = 7;

  /// Entrée de la barre basse.
  static const double navItemMinHeight = 46;

  /// `.otp-box` : 46 x 56.
  static const double otpBoxWidth = 46;
  static const double otpBoxHeight = 56;

  /// `.switch` — piste, pastille, course.
  static const Size switchTrack = Size(50, 29);
  static const double switchKnob = 23;
  static const double switchTravel = 21;

  /// `.consent` — case carrée.
  static const double consentBox = 22;
  static const double consentBoxRadius = 6;

  /// Le plancher absolu d'accessibilité, vérifié par les tests (§6.7).
  static const double minimum = 44;
}

/// Chrome de l'application (§2.5) et ancrages qui en dépendent.
abstract final class VfChrome {
  /// Hauteur de la barre supérieure, **hors** zone sûre.
  static const double appBarHeight = 52;

  /// Hauteur totale de la barre basse.
  static const double bottomNavHeight = 76;

  /// Bouton central « Vérifier » : rond primaire remonté au-dessus de la barre.
  static const double centerButtonSize = 56;
  static const double centerButtonLift = 26;
  static const double centerButtonRing = 4;

  /// Largeur du tiroir latéral.
  static const double drawerWidth = 274;

  /// Poignée de la feuille inférieure : 40 x 4.
  static const double sheetHandleWidth = 40;
  static const double sheetHandleHeight = 4;

  /// Hauteur maximale d'une feuille inférieure, en fraction de l'écran.
  static const double sheetMaxHeightFactor = 0.82;

  /// `.pointspill`.
  static const double pointsPillHeight = 38;

  /// Ancrage du toast : **au-dessus** de la barre basse (`nav + 14`).
  static const double toastLift = bottomNavHeight + 14;

  /// Trois toasts au maximum, trois secondes (§2.7).
  static const int toastMaxVisible = 3;
  static const Duration toastDuration = Duration(seconds: 3);
}
