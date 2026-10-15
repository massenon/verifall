import '../../data/models/auth_token.dart';
import '../entities/auth_inscription.dart';

/// Le contrat d'accès aux sept routes d'identité, plus la déconnexion.
///
/// # Pourquoi il rend un `AuthToken` du socle et non une entité de domaine
///
/// `AuthToken` est **le modèle transverse du socle** : le contrat garantit que
/// `login`, `otp/verify` et `password/reset` « renvoient tous exactement la même
/// forme » (§1.2), et le socle en a fait un modèle unique pour que les trois
/// passent par un seul chemin de code. En redéclarer une copie dans `domain/`
/// aurait produit deux formes de la même chose, et donc deux endroits à
/// corriger — exactement ce que la règle du composant partagé interdit.
///
/// # Ce que ce contrat ne fait pas
///
/// Il n'ouvre **pas** la session : rendre un jeton et l'installer sont deux
/// gestes différents. L'installation appartient à `OuvrirSession`, précisément
/// pour qu'il n'y ait qu'un seul endroit où un jeton entre dans le stockage
/// sécurisé.
abstract interface class AuthRepository {
  /// `POST /auth/login` — rend un jeton **directement** (réserve A.10 tranchée).
  ///
  /// Un compte jamais activé reçoit ici `401 non_authentifie`, **strictement
  /// identique** à un mot de passe faux : distinguer les deux transformerait
  /// l'écran de connexion en annuaire des inscrits (§1.6).
  Future<AuthToken> connexion({
    required String email,
    required String motDePasse,
  });

  /// `POST /auth/register` — **ne rend pas de jeton**, seulement l'étape.
  Future<AuthInscription> inscription(DemandeInscription demande);

  /// `POST /auth/otp/request` — redemander un code d'activation.
  ///
  /// La réponse est **indifférenciée**, que le compte existe ou non, et même
  /// quand le plafond de trois demandes par dix minutes est saturé.
  Future<void> demanderUnCode(String email);

  /// `POST /auth/otp/resend` — **le même geste** que [demanderUnCode] ; deux URI
  /// parce que l'écran a un bouton « Renvoyer » (§10.1).
  Future<void> renvoyerLeCode(String email);

  /// `POST /auth/otp/verify` — active un compte neuf **et** rend le jeton.
  ///
  /// Un code faux, expiré ou épuisé revient en `422 validation` avec
  /// `details.code` : le message du serveur distingue les trois cas, et le
  /// client l'affiche tel quel plutôt que d'en fabriquer un.
  Future<AuthToken> verifierLeCode({
    required String email,
    required String code,
  });

  /// `POST /auth/password/forgot` — réponse **volontairement indifférenciée**.
  Future<void> motDePasseOublie(String email);

  /// `POST /auth/password/reset` — troisième point d'entrée qui rend un jeton.
  ///
  /// Le serveur révoque **tous** les jetons, toutes les sessions web et tous les
  /// cookies « se souvenir de moi » : le jeton rendu ici est le seul vivant.
  Future<AuthToken> reinitialiserLeMotDePasse({
    required String email,
    required String code,
    required String motDePasse,
  });

  /// `POST /auth/logout` — révoque **le seul jeton présenté**.
  ///
  /// Se déconnecter d'un appareil ne ferme pas les autres : ce serait une
  /// surprise désagréable, et cela existe déjà sous un autre nom — la
  /// réinitialisation du mot de passe (§1.5).
  Future<void> deconnexion();
}
