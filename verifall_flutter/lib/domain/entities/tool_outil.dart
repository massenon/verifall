import 'package:flutter/foundation.dart';

import '../../data/models/json_reader.dart';

/// Un outil de l'annuaire OSINT — `OutilResource` (§10.5).
///
/// POURQUOI le client ne recopie **aucune** adresse : « quinze URL recopiées à
/// la main, c'est quinze occasions d'envoyer quelqu'un ailleurs que là où on le
/// croit » (arbitrage n° 64). Il ouvre celle que l'API renvoie, et rien d'autre.
@immutable
class Outil {
  const Outil({
    required this.slug,
    required this.titre,
    required this.description,
    required this.url,
    this.categorie,
    this.etiquette,
    this.libelleDuLien,
    this.recommande = false,
  });

  factory Outil.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return Outil(
      slug: reader.string('slug'),
      titre: reader.string('titre'),
      description: reader.string('description'),
      url: reader.string('url'),
      categorie: reader.stringOrNull('categorie'),
      // « Gratuit » / « Freemium » : c'est le serveur qui le dit. Le deduire du
      // nom de l'outil serait une affirmation commerciale inventee.
      etiquette: reader.stringOrNull('etiquette'),
      libelleDuLien: reader.stringOrNull('libelle_du_lien'),
      recommande: reader.boolValue('recommande'),
    );
  }

  final String slug;
  final String titre;
  final String description;
  final String url;
  final String? categorie;
  final String? etiquette;
  final String? libelleDuLien;
  final bool recommande;

  /// La forme sérialisée pour le cache 24 h — mêmes clés que l'API.
  Map<String, Object?> toJson() => <String, Object?>{
    'slug': slug,
    'titre': titre,
    'description': description,
    'url': url,
    'categorie': categorie,
    'etiquette': etiquette,
    'libelle_du_lien': libelleDuLien,
    'recommande': recommande,
  };

  @override
  String toString() => 'Outil($slug)';
}

/// La liste d'outils **et ses catégories**, telles que l'endpoint les rend.
///
/// POURQUOI les catégories viennent de `meta.categories` et jamais d'une liste
/// écrite à la main : « deux listes finissent par diverger, et c'est le filtre
/// qui cesse de trouver quoi que ce soit » (arbitrage n° 55).
@immutable
class AnnuaireOutils {
  const AnnuaireOutils({required this.outils, required this.categories});

  final List<Outil> outils;
  final List<String> categories;

  bool get estVide => outils.isEmpty;
}
