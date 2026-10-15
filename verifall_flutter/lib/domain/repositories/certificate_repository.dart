import '../entities/certificate_summary.dart';

/// Le dépôt des certificats.
///
/// « Le certificat lui-même est en cache et **doit** l'être : c'est ce qu'on
/// montre à un recruteur, souvent hors ligne. »
///
/// **Aucune méthode de vérification d'un certificat tiers.** Le contrat est
/// explicite : « la vérification publique d'un certificat existe sur le site ;
/// cette route ne doit pas devenir un second oracle où l'on énumère des
/// codes ». L'application ne vérifie donc pas le certificat de quelqu'un d'autre.
///
/// **Aucune méthode de PDF.** `GET /me/certificates/{code}/pdf` n'a aucun
/// endpoint (contrat §11, arbitrage n° 73) ; l'écrire « en attendant »
/// produirait un client qui appelle du vide.
abstract interface class CertificateRepository {
  /// La liste, **non paginée** (liste fermée).
  Future<CertificatsLus> mesCertificats();

  /// Un certificat par son **code public**.
  Future<CertificatLu> certificat(String code);
}

class CertificatsLus {
  const CertificatsLus({
    required this.certificats,
    required this.lueLe,
    required this.vientDuCache,
  });

  final List<CertificateSummary> certificats;
  final DateTime lueLe;
  final bool vientDuCache;
}

class CertificatLu {
  const CertificatLu({
    required this.certificat,
    required this.lueLe,
    required this.vientDuCache,
  });

  final CertificateSummary certificat;
  final DateTime lueLe;
  final bool vientDuCache;
}
