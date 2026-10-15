import '../../../core/network/auth_controller.dart';
import '../../../core/network/local_data_purger.dart';
import '../../../core/network/token_store.dart';
import '../../../data/models/auth_token.dart';

/// **Le** chemin par lequel un jeton entre dans l'application. Il n'y en a pas
/// d'autre.
///
/// # Pourquoi un seul chemin pour trois points d'entrée
///
/// `POST /auth/login`, `POST /auth/otp/verify` et `POST /auth/password/reset`
/// « renvoient tous exactement la même forme » (§1.2 du contrat). Trois écrans
/// qui installeraient chacun leur jeton, c'est trois endroits où oublier la
/// purge, trois endroits où écrire dans le mauvais magasin, et trois endroits à
/// corriger le jour où la forme bouge. Ici, il y en a un.
///
/// C'est un point de la définition de fini de M1, et il se vérifie : le test
/// `parcours_m1_test.dart` fait passer les trois réponses par cette classe et
/// relit le même magasin.
///
/// # Pourquoi la purge locale a lieu à CHAQUE ouverture, et pas seulement au
/// changement de mot de passe
///
/// Le contrat l'exige explicitement pour `password/reset` : « purger son
/// stockage sécurisé et son cache local au passage, exactement comme à la
/// déconnexion, avant d'installer le jeton neuf ».
///
/// L'étendre aux trois points d'entrée supprime la seule branche de cette
/// classe, et surtout elle règle un cas ordinaire au Bénin : **le téléphone
/// partagé** (arbitrage n° 59). Sans purge, la personne qui se connecte après
/// une autre hérite du cache de lecture de la précédente — un historique de
/// vérifications, des brouillons, des sujets lus. Un tri « ce qui est sensible /
/// ce qui ne l'est pas » se trompe toujours au moins une fois.
///
/// # Pourquoi on ne passe pas par `AuthController.signOut()` pour purger
///
/// `signOut()` pose l'état `deconnecte`, qui réveille la garde de route et
/// renvoie sur `/connexion`. Ouvrir une session en la fermant d'abord ferait
/// clignoter l'écran de connexion **au moment précis** où la personne vient de
/// réussir sa saisie. On purge donc les deux magasins directement, et l'état ne
/// change qu'une fois, dans le bon sens.
class OuvrirSession {
  const OuvrirSession(this._controller, this._tokenStore, this._purger);

  final AuthController _controller;
  final TokenStore _tokenStore;
  final LocalDataPurger _purger;

  /// Installe le jeton et ouvre la session.
  ///
  /// L'ordre compte : purger **avant** d'écrire. Purger après effacerait le
  /// jeton qu'on vient de poser, et l'application redemanderait le mot de passe
  /// au premier appel authentifié.
  Future<void> call(AuthToken token) async {
    await _tokenStore.clear();
    await _purger.purgeAll();

    // `signIn` ecrit le jeton dans `flutter_secure_storage` et pose l'etat
    // `connecte`. C'est le socle qui le fait, et c'est voulu : le magasin de
    // jetons n'est ecrit qu'a un seul endroit de toute l'application.
    await _controller.signIn(token);
  }
}
