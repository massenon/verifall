import 'dart:async';
import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';

import 'headers_interceptor.dart';

/// Dernier intercepteur : le réessai (04-RESEAU §4.9).
///
/// POURQUOI il passe en dernier : un réessai doit repasser par les en-têtes et
/// par le contrôle de session. Placé en premier, il rejouerait une requête avec
/// un jeton périmé.
///
/// **Ce qui se réessaie** : les `GET` et les échecs de transport (délai dépassé,
/// connexion perdue, 502/503/504). Trois tentatives au plus, recul exponentiel
/// **avec une part d'aléa** — sans l'aléa, tous les clients reviennent à la même
/// seconde après une panne et achèvent le serveur.
///
/// **Ce qui ne se réessaie jamais** :
///
/// - un `429` — on respecte `retry_after`, un seul essai ensuite, et c'est
///   l'écran qui le décide, pas l'intercepteur ;
/// - un `4xx` de validation — c'est la saisie qui est en cause ;
/// - un `426` — il faut une nouvelle version, pas une nouvelle tentative ;
/// - une **écriture sans `Idempotency-Key`** — elle produirait un doublon.
///
/// Le rejeu des écritures passe par la file `pending_ops`, pas par ici :
/// l'intercepteur réessaie dans la seconde, la file rejoue dans l'heure. Ce sont
/// deux mécanismes distincts et il ne faut pas les confondre.
class RetryInterceptor extends Interceptor {
  RetryInterceptor({
    required this.dio,
    this.maxAttempts = 3,
    this.baseDelay = const Duration(milliseconds: 400),
    Random? random,
    Future<void> Function(Duration)? sleep,
  }) : _random = random ?? Random(),
       _sleep = sleep ?? Future<void>.delayed;

  static const String _attemptKey = 'vf.retry.attempt';

  final Dio dio;
  final int maxAttempts;
  final Duration baseDelay;
  final Random _random;
  final Future<void> Function(Duration) _sleep;

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final RequestOptions options = err.requestOptions;
    final int attempt = (options.extra[_attemptKey] as int? ?? 0) + 1;

    if (attempt >= maxAttempts || !isRetryable(err)) {
      handler.next(err);
      return;
    }

    await _sleep(delayFor(attempt));

    options.extra[_attemptKey] = attempt;
    try {
      final Response<dynamic> response = await dio.fetch<dynamic>(options);
      handler.resolve(response);
    } on DioException catch (retryError) {
      handler.next(retryError);
    }
  }

  /// Le recul exponentiel, avec sa part d'aléa (« full jitter »).
  ///
  /// POURQUOI l'aléa n'est pas cosmétique : après une coupure réseau, dix mille
  /// téléphones réessaient. Sans aléa, ils reviennent tous à la même seconde.
  Duration delayFor(int attempt) {
    final int ceiling = baseDelay.inMilliseconds * pow(2, attempt - 1).toInt();
    return Duration(milliseconds: _random.nextInt(ceiling + 1) + 50);
  }

  bool isRetryable(DioException err) {
    final Response<dynamic>? response = err.response;

    // Echec de transport : rien n'est revenu. Reessayable quelle que soit la
    // methode SI la requete est idempotente ou porte une cle d'idempotence.
    if (response == null) {
      final bool transportFailure = switch (err.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout ||
        DioExceptionType.connectionError => true,
        DioExceptionType.unknown => err.error is SocketException,
        // Un 4xx/5xx est traite plus bas, par son statut ; une annulation et
        // un certificat refuse ne se reessaient jamais.
        DioExceptionType.cancel ||
        DioExceptionType.badCertificate ||
        DioExceptionType.badResponse ||
        DioExceptionType.transformTimeout => false,
      };
      return transportFailure && _isSafeToReplay(err.requestOptions);
    }

    final int? status = response.statusCode;
    // 502 / 503 / 504 : le serveur est momentanement indisponible, la requete
    // n'a probablement pas ete traitee.
    const Set<int> retryableStatuses = <int>{502, 503, 504};
    return retryableStatuses.contains(status) &&
        _isSafeToReplay(err.requestOptions);
  }

  /// Une requête est rejouable si elle est idempotente **par nature** (`GET`,
  /// `HEAD`) ou **par contrat** (elle porte une `Idempotency-Key`).
  bool _isSafeToReplay(RequestOptions options) {
    final String method = options.method.toUpperCase();
    if (method == 'GET' || method == 'HEAD') {
      return true;
    }
    final Object? key = options.headers[HeadersInterceptor.idempotencyHeader];
    return key is String && key.isNotEmpty;
  }
}
