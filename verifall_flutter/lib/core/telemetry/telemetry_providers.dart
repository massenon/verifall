import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../logging/logging_providers.dart';
import '../network/network_providers.dart';
import 'identifiant_installation.dart';
import 'puits_de_telemetrie.dart';
import 'rapporteur_de_plantage.dart';

/// L'identifiant anonyme d'installation, partagé.
final Provider<IdentifiantDInstallation> identifiantDInstallationProvider =
    Provider<IdentifiantDInstallation>((Ref ref) => IdentifiantDInstallation());

/// Le puits vers lequel les incidents quittent l'appareil.
///
/// **Nul aujourd'hui, et c'est le comportement correct** : `VF_TELEMETRY_DSN`
/// est vide et aucun consentement de télémétrie n'existe encore. Brancher un
/// fournisseur consistera à redéfinir ce provider dans `main.dart`, sans
/// toucher au reste.
final Provider<PuitsDeTelemetrie?> puitsDeTelemetrieProvider =
    Provider<PuitsDeTelemetrie?>((Ref ref) => null);

/// La porte de consentement aux traces.
///
/// **Fermée par défaut.** L'écran 03 porte trois consentements — CGU,
/// confidentialité, infolettre — et aucun ne couvre la télémétrie, alors que le
/// §7.6 exige que la collecte y soit soumise. Tant que la case n'existe pas, la
/// seule réponse honnête est « non ».
///
/// Cela n'empêche RIEN de fonctionner : les incidents sont journalisés
/// localement quoi qu'il arrive. Seule leur transmission attend.
final Provider<PorteDeConsentement> consentementTelemetrieProvider =
    Provider<PorteDeConsentement>(
      (Ref ref) =>
          () => false,
    );

/// L'identifiant d'installation, RÉSOLU.
///
/// Même patron que `clientVersionProvider` : la lecture est asynchrone, elle a
/// lieu une seule fois au démarrage, et le reste du code lit la valeur.
///
/// Pourquoi il faut qu'elle soit résolue AVANT : un gestionnaire d'erreur global
/// ne peut pas attendre une `Future`. Il doit être prêt à la première exception,
/// y compris celle qui survient pendant le démarrage.
final Provider<String> identifiantResoluProvider = Provider<String>(
  (Ref ref) => throw StateError(
    'identifiantResoluProvider doit être surchargé au démarrage.',
  ),
);

/// Le rapporteur de plantage.
final Provider<RapporteurDePlantage> rapporteurDePlantageProvider =
    Provider<RapporteurDePlantage>(
      (Ref ref) => RapporteurDePlantage(
        journal: ref.watch(appLoggerProvider),
        identifiantDInstallation: ref.watch(identifiantResoluProvider),
        versionApplication: ref.watch(clientVersionProvider),
        consentement: ref.watch(consentementTelemetrieProvider),
        puits: ref.watch(puitsDeTelemetrieProvider),
      ),
    );
