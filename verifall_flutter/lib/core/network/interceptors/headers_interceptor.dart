import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../token_store.dart';

/// Premier intercepteur : les en-têtes (04-RESEAU §4.1).
///
/// POURQUOI il passe en premier : tout le reste en dépend. Un réessai doit
/// repasser par ici pour reposer un jeton éventuellement changé entre-temps.
///
/// Le contrat ne rend **qu'un** en-tête obligatoire — `Authorization` — mais
/// trois autres se posent systématiquement, et chacun pour une raison précise.
class HeadersInterceptor extends Interceptor {
  HeadersInterceptor({
    required this.tokenStore,
    required this.clientVersion,
    Uuid? uuid,
  }) : _uuid = uuid ?? const Uuid();

  /// La version du client (§6.4). **Facultative au contrat**, envoyée quand même
  /// et systématiquement : c'est elle qui rend le plancher de version utilisable
  /// le jour où il faudra couper une version dangereuse — sans elle, on ne peut
  /// jamais retirer un ancien comportement.
  static const String clientVersionHeader = 'X-Client-Version';

  /// L'identifiant de corrélation (§1.6) : « pour qu'un incident se retrouve des
  /// deux côtés ».
  ///
  /// Le contrat ne nomme pas cet en-tête ; `X-Request-Id` est la convention la
  /// plus répandue et le serveur l'ignore sans dommage tant qu'il ne le lit pas.
  /// À aligner avec l'infrastructure quand elle tranchera.
  static const String correlationHeader = 'X-Request-Id';

  /// La clé d'idempotence (§7.3). Elle n'est **jamais** fabriquée ici : elle est
  /// générée au moment où l'utilisateur agit, gardée dans la ligne de la file, et
  /// posée par l'appelant via [withIdempotencyKey]. Une clé regénérée à l'envoi
  /// ne protège de rien.
  static const String idempotencyHeader = 'Idempotency-Key';

  /// Longueur maximale imposée par le contrat.
  static const int idempotencyMaxLength = 128;

  final TokenStore tokenStore;
  final String clientVersion;
  final Uuid _uuid;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    options.headers[Headers.acceptHeader] = Headers.jsonContentType;
    options.headers[clientVersionHeader] = clientVersion;

    // Un identifiant par requete, conserve a travers les reessais : sans cela,
    // trois tentatives donnent trois identifiants et l'incident se cherche a
    // trois endroits.
    options.headers[correlationHeader] ??= _uuid.v4();

    final String? token = await tokenStore.read();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    } else {
      // Les routes d'identite n'ont pas de jeton. Laisser un en-tete vide
      // ferait repondre 401 la ou le contrat attend une requete anonyme.
      options.headers.remove('Authorization');
    }

    handler.next(options);
  }
}

/// Pose une `Idempotency-Key` sur une requête.
///
/// POURQUOI une extension plutôt qu'un paramètre du client : la clé appartient à
/// l'**acte**, pas à l'appel. C'est la file d'écriture qui la détient et la
/// réutilise à chaque rejeu (05-HORS-LIGNE §5.6).
extension IdempotentRequest on Options {
  Options withIdempotencyKey(String key) {
    final String bounded = key.length > HeadersInterceptor.idempotencyMaxLength
        ? key.substring(0, HeadersInterceptor.idempotencyMaxLength)
        : key;
    return copyWith(
      headers: <String, dynamic>{
        ...?headers,
        HeadersInterceptor.idempotencyHeader: bounded,
      },
    );
  }
}
