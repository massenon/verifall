import '../entities/settings_reglages.dart';

/// Réglages, export RGPD et suppression de compte.
abstract interface class SettingsRepository {
  /// `GET /me/settings`.
  Future<Reglages> reglages({bool forcerLeReseau = false});

  /// `PATCH /me/settings` — **une valeur voulue, jamais une bascule**.
  ///
  /// « Une bascule perd sa cible quand la requête est rejouée après une coupure,
  /// et l'utilisateur retrouve le réglage inverse de ce qu'il a demandé. »
  Future<Reglages> definirCanal({required String cle, required bool valeur});

  /// `PATCH /me/settings` — le consentement marketing, séparé des préférences.
  Future<Reglages> definirNewsletter({required bool valeur});

  /// La même écriture, mise en file pour un envoi au retour du réseau.
  ///
  /// C'est le seul acte de cet écran qui a le droit d'attendre : ni la 2FA, ni
  /// l'export, ni la suppression ne se mettent en file — « une suppression de
  /// compte rejouée trois jours plus tard, alors que la personne a changé
  /// d'avis, est irrattrapable ».
  Future<void> definirPlusTard({required Map<String, Object?> corps});

  /// `POST /me/export` — `202`, préparation en tâche de fond.
  ///
  /// L'archive n'est **jamais** renvoyée dans la réponse : « elle contient tout
  /// ce que la plateforme détient d'une personne, et un corps HTTP se retrouve
  /// dans les journaux d'un mandataire ». Une seconde demande dans le quart
  /// d'heure répond `409 conflit` : afficher le message du serveur, sans
  /// relancer.
  Future<String> exporterMesDonnees();

  /// `DELETE /me` — en **deux temps**, sur un seul point d'entrée.
  ///
  /// Sans [code] : le serveur envoie un code par courriel et répond `202`.
  /// Rien n'est supprimé. Avec [code] : la suppression est programmée.
  Future<SuppressionCompte> supprimerMonCompte({
    required String confirmation,
    required String motDePasse,
    String? code,
  });
}
