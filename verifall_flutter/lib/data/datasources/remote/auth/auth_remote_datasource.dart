import '../../../../core/network/api_client.dart';
import '../../../../core/network/api_endpoints.dart';
import '../../../../domain/entities/auth_inscription.dart';
import '../../../models/api_envelope.dart';
import '../../../models/auth_token.dart';
import '../../../models/json_reader.dart';
import 'nom_appareil.dart';

/// Les sept routes d'identité et la déconnexion, et rien d'autre.
///
/// # Ce que cette classe ne fait pas
///
/// Elle **n'attrape aucune erreur**. `ApiClient` lève déjà des `Failure` du
/// domaine — jamais une `DioException` — et les rattraper ici pour les relancer
/// n'ajouterait qu'un étage de pile. C'est l'écran qui décide quoi montrer, et
/// il a besoin du type exact : `ValidationFailure` souligne un champ,
/// `NetworkFailure` propose de réessayer, `RateLimitedFailure` affiche le
/// message du serveur, qui contient déjà le délai.
///
/// # Pourquoi `appareil` part sur les trois appels et pas sur les quatre autres
///
/// Le contrat ne l'accepte que sur `login`, `otp/verify` et `password/reset` :
/// ce sont les trois qui **créent un jeton**, et le champ nomme le jeton. Sur
/// `register`, `otp/request`, `otp/resend` et `password/forgot`, aucun jeton
/// n'est émis ; l'envoyer serait un champ ignoré en silence.
class AuthRemoteDataSource {
  /// POURQUOI des parametres positionnels et non nommes : les champs sont
  /// prives, et Dart interdit un parametre nomme prive. Un champ public
  /// exposerait le client HTTP a qui tient une reference sur ce depot.
  const AuthRemoteDataSource(this._client, this._appareil);

  final ApiClient _client;
  final NomAppareil _appareil;

  Future<AuthToken> connexion({
    required String email,
    required String motDePasse,
  }) async {
    final ApiEnvelope reponse = await _client.post(
      ApiEndpoints.login,
      body: <String, Object?>{
        'email': email.trim(),
        'mot_de_passe': motDePasse,
        ..._corpsAppareil(),
      },
    );
    return AuthToken.fromJson(reponse.dataObject);
  }

  Future<AuthInscription> inscription(DemandeInscription demande) async {
    final ApiEnvelope reponse = await _client.post(
      ApiEndpoints.register,
      body: demande.toBody(),
    );
    final JsonReader lecteur = JsonReader(reponse.dataObject);
    return AuthInscription(
      uuid: lecteur.string('uuid'),
      email: lecteur.string('email'),
      etape: lecteur.string('etape'),
    );
  }

  /// `otp/request` et `otp/resend` sont **le même geste** : deux URI parce que
  /// l'écran a un bouton « Renvoyer ». La distinction est gardée telle quelle
  /// parce que le contrat la publie, pas parce qu'elle change quelque chose.
  Future<void> demanderUnCode(String email) =>
      _envoyerUneDemandeDeCode(ApiEndpoints.otpRequest, email);

  Future<void> renvoyerLeCode(String email) =>
      _envoyerUneDemandeDeCode(ApiEndpoints.otpResend, email);

  Future<AuthToken> verifierLeCode({
    required String email,
    required String code,
  }) async {
    final ApiEnvelope reponse = await _client.post(
      ApiEndpoints.otpVerify,
      body: <String, Object?>{
        'email': email.trim(),
        'code': code,
        ..._corpsAppareil(),
      },
    );
    return AuthToken.fromJson(reponse.dataObject);
  }

  Future<void> motDePasseOublie(String email) =>
      _envoyerUneDemandeDeCode(ApiEndpoints.passwordForgot, email);

  Future<AuthToken> reinitialiserLeMotDePasse({
    required String email,
    required String code,
    required String motDePasse,
  }) async {
    final ApiEnvelope reponse = await _client.post(
      ApiEndpoints.passwordReset,
      body: <String, Object?>{
        'email': email.trim(),
        'code': code,
        'mot_de_passe': motDePasse,
        ..._corpsAppareil(),
      },
    );
    return AuthToken.fromJson(reponse.dataObject);
  }

  Future<void> deconnexion() async {
    await _client.post(ApiEndpoints.logout);
  }

  /// Les trois routes qui répondent **toujours pareil**.
  ///
  /// POURQUOI le retour est `void` et non le message du serveur : l'écran ne
  /// doit **jamais** brancher son affichage sur ce que ces routes rendent. Elles
  /// répondent la même chose que le compte existe ou non, et même quand le
  /// plafond est saturé ; rendre leur message inviterait un jour quelqu'un à
  /// tester `if (message.contains(...))`.
  Future<void> _envoyerUneDemandeDeCode(String chemin, String email) async {
    await _client.post(chemin, body: <String, Object?>{'email': email.trim()});
  }

  /// POURQUOI une carte plutôt qu'un paramètre nullable : `appareil` est absent
  /// du corps quand on ne sait rien dire, au lieu d'y figurer à `null`. Le
  /// contrat le déclare `nullable`, mais un champ absent est plus honnête qu'un
  /// champ vide — et c'est ce que fait déjà le socle pour `Authorization`.
  Map<String, Object?> _corpsAppareil() {
    final String? nom = _appareil.valeur();
    return nom == null || nom.isEmpty
        ? const <String, Object?>{}
        : <String, Object?>{'appareil': nom};
  }
}
