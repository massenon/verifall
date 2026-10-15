import '../../../core/errors/failure.dart';
import '../../../domain/entities/gamification_recompenses.dart';
import '../../../domain/repositories/gamification_repository.dart';
import '../../datasources/local/profile/compte_local_datasource.dart';
import '../../datasources/remote/profile/compte_remote_datasource.dart';
import '../../models/profile/recompenses_model.dart';

/// Récompenses, badges et certificats.
///
/// Tout est en cache : le tableau du contrat place « badges » et « certificats
/// émis » parmi ce qui reste lisible hors ligne (05-HORS-LIGNE §5.5). Montrer un
/// certificat à un recruteur dans un bureau sans wifi est l'un des trois cas
/// d'usage nommés par la documentation.
class GamificationRepositoryImpl implements GamificationRepository {
  const GamificationRepositoryImpl({
    required this.distant,
    required this.local,
  });

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final CompteRemoteDataSource distant;
  final CompteLocalDataSource local;

  @override
  Future<Recompenses> recompenses({bool forcerLeReseau = false}) async {
    try {
      final Recompenses fraiches = await distant.recompenses();
      await local.ecrire(
        RessourceDeCompte.recompenses,
        RecompensesModel.versJson(fraiches),
      );
      return fraiches;
    } on Failure {
      if (forcerLeReseau) {
        rethrow;
      }
      final EntreeDeCache? entree = await local.lire(
        RessourceDeCompte.recompenses,
      );
      if (entree == null) {
        rethrow;
      }
      return RecompensesModel.depuisJson(entree.contenu);
    }
  }

  @override
  Future<List<Certificat>> certificats({bool forcerLeReseau = false}) async {
    try {
      final List<Certificat> frais = await distant.certificats();
      await local
          .ecrireListe(RessourceDeCompte.certificats, <Map<String, Object?>>[
            for (final Certificat certificat in frais)
              RecompensesModel.certificatVersJson(certificat),
          ]);
      return frais;
    } on Failure {
      if (forcerLeReseau) {
        rethrow;
      }
      final List<Map<String, Object?>>? cache = await local.lireListe(
        RessourceDeCompte.certificats,
      );
      if (cache == null) {
        rethrow;
      }
      return <Certificat>[
        for (final Map<String, Object?> item in cache)
          RecompensesModel.certificatDepuisJson(item),
      ];
    }
  }
}
