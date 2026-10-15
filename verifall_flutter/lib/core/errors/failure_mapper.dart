import 'dart:io';

import 'package:dio/dio.dart';

import '../../data/models/api_envelope.dart';
import '../../data/models/api_error_code.dart';
import '../../data/models/json_reader.dart';
import 'failure.dart';

/// La traduction « une réponse HTTP » vers « un échec du domaine ».
///
/// POURQUOI la traduction se fait ici, une fois, et pas dans chaque dépôt : elle
/// est testable seule (06-TESTS §6.3, « la traduction des erreurs — chaque code
/// produit la bonne catégorie ») et elle garantit que treize codes donnent bien
/// treize comportements, sans qu'un écran en oublie un.
///
/// POURQUOI on branche sur `error.code` et pas sur le statut HTTP : trois codes
/// partagent 403, deux partagent 422. Le statut ne suffit pas à décider.
abstract final class FailureMapper {
  /// Nom de l'en-tête qui porte le plancher de version sur un `426` (§6.4).
  static const String minimumClientVersionHeader = 'X-Minimum-Client-Version';

  /// Nom de l'en-tête de délai, présent uniquement sur `429` et sur une
  /// connexion verrouillée (§5.3).
  static const String retryAfterHeader = 'Retry-After';

  /// Traduit une exception Dio.
  static Failure fromDioException(DioException exception) {
    final Response<Object?>? response = exception.response;

    // Echec de transport : rien n'est revenu du serveur. C'est « vous etes hors
    // ligne » ou « nos serveurs repondent mal », jamais un message metier.
    if (response == null) {
      return switch (exception.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout => NetworkFailure(
          debug: 'timeout ${exception.type.name}',
        ),
        DioExceptionType.connectionError => const NetworkFailure(
          debug: 'connection error',
        ),
        DioExceptionType.cancel => const NetworkFailure(
          debug: 'requete annulee',
        ),
        DioExceptionType.badCertificate => const ServerFailure(
          debug: 'certificat refuse',
        ),
        // `transformTimeout` est un echec de transformation locale de la
        // reponse, pas un echec reseau : il part en anomalie plutot qu'en
        // « vous etes hors ligne », qui serait un mensonge.
        DioExceptionType.transformTimeout => const UnexpectedFailure(
          debug: 'transform timeout',
        ),
        DioExceptionType.badResponse || DioExceptionType.unknown =>
          exception.error is SocketException
              ? const NetworkFailure(debug: 'socket')
              : UnexpectedFailure(debug: exception.type.name),
      };
    }

    return fromResponse(response);
  }

  /// Traduit une réponse portant `error`.
  ///
  /// Le contrat garantit qu'**aucune** erreur ne sort en HTML sur `/api/v1/*`, y
  /// compris les 404 de route inconnue et les 405 (§2.2). Un corps illisible est
  /// donc une anomalie de mandataire, pas un cas nominal : il devient
  /// [UnexpectedFailure] et part à la télémétrie.
  static Failure fromResponse(Response<Object?> response) {
    final Map<String, Object?> body = asJsonObject(response.data);
    if (body.isEmpty) {
      return UnexpectedFailure(
        debug: 'corps illisible, statut ${response.statusCode}',
      );
    }

    final ApiEnvelope envelope = ApiEnvelope.fromJson(body);
    final ApiErrorBody? error = envelope.error;
    if (error == null) {
      return UnexpectedFailure(
        debug: 'réponse sans error, statut ${response.statusCode}',
      );
    }

    return fromApiError(
      error,
      statusCode: response.statusCode,
      retryAfter: _retryAfterOf(envelope, response),
      minimumClientVersion: _headerOf(response, minimumClientVersionHeader),
    );
  }

  /// Traduit un `error` déjà décodé. Séparée pour être testable sans Dio.
  static Failure fromApiError(
    ApiErrorBody error, {
    int? statusCode,
    Duration? retryAfter,
    String? minimumClientVersion,
  }) {
    final String message = error.message;
    final String debug = 'code=${error.rawCode} http=$statusCode';

    return switch (error.code) {
      ApiErrorCode.nonAuthentifie => UnauthenticatedFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.soldeInsuffisant => InsufficientBalanceFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.compteInactif => InactiveAccountFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.compteEnSuppression => AccountBeingDeletedFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.droitManquant => ForbiddenFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.introuvable => NotFoundFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.methodeRefusee => ClientBugFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.validation => ValidationFailure(
        fieldErrors: error.details,
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.actionImpossible => ImpossibleActionFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.conflit => ConflictFailure(
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.clientObsolete => ObsoleteClientFailure(
        minimumVersion: minimumClientVersion,
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.debitDepasse => RateLimitedFailure(
        retryAfter: retryAfter,
        serverMessage: message,
        debug: debug,
      ),
      ApiErrorCode.erreurServeur => ServerFailure(
        statusCode: statusCode,
        serverMessage: message,
        debug: debug,
      ),
      // Un code ajoute apres l'ecriture de ce client : le message reste
      // affichable, seul l'aiguillage retombe sur un traitement neutre. C'est
      // ce qui rend une evolution compatible reellement compatible (§6.2).
      ApiErrorCode.inconnu => UnexpectedFailure(
        serverMessage: message,
        debug: debug,
      ),
    };
  }

  static Duration? _retryAfterOf(
    ApiEnvelope envelope,
    Response<Object?> response,
  ) {
    final int? fromBody = envelope.retryAfterSeconds;
    if (fromBody != null) {
      return Duration(seconds: fromBody);
    }
    final String? header = _headerOf(response, retryAfterHeader);
    final int? seconds = header == null ? null : int.tryParse(header);
    return seconds == null ? null : Duration(seconds: seconds);
  }

  static String? _headerOf(Response<Object?> response, String name) =>
      response.headers.value(name.toLowerCase());
}
