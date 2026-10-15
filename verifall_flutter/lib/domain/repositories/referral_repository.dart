import '../entities/referral_parrainage.dart';

/// Le parrainage.
abstract interface class ReferralRepository {
  /// `GET /me/referrals` — code, lien, indicateurs et filleuls.
  Future<Parrainage> parrainage({bool forcerLeReseau = false});

  /// `POST /me/referrals/invite` — rend le code et le lien à partager.
  ///
  /// **Il ne compte rien.** Le seul compteur honnête du parrainage est le nombre
  /// de personnes qui ont *ouvert* une invitation, et il s'incrémente sur la
  /// page publique. L'incrémenter à l'ouverture de l'écran fabriquerait un
  /// indicateur qui mesure des consultations de son propre code.
  Future<Invitation> inviter();
}
