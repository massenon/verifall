import 'dart:async';
import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

import '../logging/app_logger.dart';
import '../logging/log_redactor.dart';
import 'incident.dart';
import 'puits_de_telemetrie.dart';

/// Répond « la personne a-t-elle consenti aux traces ? ».
///
/// Injectée, et non lue d'un réglage global, pour une raison précise : **le
/// consentement de télémétrie n'existe pas encore**. L'écran 03 en porte trois
/// — CGU, confidentialité, infolettre — et aucun ne couvre les traces, alors
/// que le §7.6 exige que la collecte y soit soumise.
///
/// Le jour où la case est ajoutée, on branche ici la vraie lecture. D'ici là le
/// défaut est `false`, et c'est le seul défaut défendable : une télémétrie
/// activée par défaut, dans un produit qui reproche aux plateformes de collecter
/// sans demander, serait une contradiction visible.
typedef PorteDeConsentement = bool Function();

/// La télémétrie de plantage.
///
/// # Ce qui manquait entièrement avant ce lot
///
/// L'application n'installait **aucun** gestionnaire d'erreur global :
/// `FlutterError.onError`, `PlatformDispatcher.instance.onError` et
/// `runZonedGuarded` étaient absents du code. Une exception non capturée
/// s'imprimait dans la console de développement et disparaissait — sur un
/// téléphone d'utilisateur, elle ne laissait aucune trace nulle part.
///
/// Le champ `telemetryDsn` d'`AppConfig` était lu depuis l'environnement et
/// **aucun fichier ne le consommait**.
///
/// # Deux gestes distincts, et il ne faut pas les confondre
///
/// **Journaliser** est local : l'incident reste sur l'appareil, comme tout ce
/// qu'écrit déjà `AppLogger`. Cela se fait TOUJOURS — c'est précisément ce qui
/// manquait, et cela ne relève d'aucun consentement puisque rien ne part.
///
/// **Transmettre** fait quitter l'appareil. Cela exige les deux portes :
///
///   1. **le consentement** — c'est le §7.6 ;
///   2. **un puits configuré** — sans destination, il n'y a nulle part où
///      envoyer.
///
/// Confondre les deux serait une faute dans les deux sens : taire un incident
/// en local parce qu'on n'a pas le consentement priverait le développement
/// d'une trace qui ne regarde personne d'autre ; transmettre sans consentement
/// serait exactement ce que ce produit reproche aux plateformes.
///
/// # Ce qui n'est JAMAIS transmis (§7.6, liste absolue)
///
/// Le message et la pile passent par `LogRedactor.redactFreeText`, qui masque
/// déjà les adresses e-mail, les jetons porteurs et les suites de six chiffres
/// ou plus — donc les téléphones et les codes à usage unique. Le contenu soumis
/// à vérification n'entre pas ici : une exception ne le porte pas, et la
/// rédaction couvre le cas où un message l'aurait recopié.
///
/// L'identifiant d'installation est **anonyme** : il ne dit pas qui, seulement
/// « le même appareil ». Il se régénère à la désinstallation et **se purge à la
/// déconnexion**, comme le reste — voir `IdentifiantDInstallation`.
class RapporteurDePlantage {
  RapporteurDePlantage({
    required this.journal,
    required this.identifiantDInstallation,
    required this.versionApplication,
    required this.consentement,
    this.puits,
    String? plateforme,
  }) : plateforme = plateforme ?? _plateformeCourante();

  /*
   | Les dependances sont PUBLIQUES et finales, comme celles d'`AppLogger` et
   | pour la meme raison, qui y est ecrite : « elles sont injectees, pas
   | mutables ; un test qui verifie qu'un jeton ne sort jamais d'un journal doit
   | pouvoir relire la destination ».
   |
   | Cela vaut doublement ici : la propriete a prouver est qu'aucun incident ne
   | quitte l'appareil sans consentement. Un test qui ne peut pas relire le
   | puits et la porte ne peut pas la prouver.
   */
  final AppLogger journal;

  /// Nul tant qu'aucun fournisseur n'est branché — l'état d'aujourd'hui.
  final PuitsDeTelemetrie? puits;

  /// Anonyme : il dit « le même appareil », jamais « qui ».
  final String identifiantDInstallation;

  final String versionApplication;

  final PorteDeConsentement consentement;

  final String plateforme;

  /// Range l'incident dans le canal de journal qui lui correspond.
  ///
  /// On réutilise la taxonomie existante plutôt que d'ajouter un canal
  /// « application » fourre-tout : un échec de rejeu appartient à
  /// `pending-ops`, où le développeur qui enquête sur la file regarde déjà.
  /// Un canal de plus qu'il faudrait penser à consulter ne serait pas consulté.
  static LogChannel _canalPour(NatureDIncident nature) => switch (nature) {
    NatureDIncident.echecDeRejeu => LogChannel.pendingOps,
    NatureDIncident.echecDeJeton => LogChannel.session,
    NatureDIncident.echecDEnvoi => LogChannel.network,
    NatureDIncident.erreurNonCapturee ||
    NatureDIncident.erreurDeCadriciel => LogChannel.lifecycle,
  };

  static String _plateformeCourante() {
    if (kIsWeb) {
      return 'web';
    }
    return Platform.operatingSystem;
  }

  /// Branche les gestionnaires globaux.
  ///
  /// À appeler UNE FOIS, depuis la racine de composition. Les deux points
  /// couvrent des chemins différents et il faut les deux : `FlutterError`
  /// attrape ce qui casse pendant la construction d'un widget,
  /// `PlatformDispatcher` ce qui remonte d'une `Future` non attendue.
  void brancher() {
    final FlutterExceptionHandler? precedent = FlutterError.onError;

    FlutterError.onError = (FlutterErrorDetails details) {
      // On appelle le gestionnaire d'origine EN PREMIER : c'est lui qui écrit
      // la trace rouge en développement, et la perdre rendrait le débogage plus
      // pénible qu'avant ce lot.
      precedent?.call(details);

      unawaited(
        signaler(
          NatureDIncident.erreurDeCadriciel,
          details.exceptionAsString(),
          details.stack,
        ),
      );
    };

    PlatformDispatcher.instance.onError = (Object erreur, StackTrace pile) {
      unawaited(
        signaler(NatureDIncident.erreurNonCapturee, erreur.toString(), pile),
      );

      // `true` : l'erreur est traitée. On ne la relance pas — la faire remonter
      // ferait terminer l'application, ce qui est exactement ce que la
      // télémétrie sert à éviter.
      return true;
    };
  }

  /// Signale un incident. Expurge, puis remet au puits.
  ///
  /// Ne lève jamais : une télémétrie qui plante en rapportant un plantage
  /// masquerait le défaut d'origine et en ajouterait un.
  Future<void> signaler(
    NatureDIncident nature,
    String message, [
    StackTrace? pile,
  ]) async {
    try {
      final Incident incident = Incident(
        nature: nature,
        // Expurgé DÈS LA CONSTRUCTION, donc y compris pour le journal local.
        // Un journal local finit dans un rapport de bogue joint par la
        // personne elle-même : il n'a pas plus le droit de porter son adresse.
        message: LogRedactor.redactFreeText(message),
        pileDAppels: pile == null
            ? null
            : LogRedactor.redactFreeText(pile.toString()),
        identifiantDInstallation: identifiantDInstallation,
        versionApplication: versionApplication,
        plateforme: plateforme,
        survenuLe: DateTime.now(),
      );

      // TOUJOURS. Rien ne quitte l'appareil, et c'est ce qui manquait.
      journal.error(
        _canalPour(nature),
        incident.message,
        data: incident.versJson(),
        stackTrace: pile,
      );

      final PuitsDeTelemetrie? destination = puits;

      if (destination == null || !consentement()) {
        return;
      }

      await destination.envoyer(incident);
    } on Object catch (_) {
      // Silence délibéré, et c'est le seul de tout le fichier.
    }
  }
}
