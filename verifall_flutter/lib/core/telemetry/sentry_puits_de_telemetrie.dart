import 'package:sentry_flutter/sentry_flutter.dart';

import 'incident.dart';
import 'puits_de_telemetrie.dart';

/// Implémentation Sentry de [PuitsDeTelemetrie].
///
/// # Ce que fait cette classe, et ce qu'elle ne fait PAS
///
/// Elle **reçoit** un [Incident] déjà expurgé par [RapporteurDePlantage] —
/// `LogRedactor.redactFreeText` a déjà masqué les adresses, les jetons et
/// les suites de chiffres. Cette classe se contente de le remettre au SDK
/// Sentry, sans aucun retraitement : deux passes de rédaction sur le même
/// texte ne font pas mieux que la première, et pourraient manquer quelque
/// chose que la première a décidé de garder.
///
/// # Pourquoi `attachFlutterErrorHandler: false` dans l'init Sentry
///
/// [RapporteurDePlantage] installe déjà `FlutterError.onError` et
/// `PlatformDispatcher.onError`. Laisser Sentry en installer un deuxième
/// produirait deux entrées pour chaque incident : une expurgée (la nôtre),
/// une brute (la sienne). La version brute peut contenir des données
/// personnelles que la rédaction aurait retirées. C'est la seule raison pour
/// laquelle l'init désactive le handler automatique.
class SentryPuitsDetelemetrie implements PuitsDeTelemetrie {
  const SentryPuitsDetelemetrie();

  @override
  Future<void> envoyer(Incident incident) async {
    await Sentry.captureEvent(
      SentryEvent(
        message: SentryMessage(incident.message),
        level: SentryLevel.error,
        tags: <String, String>{
          'nature': incident.nature.name,
          'installation': incident.identifiantDInstallation,
        },
        release: incident.versionApplication,
        platform: incident.plateforme,
        timestamp: incident.survenuLe,
      ),
      stackTrace: incident.pileDAppels,
    );
  }
}
