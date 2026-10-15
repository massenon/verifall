import 'package:flutter/widgets.dart';

/// Les jetons de couleur du design system citoyen.
///
/// POURQUOI une classe de constantes et pas des couleurs littérales : le design
/// system existe déjà (`public/assets/css/citizen.css`), il n'est pas à
/// concevoir. Le déclarer une seule fois est ce qui empêche la dérive — une
/// nuance de vert recopiée à la main dans un écran, et deux écrans ne sont plus
/// le même produit.
///
/// **Aucune couleur littérale ailleurs dans le code.** Si une teinte manque, elle
/// se demande au responsable du socle ; elle ne s'invente pas dans un parcours.
///
/// Source : `docs/flutter/02-DESIGN-SYSTEM.md` §2.1.
abstract final class VfColors {
  /// `--primary` — actions, états actifs, progression, onglet courant.
  static const Color primary = Color(0xFF09B850);

  /// `--primary-dark` — texte sur fond primaire clair.
  static const Color primaryDark = Color(0xFF079A43);

  /// `--primary-soft` — fonds d'encart pédagogique, pastilles `tag.prim`.
  static const Color primarySoft = Color(0xFFE6F8EE);

  /// `--forest` — fonds pleins : connexion, carte de solde, cadre du certificat.
  static const Color forest = Color(0xFF063B2A);

  /// `--brandred` — bouton « Vérifier », zone de danger, échéances dépassées.
  static const Color brandRed = Color(0xFFD6293E);

  /// `--ink` — titres et texte fort.
  static const Color ink = Color(0xFF111827);

  /// `--slate2` — texte secondaire, icônes inactives.
  static const Color slate2 = Color(0xFF64748B);

  /// `--line` — bordures de carte, séparateurs, piste de progression.
  static const Color line = Color(0xFFE5E7EB);

  /// `--canvas` — fond d'écran général.
  static const Color canvas = Color(0xFFF6F8F7);

  /// `--surface` — cartes, barres, feuilles.
  static const Color surface = Color(0xFFFFFFFF);

  /// `--sidebarbg` — fond du tiroir, avatar par défaut.
  static const Color sidebarBg = Color(0xFF1F2124);

  /// `--vtrue` — verdict VRAI.
  static const Color verdictTrue = Color(0xFF16A34A);

  /// `--vfalse` — verdict FAUX.
  static const Color verdictFalse = Color(0xFFDC2626);

  /// `--vinvest` — verdict TROMPEUR, échéance proche.
  static const Color verdictInvestigate = Color(0xFFD97706);

  /// `--vinfo` — « EN COURS », séries secondaires des graphiques.
  static const Color info = Color(0xFF2563EB);

  /// `--xp` — points d'expérience.
  static const Color xp = Color(0xFF7C3AED);

  /// Couleur du corps long (`.prose`), donnée par le tableau §2.3. Elle n'a pas
  /// de nom de jeton CSS mais elle est bien dans la source ; elle est ici pour
  /// éviter qu'un parcours ne la recopie en littéral.
  static const Color proseInk = Color(0xFF374151);

  /// Blanc à 65 % employé sur fond forêt (splash, cartes sombres). Signalé en
  /// §2.9 comme un contraste qui passe **de justesse** : ne pas le baisser.
  static const Color onForestMuted = Color(0xA6FFFFFF);
}

/// Couleurs de marques tierces.
///
/// POURQUOI elles sont séparées de la palette : ce sont des **logos, pas des
/// jetons** (§2.1). Les mélanger inviterait un jour à colorer un bouton du
/// produit en vert WhatsApp.
abstract final class VfBrandColors {
  /// Vert WhatsApp, uniquement sur les boutons de partage.
  static const Color whatsapp = Color(0xFF25D366);

  // Les trois couleurs d'opérateurs Mobile Money de l'écran 26 ne sont PAS
  // reprises ici : le design system ne donne pas leurs valeurs, et les deviner
  // reviendrait à inventer un jeton. Elles se demandent avec l'écran 26.
}
