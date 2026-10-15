import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../domain/entities/stats_statistiques.dart';
import 'profile_dependencies.dart';

/// `statsProvider(period)` — le nom **et sa famille** viennent du simulateur.
///
/// # Pourquoi la famille existe alors qu'aucun sélecteur ne l'utilise
///
/// Le simulateur déclare `statsProvider(period)` mais **n'expose aucun sélecteur
/// de période à l'écran** (réserve D.4). En ajouter un serait inventer un
/// contrôle ; supprimer le paramètre serait effacer un engagement du gabarit.
///
/// Le paramètre reste donc réel, typé par la liste fermée que le contrat
/// accepte — `7d`, `30d`, `90d`, `365d`, « et rien d'autre » — et l'écran passe
/// toujours [PeriodeStats.trenteJours], qui est la période des titres.
// Le type de retour n'est pas ecrit ici, contrairement au reste du depot :
// `AsyncNotifierProviderFamily` n'est pas exporte par `flutter_riverpod`, et
// l'importer depuis `package:riverpod` reviendrait a dependre d'un paquet
// transitif — ce que le socle n'a pas declare. L'inference donne le bon type.
final statsProvider = AsyncNotifierProvider.autoDispose
    .family<StatsNotifier, Statistiques, PeriodeStats>(StatsNotifier.new);

class StatsNotifier extends AsyncNotifier<Statistiques> {
  StatsNotifier(this.periode);

  final PeriodeStats periode;

  @override
  Future<Statistiques> build() =>
      ref.read(profileRepositoryProvider).statistiques(periode: periode);

  Future<void> rafraichir() async {
    state = await AsyncValue.guard(
      () => ref
          .read(profileRepositoryProvider)
          .statistiques(periode: periode, forcerLeReseau: true),
    );
  }
}
