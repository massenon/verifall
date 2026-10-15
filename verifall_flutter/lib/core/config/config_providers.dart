import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app_config.dart';
import 'app_environment.dart';

/// La configuration, fournie par Riverpod.
///
/// POURQUOI un provider surchargé au démarrage plutôt qu'un `AppConfig.instance`
/// statique : « aucun singleton statique » (§1.5). Un objet global survit à la
/// déconnexion et garde en mémoire ce qui devait être purgé ; et en test, on
/// remplace le provider, pas la classe.
///
/// Le socle le surcharge dans `main.dart` avec `AppConfig.fromEnvironment()`.
/// Laisser une erreur ici plutôt qu'une valeur par défaut est délibéré : une
/// configuration par défaut silencieuse est une adresse d'API qui pointe nulle
/// part et un bogue qu'on cherche pendant une heure.
final Provider<AppConfig> appConfigProvider = Provider<AppConfig>(
  (Ref ref) => throw StateError(
    'appConfigProvider doit être surcharge au demarrage '
    '(ProviderScope(overrides: [...])).',
  ),
);

/// Raccourci de lecture, employé par les gardes et par la journalisation.
final Provider<AppEnvironment> appEnvironmentProvider =
    Provider<AppEnvironment>(
      (Ref ref) => ref.watch(appConfigProvider).environment,
    );
