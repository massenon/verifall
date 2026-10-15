import '../../entities/thread_entity.dart';
import '../../repositories/forum_repository.dart';

/// Publier un sujet sur le forum.
///
/// # La seule règle vérifiée avant l'envoi, et pourquoi il n'y en a qu'une
///
/// Le simulateur exige **8 caractères de titre** et le dit par un toast rouge.
/// C'est la seule validation locale, et c'est délibéré : « les refus serveur
/// arrivent en `422 validation` avec `details`, et se rendent sous le champ
/// concerné » (03-ECRANS-05 §3.20).
///
/// POURQUOI ne pas doubler ici les bornes du contrat (titre 8-160, corps
/// 20-5000) : deux jeux de règles finissent par diverger, et c'est toujours le
/// client qui se trompe — il ne connaît ni le contexte, ni les évolutions.
/// Vérifier le minimum que la maquette annonce évite un aller-retour inutile ;
/// vérifier le reste reviendrait à réimplémenter le serveur.
class PublierUnSujet {
  const PublierUnSujet(this._depot);

  /// Le minimum annoncé par le simulateur, et repris au contrat (§10.5).
  static const int longueurMinimaleTitre = 8;

  /// La borne haute du contrat, utilisée par le compteur du champ.
  static const int longueurMaximaleTitre = 160;

  /// La borne haute du contrat pour le corps.
  static const int longueurMaximaleCorps = 5000;

  final ForumRepository _depot;

  /// `null` si le titre passe, sinon la phrase du simulateur, mot pour mot.
  static String? valider(String titre) {
    return titre.trim().length < longueurMinimaleTitre
        ? 'Donnez un titre d au moins $longueurMinimaleTitre caractères.'
        : null;
  }

  Future<SujetForumDetail> call({
    required String titre,
    required String corps,
    String? categorie,
    String? lienSignale,
  }) {
    return _depot.publierSujet(
      titre: titre.trim(),
      corps: corps.trim(),
      categorie: categorie,
      lienSignale: lienSignale,
    );
  }
}
