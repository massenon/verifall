import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Le magasin du jeton porteur.
///
/// POURQUOI `flutter_secure_storage` et **jamais** les préférences partagées : le
/// contrat qualifie explicitement le jeton de secret (§1.1), et les préférences
/// partagées sont un fichier lisible sur un appareil rooté. Le simulateur le
/// répète sur deux écrans ; ce n'est pas une précaution de style.
///
/// POURQUOI une interface : en test on remplace le magasin par une version en
/// mémoire. Sans elle, tout test du client réseau exigerait le trousseau du
/// système.
abstract interface class TokenStore {
  Future<String?> read();

  Future<void> write(String token);

  /// Purge. Appelée à la déconnexion, sur `401`, et à la suppression du compte.
  Future<void> clear();

  /// Le drapeau `onboarding_seen` (§1.4) : « le splash n'apparaît qu'une fois ».
  ///
  /// POURQUOI il vit dans le stockage sécurisé et non dans les préférences : ce
  /// n'est pas un secret, mais le socle n'a alors qu'un seul magasin à purger à
  /// la déconnexion — et un magasin oublié est une fuite.
  Future<bool> hasSeenOnboarding();

  Future<void> markOnboardingSeen();
}

/// L'implémentation réelle.
class SecureTokenStore implements TokenStore {
  SecureTokenStore({FlutterSecureStorage? storage})
    : _storage =
          storage ??
          const FlutterSecureStorage(
            // Android : le chiffrement par defaut de la bibliotheque (AES-GCM
            // avec enveloppe RSA dans le KeyStore). On ne demande PAS de
            // biometrie — le jeton doit rester lisible au demarrage, sinon
            // l'application ne peut pas restaurer une session sans que la
            // personne deverrouille a nouveau.
            aOptions: AndroidOptions(),
            // iOS : `first_unlock_this_device` — le jeton n'est lisible
            // qu'apres le premier deverrouillage, et il ne quitte JAMAIS
            // l'appareil par une sauvegarde iCloud.
            iOptions: IOSOptions(
              accessibility: KeychainAccessibility.first_unlock_this_device,
            ),
          );

  static const String _tokenKey = 'vf.auth.token';
  static const String _onboardingKey = 'vf.onboarding.seen';

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read() => _storage.read(key: _tokenKey);

  @override
  Future<void> write(String token) =>
      _storage.write(key: _tokenKey, value: token);

  @override
  Future<void> clear() => _storage.delete(key: _tokenKey);

  @override
  Future<bool> hasSeenOnboarding() async =>
      await _storage.read(key: _onboardingKey) == '1';

  @override
  Future<void> markOnboardingSeen() =>
      _storage.write(key: _onboardingKey, value: '1');
}

/// Magasin en mémoire, pour les tests et pour la session de développement.
///
/// POURQUOI il vit dans `lib/` : la surcharge de session de développement s'en
/// sert pour injecter un jeton de recette sans écrire dans le trousseau réel du
/// poste — ce qui laisserait un jeton derrière soi après un `flutter run`.
class InMemoryTokenStore implements TokenStore {
  InMemoryTokenStore({this.token, this.onboardingSeen = false});

  String? token;
  bool onboardingSeen;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String value) async => token = value;

  @override
  Future<void> clear() async => token = null;

  @override
  Future<bool> hasSeenOnboarding() async => onboardingSeen;

  @override
  Future<void> markOnboardingSeen() async => onboardingSeen = true;
}
