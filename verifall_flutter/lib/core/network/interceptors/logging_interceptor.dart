import 'package:dio/dio.dart';

import '../../logging/app_logger.dart';
import 'headers_interceptor.dart';

/// Deuxième intercepteur : la journalisation.
///
/// **Méthode, chemin, code, durée. Jamais le corps, jamais les en-têtes.**
///
/// POURQUOI cette interdiction est absolue (04-RESEAU §4.13, §1.6) : le corps
/// contient des jetons, des mots de passe, des codes à usage unique et du
/// contenu soumis à vérification — c'est-à-dire du texte utilisateur, souvent
/// nominatif, et le cœur de ce que la plateforme promet de protéger. Les
/// en-têtes contiennent le jeton porteur.
///
/// Le chemin lui-même est expurgé : une route porte des uuid et des slugs, et un
/// slug de sujet de forum est une phrase écrite par quelqu'un.
class LoggingInterceptor extends Interceptor {
  const LoggingInterceptor({required this.logger});

  static const String _startedAtKey = 'vf.startedAt';

  final AppLogger logger;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.extra[_startedAtKey] = DateTime.now();
    handler.next(options);
  }

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    logger.info(
      LogChannel.network,
      '${response.requestOptions.method} '
      '${_safePath(response.requestOptions.path)} '
      '-> ${response.statusCode} en ${_elapsedMs(response.requestOptions)} ms',
      correlationId: _correlationId(response.requestOptions),
      data: <String, Object?>{
        // `Idempotent-Replay: true` n'est PAS une erreur : c'est la confirmation
        // que l'acte a abouti la premiere fois (05-HORS-LIGNE §5.6). Le tracer
        // evite de chercher pourquoi « rien ne s'est passe ».
        'idempotent_replay':
            response.headers.value('idempotent-replay') == 'true',
        'api_version': response.headers.value('x-api-version'),
        'ratelimit_remaining': response.headers.value('x-ratelimit-remaining'),
      },
    );
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    logger.warning(
      LogChannel.network,
      '${err.requestOptions.method} '
      '${_safePath(err.requestOptions.path)} '
      '-> ${err.response?.statusCode ?? err.type.name} '
      'en ${_elapsedMs(err.requestOptions)} ms',
      correlationId: _correlationId(err.requestOptions),
    );
    handler.next(err);
  }

  String? _correlationId(RequestOptions options) =>
      options.headers[HeadersInterceptor.correlationHeader] as String?;

  int _elapsedMs(RequestOptions options) {
    final Object? startedAt = options.extra[_startedAtKey];
    if (startedAt is! DateTime) {
      return -1;
    }
    return DateTime.now().difference(startedAt).inMilliseconds;
  }

  /// Remplace les segments variables du chemin par un jeton fixe.
  ///
  /// POURQUOI : `/forum/threads/cette-video-montre-t-elle-le-ministre` est un
  /// chemin **et** une donnée. Journaliser le gabarit suffit au diagnostic.
  String _safePath(String path) {
    return path
        .split('/')
        .map((String segment) {
          if (segment.isEmpty) {
            return segment;
          }
          // Un segment est un gabarit connu s'il figure parmi les mots fixes de
          // l'API ; tout le reste est une valeur.
          return _fixedSegments.contains(segment) ? segment : '{}';
        })
        .join('/');
  }

  static const Set<String> _fixedSegments = <String>{
    'api',
    'v1',
    'auth',
    'login',
    'register',
    'otp',
    'request',
    'resend',
    'verify',
    'password',
    'forgot',
    'reset',
    'logout',
    'me',
    'settings',
    'export',
    'uploads',
    'verifications',
    'contest',
    'courses',
    'lessons',
    'quiz',
    'enroll',
    'complete',
    'attempt',
    'enrollments',
    'resume',
    'progress',
    'certificates',
    'forum',
    'threads',
    'vote',
    'replies',
    'report',
    'hub',
    'articles',
    'opportunities',
    'tools',
    'live-classes',
    'remind',
    'dashboard',
    'stats',
    'wallet',
    'topup',
    'transactions',
    'gamification',
    'referrals',
    'invite',
  };
}
