import 'app_config.dart';

/// Les adresses du **site public**, composées à partir de la configuration.
///
/// # Pourquoi ces chemins-ci, et pas ceux du simulateur
///
/// Le simulateur mobile affiche `verifall.com/verify-certificate`. Ce chemin
/// n'existe pas : le site sert `/verifier-un-certificat` et `/confidentialite`
/// (`routes/web.php`, routes nommées `public.verify-certificate` et
/// `public.privacy`). Les chemins ci-dessous sont **relevés du serveur**, pas
/// recopiés d'une maquette — un lien qui tombe sur un 404 est pire que du texte.
///
/// # Pourquoi tout rend `null` quand l'adresse manque
///
/// [AppConfig.webBaseUrl] est vide par défaut, et « composer une adresse web à
/// partir de celle de l'API reviendrait à inventer une URL de production ».
/// Chaque appelant reçoit donc `null` et **n'affiche pas de lien** — il garde le
/// texte, qui reste vrai. Arbitrage n° 138.
abstract final class VfWebLinks {
  /// La page publique de vérification d'un certificat.
  ///
  /// Elle s'ouvre **avec le code**, pas avec un uuid : « il est conçu pour être
  /// lu à voix haute et recopié ».
  static Uri? verificationDeCertificat(AppConfig config, String code) =>
      _compose(config, '/verifier-un-certificat', <String, String>{
        'code': code,
      });

  /// La politique de confidentialité.
  static Uri? confidentialite(AppConfig config) =>
      _compose(config, '/confidentialite', null);

  /// L'adresse **affichable** de la page de vérification, sans le code.
  ///
  /// Sert au texte de l'encart, qui la cite même quand elle n'est pas cliquable.
  static String affichageVerificationDeCertificat(AppConfig config) =>
      config.hasWebSite
      ? '${_hote(config)}/verifier-un-certificat'
      : 'verifall.com/verifier-un-certificat';

  static Uri? _compose(
    AppConfig config,
    String chemin,
    Map<String, String>? parametres,
  ) {
    if (!config.hasWebSite) {
      return null;
    }
    final Uri? base = Uri.tryParse(config.webBaseUrl);
    if (base == null || !base.hasScheme) {
      return null;
    }
    return base.replace(path: chemin, queryParameters: parametres);
  }

  /// L'hôte sans le schéma — ce qu'on montre à quelqu'un, plutôt que
  /// `https://`.
  static String _hote(AppConfig config) {
    final Uri? base = Uri.tryParse(config.webBaseUrl);
    return base == null || base.host.isEmpty ? config.webBaseUrl : base.host;
  }
}
