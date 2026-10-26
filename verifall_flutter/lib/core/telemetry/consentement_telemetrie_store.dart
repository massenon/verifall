import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persistance locale du consentement de télémétrie.
///
/// # Pourquoi le stockage sécurisé
///
/// Même raisonnement que pour `IdentifiantDInstallation` : ce n'est pas un
/// secret, mais c'est l'unique mécanisme de persistance clé-valeur déjà
/// présent. Ajouter une seconde mécanique pour une chaîne de 4 caractères
/// coûterait plus qu'elle ne rapporte.
///
/// # Pourquoi une clé séparée de `AuthConsentements`
///
/// `AuthConsentements` part dans `POST /auth/register` — elle appartient au
/// serveur. Le consentement de télémétrie est **local** : il pilote si un
/// incident quitte l'appareil, pas si un traitement est autorisé côté API.
/// Les mélanger dans le même objet ferait voyager une décision qui concerne
/// l'appareil vers un serveur qui n'en a rien à faire.
class ConsentementTelemetrieStore {
  ConsentementTelemetrieStore({FlutterSecureStorage? stockage})
    : _stockage = stockage ?? const FlutterSecureStorage();

  static const String _cle = 'vf_telemetrie_consent';

  final FlutterSecureStorage _stockage;

  /// Lit le consentement stocké. Rend `false` si le coffre est inaccessible.
  Future<bool> lire() async {
    try {
      final String? valeur = await _stockage.read(key: _cle);
      return valeur == 'true';
    } on Object catch (_) {
      return false;
    }
  }

  /// Écrit le consentement. Un échec est silencieux : il ne doit pas
  /// empêcher l'inscription ni les réglages de se terminer.
  Future<void> ecrire(bool valeur) async {
    try {
      await _stockage.write(key: _cle, value: valeur.toString());
    } on Object catch (_) {}
  }

  /// Efface la clé — appelé à la déconnexion pour ne pas laisser de trace
  /// sur un téléphone partagé.
  Future<void> effacer() async {
    try {
      await _stockage.delete(key: _cle);
    } on Object catch (_) {}
  }
}
