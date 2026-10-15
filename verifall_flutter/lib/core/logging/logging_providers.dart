import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../config/config_providers.dart';
import 'app_logger.dart';

/// Le logger unique, injecté par Riverpod.
///
/// POURQUOI un provider et pas une variable globale : un test qui veut vérifier
/// qu'un jeton n'est jamais journalisé remplace ce provider par un logger à
/// destination [RecordingLogSink]. Avec une globale, il faudrait relire la
/// console.
final Provider<AppLogger> appLoggerProvider = Provider<AppLogger>((Ref ref) {
  final AppConfig config = ref.watch(appConfigProvider);
  return AppLogger(config: config);
});
