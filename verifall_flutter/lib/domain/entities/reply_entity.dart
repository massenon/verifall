import 'package:flutter/foundation.dart';

import '../../data/models/auteur.dart';

/// Une réponse à un sujet du forum.
///
/// # Un seul niveau d'imbrication, et il est garanti ici
///
/// Le point de vigilance de l'écran 20 est explicite : « **un seul niveau
/// d'imbrication** dans les réponses, parce qu'au-delà un fil devient illisible
/// sur téléphone ».
///
/// POURQUOI la contrainte vit dans l'entité et pas dans le widget : un widget
/// qui se contente de ne pas dessiner le troisième niveau garde quand même le
/// contenu en mémoire, et le fera réapparaître le jour d'une refonte de la
/// liste. En bornant à la lecture, la règle survit à ceux qui redessineront
/// l'écran.
@immutable
class ReponseForum {
  const ReponseForum({
    required this.uuid,
    required this.corps,
    required this.auteur,
    this.publieLe,
    this.reponses = const <ReponseForum>[],
  });

  /// Une réponse n'a pas de route à elle : son identifiant est un uuid, jamais
  /// un entier (§4 du contrat).
  final String uuid;

  /// Du HTML Quill, rendu sur liste blanche.
  final String corps;

  final Auteur auteur;

  final DateTime? publieLe;

  /// Les réponses de premier niveau. **Toujours sans enfants** : voir plus haut.
  final List<ReponseForum> reponses;

  @override
  String toString() => 'ReponseForum($uuid)';
}
