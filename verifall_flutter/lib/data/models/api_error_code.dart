/// Le catalogue **fermé** des codes d'erreur de l'API v1.
///
/// Source : `docs/kb/API_SPECIFICATION.md` §2.2 — treize codes, pas un de plus.
///
/// POURQUOI le client branche sur `code` et jamais sur le statut HTTP : deux
/// codes partagent parfois le même statut (`compte_inactif`,
/// `compte_en_suppression` et `droit_manquant` sont tous trois des 403 ;
/// `validation` et `action_impossible` sont tous deux des 422). Le statut ne
/// suffit donc pas à décider quoi faire.
///
/// POURQUOI il y a un cas [inconnu] : « ajouter une valeur au catalogue des
/// `code` d'erreur » est une évolution **compatible** (§6.2). Un client qui
/// planterait sur un code inconnu figerait l'API pour toujours.
enum ApiErrorCode {
  /// 401 — jeton absent, expiré ou révoqué.
  /// Purger le jeton, purger la base locale, écran de connexion.
  /// **Aucune tentative de renouvellement** : il n'y a pas de jeton de
  /// rafraîchissement dans ce contrat (§1.4).
  nonAuthentifie('non_authentifie', 401),

  /// 402 — pas assez d'unités. **Proposer une recharge**, pas afficher une
  /// erreur : c'est le seul code HTTP qui dise « il manque de quoi payer », et
  /// il est employé exprès pour cela (§2.3).
  soldeInsuffisant('solde_insuffisant', 402),

  /// 403 — le code de vérification n'a jamais été validé.
  /// À noter : à la *connexion*, un compte non activé reçoit 401, identique à un
  /// mot de passe faux (§1.6). Ce code n'apparaît donc que sur un jeton déjà
  /// émis.
  compteInactif('compte_inactif', 403),

  /// 403 — délai de rétractation RGPD en cours. Toute route répond ainsi, sauf
  /// `GET /me` et `POST /auth/logout` (§1.7).
  compteEnSuppression('compte_en_suppression', 403),

  /// 403 — l'action existe mais pas pour vous. Afficher le message tel quel.
  droitManquant('droit_manquant', 403),

  /// 404 — ressource inexistante **ou pas à vous**.
  /// Conséquence pour les écrans : un 404 ne signifie pas « supprimé », il
  /// signifie « pas pour vous, ou pas là ». Ne jamais écrire « ce contenu a été
  /// supprimé », qui serait une affirmation (§9 du contrat).
  introuvable('introuvable', 404),

  /// 405 — mauvais verbe HTTP. C'est un bogue client : à faire remonter, pas à
  /// afficher.
  methodeRefusee('methode_refusee', 405),

  /// 422 — champs invalides. `details` est présent **uniquement** ici.
  validation('validation', 422),

  /// 422 — l'action n'a pas de sens en l'état. Afficher le message tel quel : il
  /// contient l'information utile (combien de secondes de lecture manquent, par
  /// exemple).
  actionImpossible('action_impossible', 422),

  /// 409 — déjà fait, ou déjà en cours.
  conflit('conflit', 409),

  /// 426 — version trop ancienne. Écran bloquant « mettez à jour l'application »
  /// (§6.4). 426 et non 400 : « c'est la différence entre quelqu'un qui agit et
  /// quelqu'un qui désinstalle. »
  clientObsolete('client_obsolete', 426),

  /// 429 — plafond atteint. Attendre `meta.retry_after` secondes, puis **un
  /// seul** essai (§5.4).
  debitDepasse('debit_depasse', 429),

  /// 500 — anomalie côté serveur.
  erreurServeur('erreur_serveur', 500),

  /// Non prévu par le contrat au moment où ce client a été écrit.
  /// Le message du serveur reste affichable ; seul l'aiguillage retombe sur un
  /// traitement neutre.
  inconnu('', 0);

  const ApiErrorCode(this.wireValue, this.httpStatus);

  /// La valeur telle qu'elle voyage sur le réseau.
  final String wireValue;

  /// Le statut HTTP que le contrat associe au code. **Indicatif** : c'est
  /// [wireValue] qui fait foi côté client.
  final int httpStatus;

  static ApiErrorCode parse(String? raw) {
    if (raw == null || raw.isEmpty) {
      return ApiErrorCode.inconnu;
    }
    for (final ApiErrorCode code in ApiErrorCode.values) {
      if (code.wireValue == raw) {
        return code;
      }
    }
    return ApiErrorCode.inconnu;
  }

  /// L'échec est-il imputable à la session plutôt qu'à l'écran ?
  bool get invalidatesSession => this == ApiErrorCode.nonAuthentifie;

  /// Faut-il proposer la recharge d'unités plutôt qu'une erreur ?
  bool get suggestsTopUp => this == ApiErrorCode.soldeInsuffisant;
}
