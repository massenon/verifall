import 'package:flutter/foundation.dart';

import 'json_reader.dart';

/// L'utilisateur tel qu'il accompagne l'émission d'un jeton (§1.2 du contrat).
///
/// **Quatre champs, pas un de plus** : `uuid`, `prenom`, `email`, `unites`.
/// C'est exactement ce que le contrat montre. Ajouter un champ « probable »
/// reviendrait à deviner une forme de réponse.
///
/// Le profil complet est servi par `GET /me` — sa forme n'est pas détaillée par
/// le contrat, elle appartient donc au parcours M5 qui la découvrira contre le
/// serveur, pas au socle qui l'inventerait.
@immutable
class UtilisateurResume {
  const UtilisateurResume({
    required this.uuid,
    required this.prenom,
    required this.email,
    required this.unites,
  });

  factory UtilisateurResume.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return UtilisateurResume(
      uuid: reader.string('uuid'),
      prenom: reader.string('prenom'),
      email: reader.string('email'),
      unites: reader.intValue('unites'),
    );
  }

  final String uuid;
  final String prenom;
  final String email;

  /// Le solde d'unités **à l'instant de l'émission du jeton**.
  ///
  /// POURQUOI il ne doit pas servir d'affichage durable : le solde est un fait
  /// serveur qui ne se lit **jamais** depuis le cache (05-HORS-LIGNE §5.3). Cette
  /// valeur-ci vieillit dès la seconde suivante.
  final int unites;

  /// L'initiale de l'avatar quand le serveur n'en fournit pas.
  ///
  /// Une seule lettre, celle du prénom : le contrat ne rend pas le nom de
  /// famille dans cette forme, et en inventer une deuxième reviendrait à
  /// afficher une initiale qu'on n'a pas.
  String get initiale =>
      prenom.isEmpty ? '?' : prenom.substring(0, 1).toUpperCase();

  @override
  String toString() => 'UtilisateurResume($uuid)';
}

/// La réponse des **trois** points d'entrée qui émettent un jeton :
/// `POST /auth/login`, `POST /auth/otp/verify`, `POST /auth/password/reset`.
///
/// POURQUOI un seul modèle pour les trois : « ils renvoient tous exactement la
/// même forme » (§1.2). Un seul chemin de code les traite, donc un seul endroit
/// à corriger.
@immutable
class AuthToken {
  const AuthToken({
    required this.token,
    required this.type,
    required this.expireLe,
    required this.utilisateur,
  });

  factory AuthToken.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return AuthToken(
      token: reader.string('token'),
      type: reader.string('type', fallback: 'Bearer'),
      expireLe: reader.dateTimeOrNull('expire_le'),
      utilisateur: UtilisateurResume.fromJson(
        reader.objectOrNull('utilisateur') ?? const <String, Object?>{},
      ),
    );
  }

  /// Le jeton en clair **n'existe qu'une fois**, dans cette réponse : le serveur
  /// n'en garde que l'empreinte et il n'y a aucun endpoint pour le relire
  /// (§1.2). Perdu, il faut se reconnecter.
  final String token;

  final String type;

  /// Échéance **glissante** de 30 jours : chaque appel authentifié la repousse
  /// dès qu'il reste moins de la moitié de la durée. Le client n'a rien à faire
  /// pour cela — c'est le serveur qui prolonge (§1.4).
  ///
  /// Elle sert au diagnostic, pas à décider d'un renouvellement : il n'y a **pas
  /// de jeton de rafraîchissement** dans ce contrat.
  final DateTime? expireLe;

  final UtilisateurResume utilisateur;

  String get authorizationHeader => '$type $token';

  @override
  String toString() => 'AuthToken(${utilisateur.uuid})';
}
