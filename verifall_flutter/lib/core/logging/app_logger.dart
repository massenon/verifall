import 'dart:developer' as developer;

import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import 'log_redactor.dart';

/// Le niveau de gravité. Volontairement court : cinq niveaux se choisissent mal
/// et finissent tous en `info`.
enum LogLevel {
  debug(500, 'DEBUG'),
  info(800, 'INFO'),
  warning(900, 'WARN'),
  error(1000, 'ERROR');

  const LogLevel(this.value, this.label);

  final int value;
  final String label;
}

/// Ce qu'on journalise, et qui sert vraiment (§1.6).
///
/// POURQUOI une énumération plutôt qu'une chaîne libre : un canal libre devient
/// dix orthographes du même mot, et on ne peut plus filtrer un journal.
enum LogChannel {
  /// Transitions d'authentification.
  session('session'),

  /// Échecs réseau avec leur code.
  network('network'),

  /// Rejeu de la file hors ligne.
  pendingOps('pending-ops'),

  /// Cycle de vie de l'application — il explique la moitié des bizarreries.
  lifecycle('lifecycle'),

  /// Navigation et gardes de route.
  router('router'),

  /// Base locale.
  database('database');

  const LogChannel(this.name);

  final String name;
}

/// Le logger unique du socle (§1.6).
///
/// Trois exigences, dont aucune n'est optionnelle :
///
/// 1. **Rien de sensible ne sort** — [LogRedactor] est appliqué à tout ce qui
///    traverse, sans exception et sans possibilité de contournement.
/// 2. **Rien en production sur la console** — les journaux de diffusion partent
///    vers la télémétrie de plantage (07-LIVRAISON §7.6), pas vers la sortie
///    standard. Une console de production est lisible par n'importe quel outil
///    branché sur l'appareil.
/// 3. **Un identifiant de corrélation par requête**, posé par le client réseau
///    et repris ici via [correlationId].
class AppLogger {
  AppLogger({required this.config, LogSink? sink})
    : sink = sink ?? const DeveloperLogSink();

  /// Les deux dependances sont publiques et finales : elles sont injectees, pas
  /// mutables. Un test qui verifie qu'un jeton ne sort jamais d'un journal doit
  /// pouvoir relire la destination.
  final AppConfig config;
  final LogSink sink;

  void debug(
    LogChannel channel,
    String message, {
    Object? data,
    String? correlationId,
  }) => _write(
    LogLevel.debug,
    channel,
    message,
    data: data,
    correlationId: correlationId,
  );

  void info(
    LogChannel channel,
    String message, {
    Object? data,
    String? correlationId,
  }) => _write(
    LogLevel.info,
    channel,
    message,
    data: data,
    correlationId: correlationId,
  );

  void warning(
    LogChannel channel,
    String message, {
    Object? data,
    String? correlationId,
  }) => _write(
    LogLevel.warning,
    channel,
    message,
    data: data,
    correlationId: correlationId,
  );

  void error(
    LogChannel channel,
    String message, {
    Object? data,
    Object? cause,
    StackTrace? stackTrace,
    String? correlationId,
  }) => _write(
    LogLevel.error,
    channel,
    message,
    data: data,
    cause: cause,
    stackTrace: stackTrace,
    correlationId: correlationId,
  );

  void _write(
    LogLevel level,
    LogChannel channel,
    String message, {
    Object? data,
    Object? cause,
    StackTrace? stackTrace,
    String? correlationId,
  }) {
    // En production, seuls les avertissements et les erreurs ont une raison
    // d'exister : ils partent vers la telemetrie. Le reste est du bruit qu'on
    // paierait en batterie et en fuite d'information.
    if (config.environment.isProduction &&
        level.value < LogLevel.warning.value) {
      return;
    }

    final LogEntry entry = LogEntry(
      level: level,
      channel: channel,
      // Le message lui-meme est expurge : il arrive qu'on y interpole une valeur
      // sans y penser.
      message: LogRedactor.redactFreeText(message),
      data: data == null ? null : LogRedactor.redact(data),
      // `cause.toString()` d'une exception recopie volontiers une URL complete.
      cause: cause == null ? null : LogRedactor.redactFreeText('$cause'),
      stackTrace: stackTrace,
      correlationId: correlationId,
      environmentIsProduction: config.environment.isProduction,
    );

    sink.write(entry);
  }
}

/// Une ligne de journal, déjà expurgée.
@immutable
class LogEntry {
  const LogEntry({
    required this.level,
    required this.channel,
    required this.message,
    required this.environmentIsProduction,
    this.data,
    this.cause,
    this.stackTrace,
    this.correlationId,
  });

  final LogLevel level;
  final LogChannel channel;
  final String message;
  final Object? data;
  final String? cause;
  final StackTrace? stackTrace;
  final String? correlationId;
  final bool environmentIsProduction;

  @override
  String toString() {
    final StringBuffer buffer = StringBuffer()
      ..write('[${level.label}] ')
      ..write('${channel.name} ');
    if (correlationId != null) {
      buffer.write('($correlationId) ');
    }
    buffer.write(message);
    if (data != null) {
      buffer.write(' | $data');
    }
    if (cause != null) {
      buffer.write(' | cause: $cause');
    }
    return buffer.toString();
  }
}

/// La destination d'un journal.
///
/// POURQUOI une abstraction : en test on capture, en production on envoie à la
/// télémétrie, en développement on écrit sur la console. Trois destinations, un
/// seul chemin d'écriture.
abstract interface class LogSink {
  void write(LogEntry entry);
}

/// La destination de développement : `dart:developer`, jamais `print`.
///
/// POURQUOI `developer.log` : il porte le niveau, le nom du canal et l'erreur,
/// ce qu'une chaîne concaténée sur `stdout` perd. Et il reste muet en production
/// parce que [AppLogger] filtre en amont.
class DeveloperLogSink implements LogSink {
  const DeveloperLogSink();

  @override
  void write(LogEntry entry) {
    // La telemetrie de plantage (section 7) se branchera ici, derriere le meme
    // contrat : elle recevra des entrees DEJA expurgees.
    if (entry.environmentIsProduction) {
      return;
    }
    developer.log(
      entry.toString(),
      name: 'verifall.${entry.channel.name}',
      level: entry.level.value,
      stackTrace: entry.stackTrace,
    );
  }
}

/// Destination de test : elle garde tout en mémoire.
///
/// Elle vit dans `lib/` et non dans `test/` parce que les tests de plusieurs
/// parcours en auront besoin, et qu'un composant partagé dupliqué diverge en une
/// semaine (§8.1, règle 3).
class RecordingLogSink implements LogSink {
  final List<LogEntry> entries = <LogEntry>[];

  @override
  void write(LogEntry entry) => entries.add(entry);

  void clear() => entries.clear();
}
