import 'incident.dart';

/// Où partent les incidents **quand ils quittent l'appareil**.
///
/// # Pourquoi une interface plutôt qu'un fournisseur nommé
///
/// Le §7.6 dit ce qu'on collecte et ce qu'on ne collecte JAMAIS ; il ne nomme
/// aucun service. Écrire Sentry — ou tout autre — dans le cœur de
/// l'application ferait choisir un fournisseur par un détail d'implémentation,
/// et rendrait le changement coûteux le jour où le porteur en décide autrement.
///
/// La partie qui ne s'achète pas — la rédaction, la porte de consentement,
/// l'identifiant anonyme purgé à la déconnexion — vit donc dans
/// `RapporteurDePlantage`. Brancher un fournisseur, c'est écrire une classe qui
/// implémente cette interface, et rien d'autre.
///
/// # Aucun puits n'est branché aujourd'hui, et c'est délibéré
///
/// Deux raisons, dont chacune suffirait :
///
///   1. **`VF_TELEMETRY_DSN` est vide.** Il n'y a nulle part où envoyer.
///   2. **Aucun consentement de télémétrie n'existe.** L'écran 03 en porte
///      trois — CGU, confidentialité, infolettre — et aucun ne couvre les
///      traces, alors que le §7.6 exige que la collecte y soit soumise.
///
/// En attendant, `RapporteurDePlantage` journalise localement : un incident ne
/// quitte pas l'appareil, mais il ne DISPARAÎT plus. C'était le cas avant ce
/// lot, et c'était le vrai défaut.
abstract interface class PuitsDeTelemetrie {
  Future<void> envoyer(Incident incident);
}
