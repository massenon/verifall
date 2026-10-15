/// Les statuts d'analyse que le serveur émet, relevés sur l'implémentation
/// réelle (`App\Enums\StatutVerification`).
///
/// # Pourquoi une énumération ici, alors que le libellé vient du serveur
///
/// Le libellé **n'est jamais fabriqué** : il arrive dans `statut_libelle` et
/// s'affiche tel quel. Cette énumération sert uniquement à **brancher un
/// comportement** — rediriger vers le suivi ou vers le rapport, annoncer une
/// reprise, mentionner un remboursement. C'est exactement l'usage que le
/// contrat prescrit : « le client affiche le libellé et branche sur la
/// valeur ».
///
/// # Pourquoi il y a un cas [inconnu]
///
/// Ajouter un statut côté serveur est une évolution compatible (§6.2). Un
/// `switch` sans branche par défaut planterait à l'exécution — c'est le piège
/// qui a déjà coûté une correction côté web (arbitrage n° 38).
enum StatutVerification {
  /// `queued` — soumise, pas encore prise en charge.
  enFile('queued'),

  /// `analyzing` — les moteurs travaillent.
  enCours('analyzing'),

  /// `retry_scheduled` — les moteurs n'ont pas pu répondre, l'analyse
  /// **reprendra d'elle-même**.
  ///
  /// Ce n'est pas un échec, et l'écran ne doit pas le présenter comme tel :
  /// « rendre un rapport creux serait la pire réponse possible ».
  repriseProgrammee('retry_scheduled'),

  /// `completed` — un rapport existe.
  terminee('completed'),

  /// `human_review` — les moteurs se contredisent. Le rapport existe, il est
  /// consultable par son auteur, il n'est **pas publié**.
  arbitrageHumain('human_review'),

  /// `failed` — tentatives épuisées. Le serveur a rendu les unités.
  echouee('failed'),

  /// Un statut que cette version du client ne connaît pas encore.
  inconnu('');

  const StatutVerification(this.wireValue);

  final String wireValue;

  static StatutVerification parse(String? raw) {
    for (final StatutVerification statut in StatutVerification.values) {
      if (statut != StatutVerification.inconnu && statut.wireValue == raw) {
        return statut;
      }
    }
    return StatutVerification.inconnu;
  }

  /// L'analyse est-elle encore en marche du point de vue de la personne ?
  ///
  /// POURQUOI [repriseProgrammee] en fait partie : du point de vue de qui a
  /// posé la question, sa demande est toujours en cours. L'en exclure lui
  /// ferait croire qu'elle s'est volatilisée.
  bool get estEnMarche => switch (this) {
    StatutVerification.enFile ||
    StatutVerification.enCours ||
    StatutVerification.repriseProgrammee => true,
    _ => false,
  };

  /// Le serveur a-t-il rendu les unités, de façon **certaine** ?
  ///
  /// Vrai pour le seul cas où l'implémentation rembourse sans condition : la
  /// renonciation après épuisement des tentatives. Les autres remboursements —
  /// analyse partielle, réponse déjà connue — sont annoncés par le serveur
  /// lui-même dans `rapport.reserve`, et l'écran se contente de la relayer.
  ///
  /// POURQUOI cette prudence : le champ `refunded_at` n'est pas sérialisé.
  /// Affirmer un remboursement qu'on ne peut pas lire serait exactement le
  /// « rapport creux » que la règle du chantier proscrit.
  bool get remboursementCertain => this == StatutVerification.echouee;
}
