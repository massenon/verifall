import '../../../domain/entities/auth_inscription.dart';
import '../../../domain/repositories/auth_repository.dart';
import '../../datasources/remote/auth/auth_remote_datasource.dart';
import '../../models/auth_token.dart';

/// L'implémentation du contrat d'identité.
///
/// # Pourquoi elle est aussi mince
///
/// Aucune des sept routes d'identité n'a de cache. C'est délibéré, et le contrat
/// l'impose de fait : une connexion ne se met pas en file, une inscription crée
/// une identité, un code expire en dix minutes. Il n'y a **rien** à garder
/// localement dans ce parcours — d'où un `schema_auth.drift` qui reste vide.
///
/// Une couche qui ne fait que déléguer mérite tout de même d'exister : c'est
/// elle qui empêche un écran de connaître `ApiClient`, et donc de connaître Dio.
/// Le jour où l'on change de client HTTP, ce sont ces quelques lignes qui
/// bougent, pas six écrans.
class AuthRepositoryImpl implements AuthRepository {
  const AuthRepositoryImpl(this._distant);

  final AuthRemoteDataSource _distant;

  @override
  Future<AuthToken> connexion({
    required String email,
    required String motDePasse,
  }) => _distant.connexion(email: email, motDePasse: motDePasse);

  @override
  Future<AuthInscription> inscription(DemandeInscription demande) =>
      _distant.inscription(demande);

  @override
  Future<void> demanderUnCode(String email) => _distant.demanderUnCode(email);

  @override
  Future<void> renvoyerLeCode(String email) => _distant.renvoyerLeCode(email);

  @override
  Future<AuthToken> verifierLeCode({
    required String email,
    required String code,
  }) => _distant.verifierLeCode(email: email, code: code);

  @override
  Future<void> motDePasseOublie(String email) =>
      _distant.motDePasseOublie(email);

  @override
  Future<AuthToken> reinitialiserLeMotDePasse({
    required String email,
    required String code,
    required String motDePasse,
  }) => _distant.reinitialiserLeMotDePasse(
    email: email,
    code: code,
    motDePasse: motDePasse,
  );

  @override
  Future<void> deconnexion() => _distant.deconnexion();
}
