import '../../../core/errors/failure.dart';
import '../../../domain/entities/profile_moi.dart';
import '../../../domain/entities/stats_statistiques.dart';
import '../../../domain/repositories/profile_repository.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/local/profile/compte_local_datasource.dart';
import '../../datasources/remote/profile/compte_remote_datasource.dart';
import '../../models/profile/moi_model.dart';
import '../../models/profile/statistiques_model.dart';
import '../../../core/network/api_endpoints.dart';

/// Mon profil et mes statistiques.
///
/// # Le cycle de lecture, identique partout (05-HORS-LIGNE §5.4)
///
/// 1. lire le cache et **afficher immédiatement** ;
/// 2. lancer la requête réseau ;
/// 3. si elle réussit, écrire le cache et rafraîchir ;
/// 4. si elle échoue, garder l'affichage et laisser l'écran poser le bandeau
///    d'horodatage.
///
/// Le dépôt rend donc soit une valeur fraîche, soit une valeur de cache — et
/// l'entité porte la date qui permet de les distinguer. **Le solde échappe à ce
/// cycle** : il n'est pas écrit en cache, donc il ne peut pas en ressortir.
class ProfileRepositoryImpl implements ProfileRepository {
  const ProfileRepositoryImpl({
    required this.distant,
    required this.local,
    required this.file,
  });

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final CompteRemoteDataSource distant;
  final CompteLocalDataSource local;
  final PendingOpsQueue file;

  @override
  Future<Moi> moi({bool forcerLeReseau = false}) async {
    try {
      final Moi frais = await distant.moi();
      // Le cache recoit le profil PRIVE DE SON SOLDE. C'est ce qui rend la
      // regle du contrat mecanique plutot que declarative.
      await local.ecrire(
        RessourceDeCompte.profil,
        MoiModel.versJson(frais.sansSolde()),
      );
      return frais;
    } on Failure catch (echec) {
      if (forcerLeReseau) {
        rethrow;
      }
      final Moi? cache = await moiEnCache();
      if (cache == null) {
        rethrow;
      }
      // Une session invalide ne se rattrape pas par le cache : le socle vient
      // de purger la base, et afficher un profil serait afficher celui de
      // quelqu'un qui n'est plus connecte.
      if (echec is UnauthenticatedFailure) {
        rethrow;
      }
      return cache;
    }
  }

  @override
  Future<Moi?> moiEnCache() async {
    final EntreeDeCache? entree = await local.lire(RessourceDeCompte.profil);
    if (entree == null) {
      return null;
    }
    return MoiModel.depuisJson(entree.contenu, avecSolde: false);
  }

  @override
  Future<Moi> mettreAJour(ModificationProfil modification) async {
    final Moi frais = await distant.mettreAJour(modification);
    await local.ecrire(
      RessourceDeCompte.profil,
      MoiModel.versJson(frais.sansSolde()),
    );
    return frais;
  }

  @override
  Future<void> mettreAJourPlusTard(ModificationProfil modification) async {
    // `PATCH /me` figure dans la liste FERMEE des actes que le contrat autorise
    // a mettre en file (7.2). Il y survit parce que la derniere ecriture gagne
    // cote serveur : rejouer la meme modification deux fois donne le meme etat.
    await file.enqueue(
      kind: PendingOpKind.modifierProfil,
      endpoint: ApiEndpoints.me,
      method: 'PATCH',
      payload: modification.versCorps(),
    );
  }

  @override
  Future<Statistiques> statistiques({
    PeriodeStats periode = PeriodeStats.trenteJours,
    bool forcerLeReseau = false,
  }) async {
    try {
      final Statistiques fraiches = await distant.statistiques(periode);
      await local.ecrire(
        RessourceDeCompte.statistiques,
        StatistiquesModel.versJson(fraiches),
      );
      return fraiches;
    } on Failure {
      if (forcerLeReseau) {
        rethrow;
      }
      final EntreeDeCache? entree = await local.lire(
        RessourceDeCompte.statistiques,
      );
      if (entree == null) {
        rethrow;
      }
      // La date de CALCUL est celle de la lecture qui a rempli le cache, pas
      // celle d'aujourd'hui : « une statistique perimee presentee comme
      // actuelle est un indicateur faux ».
      return StatistiquesModel.depuisJson(
        entree.contenu,
        calculeLe: entree.recupereLe,
      );
    }
  }
}
