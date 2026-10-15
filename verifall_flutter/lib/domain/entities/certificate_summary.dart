import 'package:flutter/foundation.dart';

/// Un certificat délivré — `CertificatResource`.
///
/// # Deux règles qui ne se négocient pas
///
/// **Le nom du titulaire et le titre du cours sont figés à l'émission**
/// (arbitrage n° 40, colonnes `holder_name` et `course_title`). L'écran affiche
/// ce que porte le certificat, **pas** les valeurs courantes du compte : « un
/// diplôme atteste de ce qui a été suivi ce jour-là — il ne doit ni mentir
/// après un renommage du cours, ni redevenir nominatif après un effacement
/// RGPD ».
///
/// **Le certificat s'ouvre par son `code` public**, pas par un uuid. C'est
/// l'exception assumée du contrat à la règle « uuid partout » : « son code est
/// conçu pour être lu à voix haute et recopié (alphabet sans O/0, I/1, S/5).
/// C'est lui qu'un recruteur saisit. » Le client n'en génère **aucun**.
@immutable
class CertificateSummary {
  const CertificateSummary({
    required this.code,
    required this.titulaire,
    required this.coursTitre,
    required this.revoque,
    this.coursSlug,
    this.delivreLe,
    this.revoqueLe,
  });

  final String code;

  /// Le nom **au moment de la délivrance**.
  final String titulaire;

  /// Le titre du cours **au moment de la délivrance**.
  final String coursTitre;

  final String? coursSlug;
  final DateTime? delivreLe;
  final bool revoque;
  final DateTime? revoqueLe;
}
