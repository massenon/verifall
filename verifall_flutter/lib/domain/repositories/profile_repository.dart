import '../entities/profile_moi.dart';
import '../entities/stats_statistiques.dart';

/// Mon profil et mes statistiques.
///
/// # Pourquoi les statistiques vivent ici et non dans un dépôt à elles
///
/// Le découpage des parcours (§8.6) nomme cinq dépôts pour M5 — `profile`,
/// `wallet`, `gamification`, `referral`, `settings` — et il n'y a pas de dépôt
/// « stats ». `GET /me/stats` est une lecture de **mon compte** : la ranger ici
/// évite de créer un fichier que la liste des fichiers en écriture n'autorise
/// pas, et évite surtout un sixième dépôt pour une seule méthode.
abstract interface class ProfileRepository {
  /// `GET /me`.
  ///
  /// [forcerLeReseau] à faux autorise l'affichage immédiat du cache. Le solde,
  /// lui, **n'est jamais servi depuis le cache** : un profil venu de la base
  /// locale porte `unites == null` (contrat §7.1).
  Future<Moi> moi({bool forcerLeReseau = false});

  /// Le dernier profil connu, ou `null`. **Sans solde**, toujours.
  Future<Moi?> moiEnCache();

  /// `PATCH /me` — seuls les champs présents sont envoyés.
  Future<Moi> mettreAJour(ModificationProfil modification);

  /// Met la modification en file d'écriture, pour un envoi au retour du réseau.
  ///
  /// POURQUOI c'est une méthode distincte et pas un repli silencieux : « ne
  /// jamais faire croire qu'une écriture a abouti » (05-HORS-LIGNE §5.7).
  /// L'écran doit pouvoir dire que l'enregistrement partira plus tard.
  Future<void> mettreAJourPlusTard(ModificationProfil modification);

  /// `GET /me/stats?period=` — la période est une liste fermée.
  Future<Statistiques> statistiques({
    PeriodeStats periode = PeriodeStats.trenteJours,
    bool forcerLeReseau = false,
  });
}
