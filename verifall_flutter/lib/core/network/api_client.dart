import 'package:dio/dio.dart';

import '../../data/models/api_envelope.dart';
import '../errors/failure.dart';
import '../errors/failure_mapper.dart';
import '../logging/app_logger.dart';
import 'api_endpoints.dart';
import 'interceptors/auth_interceptor.dart';
import 'interceptors/headers_interceptor.dart';
import 'interceptors/logging_interceptor.dart';
import 'interceptors/rate_limit_interceptor.dart';
import 'interceptors/retry_interceptor.dart';
import 'token_store.dart';

/// Le client de l'API v1.
///
/// **Un seul client, construit une fois, fourni par un provider** (04-RESEAU
/// §4.1). Il rend des [ApiEnvelope] ou lève des [Failure] — jamais une
/// `DioException`, qui ne doit pas franchir la frontière de `data/`.
class ApiClient {
  ApiClient._(this._dio, this.rateLimit);

  /// Construit le client et branche les intercepteurs **dans l'ordre**, parce
  /// que l'ordre compte (04-RESEAU §4.2) :
  ///
  /// 1. en-têtes — tout le reste en dépend ;
  /// 2. journalisation — méthode, chemin, code, durée ;
  /// 3. session — sur 401, déconnexion propre ;
  /// 4. débit — lecture des en-têtes de quota, auto-régulation ;
  /// 5. réessai — **en dernier**, pour qu'un réessai repasse par les en-têtes.
  factory ApiClient.create({
    required String baseUrl,
    required TokenStore tokenStore,
    required AppLogger logger,
    required String clientVersion,
    required Future<void> Function() onUnauthenticated,
    required void Function() onClientObsolete,
    required void Function() onAccountInactive,
    required void Function() onAccountBeingDeleted,
    HttpClientAdapter? adapter,
  }) {
    final Dio dio = Dio(
      BaseOptions(
        // La version majeure appartient au contrat, pas a la configuration :
        // c'est pourquoi elle est concatenee ici et non lue de l'environnement.
        baseUrl: '${_trimTrailingSlash(baseUrl)}${ApiEndpoints.versionPrefix}',
        // Des delais explicites. POURQUOI : « un delai par defaut de
        // bibliotheque est toujours trop long pour un reseau mobile beninois —
        // un ecran fige trente secondes est percu comme une panne de
        // l'application, pas du reseau. »
        connectTimeout: const Duration(seconds: 10),
        sendTimeout: const Duration(seconds: 30),
        receiveTimeout: const Duration(seconds: 20),
        // `Accept: application/json` systematique. Le contrat garantit que le
        // serveur ne rend jamais de HTML sur /api/v1/*, mais l'en-tete reste
        // pose par prudence : un mandataire mal configure, lui, en rend.
        headers: <String, Object>{
          Headers.acceptHeader: Headers.jsonContentType,
        },
        // Seuls les 2xx sont des succes de transport. POURQUOI c'est important
        // ici : ce sont les `onError` des intercepteurs qui portent le 401, le
        // 426, la lecture des en-tetes de quota et le reessai. Elargir
        // `validateStatus` a tous les statuts les court-circuiterait tous — et
        // la session ne se fermerait plus jamais sur un jeton revoque.
        // Le corps de l'erreur reste lisible : il voyage dans
        // `DioException.response.data`, et c'est de la que `error.code` est lu.
        validateStatus: (int? status) =>
            status != null && status >= 200 && status < 300,
        responseType: ResponseType.json,
      ),
    );

    if (adapter != null) {
      dio.httpClientAdapter = adapter;
    }

    final RateLimitInterceptor rateLimit = RateLimitInterceptor();

    dio.interceptors.addAll(<Interceptor>[
      HeadersInterceptor(tokenStore: tokenStore, clientVersion: clientVersion),
      LoggingInterceptor(logger: logger),
      AuthInterceptor(
        onUnauthenticated: onUnauthenticated,
        onClientObsolete: onClientObsolete,
        onAccountInactive: onAccountInactive,
        onAccountBeingDeleted: onAccountBeingDeleted,
      ),
      rateLimit,
      RetryInterceptor(dio: dio),
    ]);

    return ApiClient._(dio, rateLimit);
  }

  final Dio _dio;

  /// Exposé pour que l'écran de suivi d'analyse puisse espacer son sondage
  /// **avant** de se faire éconduire (04-RESEAU §4.5).
  final RateLimitInterceptor rateLimit;

  /// Accès brut, pour les cas que le socle ne couvre pas — l'envoi de fichier en
  /// multipart avec progression, par exemple.
  Dio get raw => _dio;

  Future<ApiEnvelope> get(
    String path, {
    Map<String, Object?>? query,
    CancelToken? cancelToken,
  }) => _send(path, method: 'GET', query: query, cancelToken: cancelToken);

  /// Une page de liste. `limit` est borné au plafond du contrat.
  ///
  /// POURQUOI le client borne alors que le serveur ramène à 50 sans erreur
  /// (§3.2) : demander 200 pour en recevoir 50 fait croire à une fin de liste.
  Future<ApiEnvelope> getPage(
    String path, {
    String? cursor,
    int limit = PageMeta.defaultLimit,
    Map<String, Object?>? filters,
    CancelToken? cancelToken,
  }) {
    assert(
      !ApiEndpoints.unpaginatedCollections.contains(path),
      'Cette collection n est pas paginee (contrat §3.3) : '
      'un curseur qui ne se declenche jamais est du code mort.',
    );
    return get(
      path,
      query: <String, Object?>{
        ...?filters,
        'limit': limit.clamp(1, PageMeta.maxLimit),
        // Le curseur est OPAQUE : renvoye tel quel, jamais lu, jamais fabrique.
        if (cursor != null && cursor.isNotEmpty) 'cursor': cursor,
      },
      cancelToken: cancelToken,
    );
  }

  /// Une écriture.
  ///
  /// [idempotencyKey] est **la clé de l'acte**, pas de la tentative : elle est
  /// générée au moment où l'utilisateur agit, gardée dans la file, et réutilisée
  /// identique à chaque rejeu (§7.3).
  Future<ApiEnvelope> post(
    String path, {
    Object? body,
    Map<String, Object?>? query,
    String? idempotencyKey,
    CancelToken? cancelToken,
  }) => _send(
    path,
    method: 'POST',
    body: body,
    query: query,
    idempotencyKey: idempotencyKey,
    cancelToken: cancelToken,
  );

  Future<ApiEnvelope> patch(
    String path, {
    Object? body,
    String? idempotencyKey,
    CancelToken? cancelToken,
  }) => _send(
    path,
    method: 'PATCH',
    body: body,
    idempotencyKey: idempotencyKey,
    cancelToken: cancelToken,
  );

  Future<ApiEnvelope> delete(
    String path, {
    Object? body,
    CancelToken? cancelToken,
  }) => _send(path, method: 'DELETE', body: body, cancelToken: cancelToken);

  Future<ApiEnvelope> _send(
    String path, {
    required String method,
    Object? body,
    Map<String, Object?>? query,
    String? idempotencyKey,
    CancelToken? cancelToken,
  }) async {
    Options options = Options(method: method);
    if (idempotencyKey != null && idempotencyKey.isNotEmpty) {
      options = options.withIdempotencyKey(idempotencyKey);
    }

    late final Response<dynamic> response;
    try {
      response = await _dio.request<dynamic>(
        path,
        data: body,
        queryParameters: query,
        options: options,
        cancelToken: cancelToken,
      );
    } on DioException catch (error) {
      throw FailureMapper.fromDioException(error);
    } on Object catch (error) {
      // Aucune exception brute ne franchit cette frontiere : un ecran qui
      // attraperait une exception de bibliotheque serait a reecrire le jour ou
      // l'on change de bibliotheque.
      throw UnexpectedFailure(debug: '$error');
    }

    final ApiEnvelope envelope = ApiEnvelope.fromJson(
      _asMap(response.data, response.statusCode),
    );

    // LA seule regle de lecture : si la reponse contient `error`, c'est un
    // echec. Sinon `data` fait foi. En particulier, `{ "data": null }` n'est
    // PAS une erreur — c'est « c'est fait », ou un etat vide legitime.
    if (envelope.hasError) {
      throw FailureMapper.fromResponse(response);
    }

    return envelope;
  }

  Map<String, Object?> _asMap(Object? data, int? statusCode) {
    if (data is Map) {
      return data.map(
        (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
      );
    }
    // « Jamais de corps vide : un client qui decode du JSON ne doit jamais
    // avoir a gerer le cas "rien a decoder" » (§2.1). Si on arrive ici, c'est
    // une anomalie d'infrastructure, pas un cas nominal.
    throw UnexpectedFailure(debug: 'corps non JSON, statut $statusCode');
  }

  void close() => _dio.close(force: true);

  static String _trimTrailingSlash(String value) =>
      value.endsWith('/') ? value.substring(0, value.length - 1) : value;
}
