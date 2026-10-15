import 'package:flutter_riverpod/flutter_riverpod.dart';

/// **Le solde d'unités affiché par la pastille de points.**
///
/// # Pourquoi il appartient au socle
///
/// `VfPointsPill` y était déjà ; **la source de sa valeur, non**. Quatre écrans
/// l'alimentaient de quatre façons différentes, et la même pastille affichait
/// donc quatre choses selon l'onglet :
///
/// | Écran | Ce qu'il donnait à la pastille | Ce que ça valait |
/// |---|---|---|
/// | Tableau de bord, Vérifier, Mes vérifications (M2) | `soldeUnitesProvider` | juste |
/// | Catalogue (M3) | `session.utilisateur.unites` | le solde **à l'émission du jeton** — périmé dès la seconde suivante |
/// | Hub (M4) | `null`, en dur | un tiret, toujours |
/// | Mon profil (M5) | l'état du profil | juste, mais séparé |
///
/// C'est la duplication que la règle 3 du parallélisme veut éviter, et elle est
/// **visible par l'utilisateur** : le troisième signe qu'un composant appartient
/// au socle. Arbitrage n° 135.
///
/// # Trois règles tiennent dans ce provider
///
/// 1. **Il ne vient jamais du cache** (contrat §7.1). Il n'est donc alimenté que
///    par une réponse réseau : `compteurs.unites` de l'agrégat d'accueil,
///    `meta.solde` après une soumission, `GET /me/wallet`, `GET /me`, ou le
///    résumé de compte rendu **avec le jeton** — qui est, lui aussi, une lecture
///    serveur, à l'instant de l'émission.
/// 2. **`null` signifie « pas encore relu »**, et la pastille affiche alors un
///    tiret — « mieux vaut un tiret honnête qu'un zéro qui ferait croire à un
///    compte vide ».
/// 3. **Aucun débit local.** Le client ne soustrait rien : « le solde est un fait
///    serveur ». Il n'existe donc pas de méthode `debiter`, et c'est volontaire —
///    on ne peut pas l'appeler par accident.
///
/// POURQUOI il n'est pas `autoDispose` : la pastille vit dans la barre supérieure
/// de six écrans. Le rejeter en changeant d'onglet ferait clignoter un tiret à
/// chaque navigation.
final NotifierProvider<SoldeUnites, int?> soldeUnitesProvider =
    NotifierProvider<SoldeUnites, int?>(SoldeUnites.new);

class SoldeUnites extends Notifier<int?> {
  @override
  int? build() => null;

  /// Enregistre un solde **lu du serveur**.
  ///
  /// Un `null` reçu ne remplace pas une valeur connue : il signifie « cet appel
  /// ne portait pas de solde », pas « le solde est devenu inconnu ».
  void relu(int? soldeServeur) {
    if (soldeServeur == null) {
      return;
    }
    state = soldeServeur;
  }

  /// Remet à « inconnu ».
  ///
  /// Appelé à la déconnexion — un solde qui survivrait à la purge s'afficherait
  /// au compte suivant sur un téléphone partagé — et quand on sait que la valeur
  /// affichée peut être périmée sans avoir de quoi la remplacer, après une
  /// reprise de la file par exemple.
  void oublier() => state = null;
}
