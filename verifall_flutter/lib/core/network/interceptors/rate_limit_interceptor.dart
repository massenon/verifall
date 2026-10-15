import 'dart:async';

import 'package:dio/dio.dart';

/// L'auto-régulation de débit (§5, 04-RESEAU §4.5).
///
/// > « Un plafond sans en-têtes est un mur invisible. »
///
/// Le serveur annonce `X-RateLimit-Limit` et `X-RateLimit-Remaining` sur chaque
/// réponse. Sous 10 % du plafond, le client **espace de lui-même** plutôt que de
/// se faire éconduire et d'afficher une erreur.
///
/// POURQUOI c'est concret et pas théorique : l'écran de suivi d'analyse
/// interroge l'avancement toutes les trois secondes. Le plafond de lecture — 120
/// par minute — a été dimensionné pour cela, mais un écran qui sonde pendant
/// qu'une liste se charge peut le frôler. C'est ce sondage-là qu'il faut
/// ralentir **avant** qu'il ne se fasse éconduire.
class RateLimitInterceptor extends Interceptor {
  RateLimitInterceptor({
    this.throttleThreshold = 0.10,
    this.throttleDelay = const Duration(milliseconds: 400),
    Future<void> Function(Duration)? sleep,
  }) : _sleep = sleep ?? Future<void>.delayed;

  static const String limitHeader = 'x-ratelimit-limit';
  static const String remainingHeader = 'x-ratelimit-remaining';

  /// La fraction du plafond sous laquelle on commence à espacer.
  final double throttleThreshold;

  /// L'espacement appliqué. Volontairement court : il s'agit de lisser, pas de
  /// suspendre l'application.
  final Duration throttleDelay;

  final Future<void> Function(Duration) _sleep;

  int? _limit;
  int? _remaining;

  /// Le quota restant lu à la dernière réponse. Exposé pour le diagnostic et
  /// pour les tests.
  int? get remaining => _remaining;

  int? get limit => _limit;

  /// Faut-il espacer les appels ?
  bool get shouldThrottle {
    final int? limit = _limit;
    final int? remaining = _remaining;
    if (limit == null || remaining == null || limit <= 0) {
      return false;
    }
    return remaining / limit < throttleThreshold;
  }

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    if (shouldThrottle) {
      await _sleep(throttleDelay);
    }
    handler.next(options);
  }

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    _readHeaders(response.headers);
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final Response<dynamic>? response = err.response;
    if (response != null) {
      _readHeaders(response.headers);
    }
    handler.next(err);
  }

  void _readHeaders(Headers headers) {
    _limit = int.tryParse(headers.value(limitHeader) ?? '') ?? _limit;
    _remaining =
        int.tryParse(headers.value(remainingHeader) ?? '') ?? _remaining;
  }
}
