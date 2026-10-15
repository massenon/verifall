import 'package:flutter/foundation.dart';

import '../../data/models/api_error_code.dart';

/// La hiérarchie d'échecs du domaine (04-RESEAU §4.2 et §4.5 de l'ancienne
/// numérotation).
///
/// POURQUOI aucune `DioException` ne franchit la frontière de `data/` : « un
/// écran qui attrape une `DioException` est un écran qu'il faudra réécrire le
/// jour où l'on change de bibliothèque ».
///
/// POURQUOI le message d'exception ne remonte **jamais** à l'écran : il peut
/// contenir une adresse de service, un identifiant, voire une clé recopiée par
/// une bibliothèque tierce. Il va au journal ; l'écran reçoit soit le message du
/// serveur — déjà rédigé en français et affichable tel quel — soit rien, et
/// c'est alors à l'écran d'écrire sa phrase.
@immutable
sealed class Failure implements Exception {
  const Failure({this.serverMessage, this.debug});

  /// Le message **du serveur**, quand il y en a un. Le contrat garantit qu'il est
  /// rédigé en français et affichable tel quel (§2.1) : le client ne le traduit
  /// pas, ne le reformule pas, et n'invente pas de repli quand il en reçoit un.
  final String? serverMessage;

  /// Réservé au journal. **Jamais affiché.**
  final String? debug;

  /// Vrai quand l'écran doit préférer le message du serveur à sa propre phrase.
  bool get hasServerMessage =>
      serverMessage != null && serverMessage!.trim().isNotEmpty;
}

/// « Vous êtes hors ligne » + ce qui reste possible.
///
/// POURQUOI cette catégorie est distincte d'une panne serveur : l'écran doit
/// dire ce qui reste faisable hors ligne, pas proposer « Réessayer » dans le
/// vide.
final class NetworkFailure extends Failure {
  const NetworkFailure({super.serverMessage, super.debug});
}

/// « Nos serveurs répondent mal » + Réessayer. 5xx, hors 500 métier.
final class ServerFailure extends Failure {
  const ServerFailure({this.statusCode, super.serverMessage, super.debug});

  final int? statusCode;
}

/// 401 `non_authentifie` — retour à la connexion, **sans message alarmiste**.
///
/// Le client purge le jeton et la base locale. Il ne tente **aucun**
/// renouvellement : il n'y a pas de jeton de rafraîchissement (§1.4 du contrat).
final class UnauthenticatedFailure extends Failure {
  const UnauthenticatedFailure({super.serverMessage, super.debug});
}

/// 403 `droit_manquant` — dire ce que la personne peut faire, pas un « 403 ».
final class ForbiddenFailure extends Failure {
  const ForbiddenFailure({super.serverMessage, super.debug});
}

/// 403 `compte_inactif` — le code à six chiffres n'a jamais été validé.
/// L'écran renvoie vers la saisie du code, avec l'adresse connue.
final class InactiveAccountFailure extends Failure {
  const InactiveAccountFailure({super.serverMessage, super.debug});
}

/// 403 `compte_en_suppression` — le délai de rétractation de trente jours court.
///
/// POURQUOI c'est traité au niveau du routeur et pas écran par écran : **toute**
/// route répond ainsi, sauf `GET /me` et `POST /auth/logout` (§1.7). Un
/// traitement écran par écran donnerait trente messages à écrire au lieu d'un.
/// **L'annulation ne passe pas par l'API** : elle se fait par le lien signé reçu
/// par courriel, et l'écran doit le dire.
final class AccountBeingDeletedFailure extends Failure {
  const AccountBeingDeletedFailure({super.serverMessage, super.debug});
}

/// 402 `solde_insuffisant` — **proposer une recharge**, pas afficher une erreur.
final class InsufficientBalanceFailure extends Failure {
  const InsufficientBalanceFailure({super.serverMessage, super.debug});
}

/// 422 `validation` — un message **par champ**, sous le champ.
final class ValidationFailure extends Failure {
  const ValidationFailure({
    this.fieldErrors = const <String, List<String>>{},
    super.serverMessage,
    super.debug,
  });

  final Map<String, List<String>> fieldErrors;

  List<String> messagesFor(String field) =>
      fieldErrors[field] ?? const <String>[];

  String? firstMessageFor(String field) {
    final List<String> messages = messagesFor(field);
    return messages.isEmpty ? null : messages.first;
  }
}

/// 422 `action_impossible` — l'action n'a pas de sens en l'état.
///
/// Le message du serveur porte l'information utile (combien de secondes de
/// lecture manquent, par exemple) : l'afficher tel quel, **jamais un échec
/// muet**.
final class ImpossibleActionFailure extends Failure {
  const ImpossibleActionFailure({super.serverMessage, super.debug});
}

/// 409 `conflit` — déjà fait, ou déjà en cours.
final class ConflictFailure extends Failure {
  const ConflictFailure({super.serverMessage, super.debug});
}

/// 404 `introuvable` — écran de repli explicite, avec une sortie.
///
/// POURQUOI le libellé ne doit jamais dire « supprimé » : « ce qui n'appartient
/// pas au demandeur répond 404, jamais 403 » (§9). Un 404 signifie « pas pour
/// vous, ou pas là ». Écrire « ce contenu a été supprimé » serait une
/// affirmation que le client n'a pas les moyens de faire.
final class NotFoundFailure extends Failure {
  const NotFoundFailure({super.serverMessage, super.debug});
}

/// 429 `debit_depasse` — dire **quand** ce sera de nouveau possible.
final class RateLimitedFailure extends Failure {
  const RateLimitedFailure({this.retryAfter, super.serverMessage, super.debug});

  /// Le délai à respecter. Vient de `meta.retry_after` ou de l'en-tête
  /// `Retry-After`, qui portent la même valeur en secondes (§5.3).
  final Duration? retryAfter;
}

/// 426 `client_obsolete` — écran bloquant « mettez à jour l'application ».
///
/// POURQUOI 426 et non 400 : « c'est la différence entre quelqu'un qui agit et
/// quelqu'un qui désinstalle » (§6.4).
final class ObsoleteClientFailure extends Failure {
  const ObsoleteClientFailure({
    this.minimumVersion,
    super.serverMessage,
    super.debug,
  });

  /// Lu de l'en-tête `X-Minimum-Client-Version`.
  final String? minimumVersion;
}

/// 405 `methode_refusee` — bogue client. À faire remonter, pas à afficher.
final class ClientBugFailure extends Failure {
  const ClientBugFailure({super.serverMessage, super.debug});
}

/// Tout le reste : message neutre + envoi à la télémétrie.
final class UnexpectedFailure extends Failure {
  const UnexpectedFailure({super.serverMessage, super.debug});
}

/// Un échec local, sans réseau : lecture de la base, du stockage sécurisé, du
/// disque. Il n'existe pas dans le contrat parce qu'il ne traverse rien.
final class LocalStorageFailure extends Failure {
  const LocalStorageFailure({super.debug});
}

/// Le code d'erreur associé à un échec, quand il en vient un.
extension FailureCode on Failure {
  ApiErrorCode? get apiCode => switch (this) {
    UnauthenticatedFailure() => ApiErrorCode.nonAuthentifie,
    InsufficientBalanceFailure() => ApiErrorCode.soldeInsuffisant,
    InactiveAccountFailure() => ApiErrorCode.compteInactif,
    AccountBeingDeletedFailure() => ApiErrorCode.compteEnSuppression,
    ForbiddenFailure() => ApiErrorCode.droitManquant,
    NotFoundFailure() => ApiErrorCode.introuvable,
    ClientBugFailure() => ApiErrorCode.methodeRefusee,
    ValidationFailure() => ApiErrorCode.validation,
    ImpossibleActionFailure() => ApiErrorCode.actionImpossible,
    ConflictFailure() => ApiErrorCode.conflit,
    ObsoleteClientFailure() => ApiErrorCode.clientObsolete,
    RateLimitedFailure() => ApiErrorCode.debitDepasse,
    ServerFailure() => ApiErrorCode.erreurServeur,
    NetworkFailure() => null,
    LocalStorageFailure() => null,
    UnexpectedFailure() => null,
  };
}
