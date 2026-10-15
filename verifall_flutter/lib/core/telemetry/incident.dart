import 'package:flutter/foundation.dart';

/// Les natures d'incident que le §7.6 demande de collecter.
///
/// La liste est CLOSE et volontairement courte : « plantages, erreurs non
/// capturées, images perdues, et un petit nombre d'événements de diagnostic —
/// échec de rejeu de la file, échec de renouvellement de jeton, échec d'envoi
/// de fichier. Ces trois-là expliquent la majorité des incidents réels. »
///
/// Un enum plutôt qu'une chaîne libre : une catégorie inventée au fil de l'eau
/// ne se regroupe pas côté serveur, et personne ne s'en aperçoit avant d'avoir
/// besoin du chiffre.
enum NatureDIncident {
  /// Une exception non capturée, remontée par `PlatformDispatcher.onError`.
  erreurNonCapturee,

  /// Une erreur du cadriciel, remontée par `FlutterError.onError`.
  erreurDeCadriciel,

  /// Le rejeu de la file d'écriture a échoué — le travail hors ligne de
  /// quelqu'un n'est pas parti.
  echecDeRejeu,

  /// Le renouvellement du jeton a échoué : la personne va être déconnectée
  /// sans l'avoir demandé.
  echecDeJeton,

  /// L'envoi d'un fichier a échoué.
  echecDEnvoi,
}

/// Un incident, **déjà expurgé**, prêt à quitter l'appareil.
///
/// # L'invariant qui compte
///
/// Rien de nominatif n'entre ici. La construction passe obligatoirement par
/// `RapporteurDePlantage`, qui applique `LogRedactor` au message ET à la pile
/// d'appels. Cette classe ne redresse rien : elle transporte. Si un jour un
/// champ nominatif y apparaît, c'est en amont qu'est le défaut.
///
/// # Ce qu'il ne contient PAS, et la liste est absolue (§7.6)
///
///   · le contenu soumis à vérification — texte utilisateur, souvent nominatif,
///     et c'est le cœur de ce que la plateforme promet de protéger ;
///   · l'adresse e-mail, le téléphone, le nom ;
///   · les jetons, mots de passe, codes à usage unique ;
///   · l'adresse IP en clair.
///
/// Reste `identifiantDInstallation` : un identifiant **anonyme**, qui suffit à
/// regrouper les incidents d'un même appareil, se régénère à la
/// désinstallation, et se purge à la déconnexion.
@immutable
class Incident {
  const Incident({
    required this.nature,
    required this.message,
    required this.identifiantDInstallation,
    required this.versionApplication,
    required this.plateforme,
    required this.survenuLe,
    this.pileDAppels,
  });

  final NatureDIncident nature;

  /// Le message, **expurgé**.
  final String message;

  /// La pile d'appels, **expurgée**. Nulle quand la source n'en fournit pas.
  final String? pileDAppels;

  /// Identifiant anonyme d'installation. Jamais l'identifiant du compte.
  final String identifiantDInstallation;

  final String versionApplication;

  final String plateforme;

  final DateTime survenuLe;

  /// La forme transportable. Les clés sont stables : un puits les lit.
  Map<String, Object?> versJson() => <String, Object?>{
    'nature': nature.name,
    'message': message,
    if (pileDAppels != null) 'pile': pileDAppels,
    'installation': identifiantDInstallation,
    'version': versionApplication,
    'plateforme': plateforme,
    'survenu_le': survenuLe.toUtc().toIso8601String(),
  };

  @override
  String toString() => 'Incident(${nature.name}: $message)';
}
