import 'package:flutter/foundation.dart';

import 'json_reader.dart';

/// L'auteur d'un message du forum — `AuteurResource`.
///
/// **Trois champs, et le contrat est explicite sur ce qui n'y est pas** (§9) :
/// ni adresse e-mail, ni téléphone, **ni rôle**.
///
/// POURQUOI pas de rôle : « publier "untel est modérateur" sur chaque message
/// dresserait la liste nominative de l'équipe de modération ». Les cartes de
/// sujet du simulateur affichent un badge de rôle (« Mentor Vérif'All »,
/// « Contributeur actif ») : c'est une divergence connue, consignée en
/// section 9 des réserves. **Ne pas la combler en devinant côté client.**
@immutable
class Auteur {
  const Auteur({
    required this.uuid,
    required this.nomAffichage,
    required this.initiales,
  });

  factory Auteur.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return Auteur(
      uuid: reader.string('uuid'),
      nomAffichage: reader.string('nom_affichage'),
      initiales: reader.string('initiales'),
    );
  }

  final String uuid;
  final String nomAffichage;

  /// Les initiales viennent du serveur : les recalculer côté client donnerait
  /// deux résultats différents sur un nom composé.
  final String initiales;

  @override
  String toString() => 'Auteur($uuid)';
}
