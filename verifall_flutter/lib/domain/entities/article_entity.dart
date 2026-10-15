import 'package:flutter/foundation.dart';

/// Une brève vérifiée du Hub.
///
/// # Ce que le Hub est, et ce qui se lit dans ce type
///
/// L'arbitrage n° 44 : **le Hub qualifie plus qu'il ne relaie**. « C'est ce qui
/// sépare le Hub d'un agrégateur : on n'y relaie pas une information, on dit ce
/// qu'elle vaut. »
///
/// D'où deux champs qui ne sont pas décoratifs : le verdict, et la source
/// primaire.
@immutable
class ArticleHub {
  const ArticleHub({
    required this.slug,
    required this.titre,
    this.categorie,
    this.chapo,
    this.corps,
    this.verdict,
    this.verdictLibelle,
    this.sourcePrimaire,
    this.publieLe,
    this.couvertureUrl,
  });

  /// **Un slug**, jamais un entier — le lien profond devient
  /// `verifall://hub/{slug}` (§4 du contrat).
  final String slug;

  final String titre;

  /// Pour le Hub, `category` est un **nom de catégorie** (`Politique`, `Santé`)
  /// et non un slug : c'est ce que le §3.5 du contrat précise, et c'est ce qui
  /// s'affiche tel quel en titre d'écran et sur la couverture.
  final String? categorie;

  final String? chapo;

  /// Du HTML, rendu sur liste blanche.
  final String? corps;

  /// La valeur technique. **On branche dessus, on n'affiche jamais.**
  final String? verdict;

  /// Le libellé français rendu par le serveur. **On affiche celui-ci.**
  ///
  /// POURQUOI les deux voyagent ensemble : « le client affiche le libellé et
  /// branche sur la valeur ; il ne fabrique jamais le libellé lui-même :
  /// ajouter un cas côté serveur casserait tous les `switch` du client » (§8).
  ///
  /// C'est aussi ce qui explique que le Hub affiche « DÉMENTI » là où un rapport
  /// dit « FAUX » : « c'est un registre, pas une divergence — on dément une
  /// rumeur, on qualifie une affirmation ».
  final String? verdictLibelle;

  final String? sourcePrimaire;

  final DateTime? publieLe;

  /// La couverture distante. `null` = le dégradé de `VfCover` suffit — « en
  /// production ce sont des couvertures distantes, et le dégradé devient le
  /// repli, jamais un carré gris » (§2.7).
  final String? couvertureUrl;

  /// Une brève sans verdict n'affiche pas un bandeau vide.
  ///
  /// POURQUOI « EN COURS » plutôt que rien : « un bandeau vide passerait pour un
  /// défaut d'affichage » (03-ECRANS-05 §3.24). Ce libellé est du texte
  /// éditorial de la documentation, pas un libellé d'énumération fabriqué à la
  /// place du serveur.
  static const String libelleVerdictAbsent = 'EN COURS';

  bool get aUnVerdict =>
      verdictLibelle != null && verdictLibelle!.trim().isNotEmpty;

  @override
  String toString() => 'ArticleHub($slug)';
}

/// Une page de brèves.
@immutable
class PageArticles {
  const PageArticles({
    required this.articles,
    this.curseurSuivant,
    this.recupereLe,
  });

  final List<ArticleHub> articles;

  /// **Opaque** (§3.2).
  final String? curseurSuivant;

  final DateTime? recupereLe;

  bool get vientDuCache => recupereLe != null;
}
