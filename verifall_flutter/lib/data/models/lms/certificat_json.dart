import '../../../domain/entities/certificate_summary.dart';
import '../json_reader.dart';

/// `CertificatResource`.
abstract final class CertificatJson {
  static CertificateSummary lire(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    return CertificateSummary(
      code: r.string('code'),
      // `titulaire` et `cours_titre` sont FIGES a l'emission : ce sont les
      // valeurs du certificat, pas celles du compte aujourd'hui.
      titulaire: r.string('titulaire'),
      coursTitre: r.string('cours_titre'),
      coursSlug: r.stringOrNull('cours_slug'),
      delivreLe: r.dateTimeOrNull('delivre_le'),
      revoque: r.boolValue('revoque'),
      revoqueLe: r.dateTimeOrNull('revoque_le'),
    );
  }
}
