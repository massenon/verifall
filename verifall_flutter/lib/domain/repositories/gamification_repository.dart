import '../entities/gamification_recompenses.dart';

/// Récompenses, badges et certificats.
abstract interface class GamificationRepository {
  /// `GET /me/gamification` — **non paginé**, et il recalcule les badges avant
  /// de répondre. La grille de l'écran est alimentée entièrement par cet appel :
  /// le client ne tient aucune liste de badges.
  Future<Recompenses> recompenses({bool forcerLeReseau = false});

  /// `GET /me/certificates` — **non paginé** également : « personne n'obtient
  /// trois cents certificats ».
  Future<List<Certificat>> certificats({bool forcerLeReseau = false});
}
