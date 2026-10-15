import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../domain/entities/gamification_recompenses.dart';
import '../../../../domain/repositories/gamification_repository.dart';
import 'profile_dependencies.dart';
import '../../../../core/state/riverpod_retry.dart';

/// L'état de l'écran 27.
@immutable
class EtatRecompenses {
  const EtatRecompenses({
    required this.recompenses,
    required this.certificats,
    this.certificatsIndisponibles = false,
  });

  final Recompenses recompenses;
  final List<Certificat> certificats;

  /// Vrai quand la liste des certificats n'a pas pu être lue **du tout** —
  /// ni du réseau, ni du cache.
  ///
  /// POURQUOI la distinction : une liste vide veut dire « terminez un cours pour
  /// obtenir votre premier certificat », une liste indisponible veut dire « nous
  /// n'avons pas pu la lire ». Les confondre proposerait de commencer un cours à
  /// quelqu'un qui en a déjà terminé quatre.
  final bool certificatsIndisponibles;
}

/// `gamificationProvider` — le nom vient du simulateur.
final AsyncNotifierProvider<GamificationNotifier, EtatRecompenses>
gamificationProvider =
    AsyncNotifierProvider<GamificationNotifier, EtatRecompenses>(
      GamificationNotifier.new,
      isAutoDispose: true,
      retry: sansReessaiAutomatique,
    );

class GamificationNotifier extends AsyncNotifier<EtatRecompenses> {
  GamificationRepository get _depot => ref.read(gamificationRepositoryProvider);

  @override
  Future<EtatRecompenses> build() => _charger();

  Future<void> rafraichir() async {
    state = await AsyncValue.guard(_charger);
  }

  Future<EtatRecompenses> _charger() async {
    // L'appel RECALCULE les badges avant de répondre : « un badge merite qu'on
    // ne decouvre qu'au prochain passage est un badge qui semble ne pas
    // marcher ». Le client, lui, ne decide jamais qu'un badge est nouveau.
    final Recompenses recompenses = await _depot.recompenses();

    try {
      return EtatRecompenses(
        recompenses: recompenses,
        certificats: await _depot.certificats(),
      );
    } on Failure {
      return EtatRecompenses(
        recompenses: recompenses,
        certificats: const <Certificat>[],
        certificatsIndisponibles: true,
      );
    }
  }
}
