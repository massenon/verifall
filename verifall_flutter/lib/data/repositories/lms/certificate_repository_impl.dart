import '../../../core/errors/failure.dart';
import '../../../domain/entities/certificate_summary.dart';
import '../../../domain/repositories/certificate_repository.dart';
import '../../datasources/local/lms/lms_local_data_source.dart';
import '../../datasources/remote/lms/lms_remote_data_source.dart';
import '../../models/json_reader.dart';
import '../../models/lms/certificat_json.dart';

/// Le dépôt des certificats.
///
/// Le cache est ici une **exigence**, pas une optimisation : « c'est ce qu'on
/// montre à un recruteur, souvent hors ligne ».
class CertificateRepositoryImpl implements CertificateRepository {
  CertificateRepositoryImpl({
    required this.distant,
    required this.local,
    DateTime Function()? horloge,
  }) : _horloge = horloge ?? DateTime.now;

  final LmsRemoteDataSource distant;
  final LmsCache local;
  final DateTime Function() _horloge;

  @override
  Future<CertificatsLus> mesCertificats() async {
    try {
      final ReponseBrute<List<CertificateSummary>> reponse = await distant
          .mesCertificats();
      final DateTime maintenant = _horloge();
      await local.ecrireCertificat(
        LmsLocalDataSource.cleListe,
        reponse.data,
        reponse.meta,
        maintenant,
      );
      // Chaque certificat est aussi ecrit seul : un lien profond vers
      // /certificat/<code> doit s'ouvrir hors ligne sans passer par la liste.
      for (final Map<String, Object?> json in _objets(reponse.data)) {
        final String code = JsonReader(json).string('code');
        if (code.isNotEmpty) {
          await local.ecrireCertificat(
            code,
            json,
            const <String, Object?>{},
            maintenant,
          );
        }
      }
      return CertificatsLus(
        certificats: reponse.valeur,
        lueLe: maintenant,
        vientDuCache: false,
      );
    } on Failure {
      final EntreeDeCache? entree = await local.lireCertificat(
        LmsLocalDataSource.cleListe,
      );
      if (entree == null) {
        rethrow;
      }
      return CertificatsLus(
        certificats: <CertificateSummary>[
          for (final Map<String, Object?> json in _objets(entree.data))
            CertificatJson.lire(json),
        ],
        lueLe: entree.lueLe,
        vientDuCache: true,
      );
    }
  }

  @override
  Future<CertificatLu> certificat(String code) async {
    try {
      final ReponseBrute<CertificateSummary> reponse = await distant.certificat(
        code,
      );
      final DateTime maintenant = _horloge();
      await local.ecrireCertificat(
        code,
        reponse.data,
        reponse.meta,
        maintenant,
      );
      return CertificatLu(
        certificat: reponse.valeur,
        lueLe: maintenant,
        vientDuCache: false,
      );
    } on Failure {
      final EntreeDeCache? entree = await local.lireCertificat(code);
      if (entree == null) {
        // 404 compris : « on n'ecrit jamais "faux" — un code mal recopie n'est
        // pas une fraude » (arbitrage n° 65). C'est l'ecran qui formule ; le
        // depot se contente de laisser passer l'echec nomme.
        rethrow;
      }
      return CertificatLu(
        certificat: CertificatJson.lire(asJsonObject(entree.data)),
        lueLe: entree.lueLe,
        vientDuCache: true,
      );
    }
  }

  static List<Map<String, Object?>> _objets(Object? data) =>
      <Map<String, Object?>>[
        for (final Object? item in data is List ? data : const <Object?>[])
          if (item is Map) asJsonObject(item),
      ];
}
