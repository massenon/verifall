import '../../../core/errors/failure.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../domain/entities/live_class_session.dart';
import '../../../domain/repositories/live_class_repository.dart';
import '../../datasources/local/lms/lms_local_data_source.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/remote/lms/lms_remote_data_source.dart';
import '../../models/json_reader.dart';
import '../../models/lms/seance_json.dart';

/// Le dépôt des séances en direct.
class LiveClassRepositoryImpl implements LiveClassRepository {
  LiveClassRepositoryImpl({
    required this.distant,
    required this.local,
    required this.file,
    DateTime Function()? horloge,
  }) : _horloge = horloge ?? DateTime.now;

  final LmsRemoteDataSource distant;
  final LmsCache local;
  final PendingOpsQueue file;
  final DateTime Function() _horloge;

  @override
  Future<SeancesLues> seances() async {
    try {
      final ReponseBrute<List<LiveClassSession>> reponse = await distant
          .seances();
      final DateTime maintenant = _horloge();
      await local.ecrireSeances(reponse.data, reponse.meta, maintenant);
      return SeancesLues(
        seances: reponse.valeur,
        lueLe: maintenant,
        vientDuCache: false,
      );
    } on Failure {
      final EntreeDeCache? entree = await local.lireSeances();
      if (entree == null) {
        rethrow;
      }
      return SeancesLues(
        seances: <LiveClassSession>[
          for (final Object? item
              in entree.data is List
                  ? entree.data! as List<Object?>
                  : const <Object?>[])
            if (item is Map) SeanceJson.lire(asJsonObject(item)),
        ],
        lueLe: entree.lueLe,
        vientDuCache: true,
      );
    }
  }

  @override
  Future<bool> demanderUnRappel(String uuid) async {
    try {
      await distant.demanderUnRappel(uuid);
      return true;
    } on NetworkFailure {
      // Protege par une CONTRAINTE D'UNICITE en base : rejouable sans doublon.
      await file.enqueue(
        kind: PendingOpKind.rappelSeance,
        endpoint: ApiEndpoints.liveClassRemind(uuid),
      );
      return false;
    }
  }
}
