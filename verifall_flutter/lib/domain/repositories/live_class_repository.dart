import '../entities/live_class_session.dart';

/// Le dépôt des séances en direct.
abstract interface class LiveClassRepository {
  /// La liste **entière**, non paginée (contrat §3.3).
  ///
  /// POURQUOI on ne passe pas `state` au serveur alors qu'il l'accepte : la
  /// liste est fermée, et la répartir en trois onglets côté client est la seule
  /// façon que les trois onglets marchent **hors ligne**. L'interdiction de
  /// filtrer localement ne vaut que pour les listes **paginées** (§3.4), où le
  /// filtre local ne porterait que sur la page chargée.
  Future<SeancesLues> seances();

  /// Demander un rappel. Rend `false` quand l'acte est parti **en file**.
  ///
  /// L'écriture est protégée par une contrainte d'unicité en base, donc
  /// rejouable sans doublon.
  Future<bool> demanderUnRappel(String uuid);
}

class SeancesLues {
  const SeancesLues({
    required this.seances,
    required this.lueLe,
    required this.vientDuCache,
  });

  final List<LiveClassSession> seances;
  final DateTime lueLe;
  final bool vientDuCache;
}
