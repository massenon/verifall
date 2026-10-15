import '../../repositories/forum_repository.dart';

/// Répondre à un sujet.
///
/// # Deux bornes, deux sources, et aucune contradiction
///
/// Le contrat accepte `corps` de **2 à 5000 caractères**. Le simulateur, lui,
/// exige **5 caractères** et pose un `maxlength` de **800** sur la zone de
/// saisie.
///
/// Ce sont deux autorités différentes, et elles ne se contredisent pas : « le
/// contrat l'emporte sur le réseau, le simulateur l'emporte sur l'écran ». Les
/// bornes de l'écran (5 et 800) sont **incluses** dans celles du serveur (2 et
/// 5000) — aucune saisie acceptée par l'écran ne sera refusée par le serveur.
class RepondreAUnSujet {
  const RepondreAUnSujet(this._depot);

  /// Le minimum du simulateur.
  static const int longueurMinimale = 5;

  /// Le `maxlength` du simulateur, repris par le compteur du champ.
  static const int longueurMaximale = 800;

  final ForumRepository _depot;

  /// `null` si la réponse passe, sinon la phrase du simulateur.
  static String? valider(String corps) {
    return corps.trim().length < longueurMinimale
        ? 'Ecrivez une réponse un peu plus longue.'
        : null;
  }

  /// Un fil **verrouillé** répond `422 action_impossible`, et le message du
  /// serveur s'affiche tel quel. L'écran masque le composeur ; c'est le serveur
  /// qui protège — « contourner la décision de la modération par l'API serait
  /// une faille ».
  Future<void> call({required String slug, required String corps}) =>
      _depot.repondre(slug, corps.trim());
}
