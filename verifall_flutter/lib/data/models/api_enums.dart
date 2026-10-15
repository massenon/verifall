/// Les énumérations que le contrat fixe **en toutes lettres**, et elles seules.
///
/// POURQUOI ce fichier est court alors que l'application affiche beaucoup d'états
/// (verdicts, statuts d'analyse, types d'opportunité, échéances) : le contrat
/// pose une convention qui rend inutile — et dangereux — de les figer ici.
///
/// > « **Énumérations : valeur technique et libellé français voyagent
/// > ensemble.** `"verdict": "false"`, `"verdict_libelle": "Faux"`. Le client
/// > affiche le libellé et branche sur la valeur. **Il ne fabrique jamais le
/// > libellé lui-même** : ajouter un cas côté serveur casserait tous les
/// > `switch` du client. » (§8)
///
/// Le contrat ne publie pas la liste complète des valeurs techniques de
/// `verdict` ; en inventer une reviendrait à deviner une forme de réponse. Le
/// socle fournit donc, côté présentation, `VfVerdictTone` — un jeu de styles avec
/// un cas neutre — et laisse le parcours M2 établir la correspondance contre le
/// serveur réel, une fois, au même endroit.
library;

/// Les motifs de signalement — **liste fermée**, donnée telle quelle (§10.5).
///
/// « Signaler deux fois ne crée qu'une ligne » : l'unicité est en base, donc
/// l'opération est rejouable sans clé d'idempotence.
enum MotifSignalement {
  desinformation('desinformation'),
  haine('haine'),
  arnaque('arnaque'),
  spam('spam'),
  donnees('donnees'),
  autre('autre');

  const MotifSignalement(this.wireValue);

  final String wireValue;

  static MotifSignalement? parse(String? raw) {
    for (final MotifSignalement motif in MotifSignalement.values) {
      if (motif.wireValue == raw) {
        return motif;
      }
    }
    return null;
  }
}

/// `GET /me/stats?period=` accepte **ces quatre valeurs et rien d'autre**
/// (§10.6).
///
/// POURQUOI la liste est fermée côté serveur : « une valeur libre laisserait
/// demander cent mille jours d'historique ». Le client n'a donc aucune raison
/// d'en construire une.
enum PeriodeStats {
  sept('7d'),
  trente('30d'),
  quatreVingtDix('90d'),
  troisCentSoixanteCinq('365d');

  const PeriodeStats(this.wireValue);

  final String wireValue;
}

/// L'étape rendue par les parcours en deux temps.
///
/// Deux valeurs sont écrites noir sur blanc dans le contrat : `code_envoye`
/// (§10.1, §10.2) et `suppression_programmee` (§10.2). Il n'y en a pas d'autres
/// de documentées, donc il n'y en a pas d'autres ici.
enum EtapeServeur {
  codeEnvoye('code_envoye'),
  suppressionProgrammee('suppression_programmee'),

  /// Valeur non prévue au moment où ce client a été écrit. Ajouter une valeur
  /// est une évolution compatible (§6.2) : le client ne doit pas tomber.
  inconnue('');

  const EtapeServeur(this.wireValue);

  final String wireValue;

  static EtapeServeur parse(String? raw) {
    for (final EtapeServeur etape in EtapeServeur.values) {
      if (etape.wireValue == raw && etape != EtapeServeur.inconnue) {
        return etape;
      }
    }
    return EtapeServeur.inconnue;
  }
}
