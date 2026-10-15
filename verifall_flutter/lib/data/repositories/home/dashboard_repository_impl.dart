import '../../../core/errors/failure.dart';
import '../../../domain/entities/home_tableau_de_bord.dart';
import '../../../domain/repositories/dashboard_repository.dart';
import '../../datasources/local/home/dashboard_local_source.dart';
import '../../datasources/remote/home/dashboard_remote_source.dart';

/// Le dépôt de l'écran d'accueil.
///
/// # Le cycle de lecture, identique partout (05-HORS-LIGNE §5.4)
///
/// 1. lire le cache et **afficher immédiatement** ;
/// 2. lancer la requête réseau ;
/// 3. si elle réussit, écrire le cache et rafraîchir ;
/// 4. si elle échoue, garder l'affichage et poser un bandeau d'horodatage.
///
/// **Une exception impérative** : le solde d'unités ne sort jamais du cache. Il
/// vaut donc `null` sur un affichage servi par Drift, et la pastille affiche un
/// tiret — « mieux vaut un tiret honnête qu'un zéro qui ferait croire à un
/// compte vide ».
class DashboardRepositoryImpl implements DashboardRepository {
  const DashboardRepositoryImpl({required this.distant, required this.local});

  final DashboardRemoteSource distant;
  final DashboardLocalSource local;

  @override
  Future<AccueilCitoyen> lire() async {
    try {
      final TableauDeBord tableau = await distant.tableauDeBord();
      await local.ecrire(tableau);

      // La reprise vient d'un AUTRE endpoint : son echec ne doit pas faire
      // tomber tout l'accueil. Une carte manquante vaut mieux qu'un ecran
      // d'erreur sur un tableau de bord par ailleurs complet.
      ({bool lue, RepriseDeCours? reprise}) reprise = (
        lue: false,
        reprise: null,
      );
      try {
        reprise = await distant.reprise();
        await local.ecrireLaReprise(reprise.reprise);
      } on Failure {
        reprise = await local.lireLaReprise();
      }

      return AccueilCitoyen(
        tableau: tableau,
        repriseLue: reprise.lue,
        reprise: reprise.reprise,
      );
    } on Failure {
      final AccueilCitoyen? enCache = await lireLeCache();
      if (enCache == null) {
        // Pas de cache : c'est une vraie erreur, avec un bouton « Reessayer ».
        // « Jamais un tableau de bord vide silencieux. »
        rethrow;
      }
      return enCache;
    }
  }

  @override
  Future<AccueilCitoyen?> lireLeCache() async {
    final TableauDeBord? tableau = await local.lire();
    if (tableau == null) {
      return null;
    }
    final ({bool lue, RepriseDeCours? reprise}) reprise = await local
        .lireLaReprise();
    return AccueilCitoyen(
      tableau: tableau,
      repriseLue: reprise.lue,
      reprise: reprise.reprise,
      venuDuCache: true,
    );
  }
}
