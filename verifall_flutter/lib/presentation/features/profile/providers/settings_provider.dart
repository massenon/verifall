import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../../domain/entities/settings_reglages.dart';
import '../../../../domain/usecases/profile/regler_une_preference.dart';
import 'profile_dependencies.dart';
import '../../../../core/state/riverpod_retry.dart';

/// `settingsProvider` — le nom vient du simulateur.
final AsyncNotifierProvider<SettingsNotifier, Reglages> settingsProvider =
    AsyncNotifierProvider<SettingsNotifier, Reglages>(
      SettingsNotifier.new,
      isAutoDispose: true,
      // Voir [sansReessaiAutomatique] : la reprise est visible, jamais
      // silencieuse.
      retry: sansReessaiAutomatique,
    );

class SettingsNotifier extends AsyncNotifier<Reglages> {
  @override
  Future<Reglages> build() => ref.read(settingsRepositoryProvider).reglages();

  Future<void> rafraichir() async {
    state = await AsyncValue.guard(
      () => ref.read(settingsRepositoryProvider).reglages(forcerLeReseau: true),
    );
  }

  /// Bascule optimiste d'un canal de notification.
  ///
  /// # Ce qui est optimiste, et ce qui ne l'est pas
  ///
  /// L'affichage l'est : l'interrupteur bouge tout de suite. **L'envoi ne l'est
  /// pas** : il porte la valeur voulue, calculée ici, une fois. Un rejeu après
  /// coupure renverra donc `false` s'il a été demandé `false`, et non
  /// « l'inverse de ce qui existe » — c'est toute la différence entre un réglage
  /// qui tient et un réglage qui s'inverse tout seul.
  ///
  /// En cas d'échec, l'interrupteur **revient à son état antérieur** : « un
  /// interrupteur qui reste allumé après un échec est un mensonge ».
  Future<ResultatReglage> definirCanal({
    required String cle,
    required bool valeur,
  }) => _appliquer(
    optimiste: (Reglages courant) => courant.avecCanal(cle, valeur),
    envoi: () =>
        ref.read(reglerUnePreferenceProvider).canal(cle: cle, valeur: valeur),
  );

  /// La lettre d'information — **un consentement horodaté**, pas une préférence
  /// (arbitrage n° 11). Visuellement l'interrupteur ne change pas ;
  /// sémantiquement, il écrit ailleurs.
  Future<ResultatReglage> definirNewsletter({required bool valeur}) =>
      _appliquer(
        optimiste: (Reglages courant) => courant.avecNewsletter(valeur),
        envoi: () =>
            ref.read(reglerUnePreferenceProvider).newsletter(valeur: valeur),
      );

  Future<ResultatReglage> _appliquer({
    required Reglages Function(Reglages courant) optimiste,
    required Future<({ResultatReglage resultat, Reglages? reglages})> Function()
    envoi,
  }) async {
    final Reglages? avant = state.value;
    if (avant == null) {
      // Rien n'a encore ete lu : on ne bascule pas un reglage qu'on ne connait
      // pas, sinon on enverrait une valeur voulue tiree d'un etat inconnu.
      return ResultatReglage.enregistre;
    }

    state = AsyncValue<Reglages>.data(optimiste(avant));

    try {
      final ({ResultatReglage resultat, Reglages? reglages}) issue =
          await envoi();
      if (issue.reglages != null) {
        state = AsyncValue<Reglages>.data(issue.reglages!);
      }
      return issue.resultat;
    } on Object {
      // Retour a l'état anterieur. L'ecran affiche le message du serveur ; ici
      // on ne fait que refuser de laisser l'interface mentir.
      state = AsyncValue<Reglages>.data(avant);
      rethrow;
    }
  }
}

/// La version de l'application, **lue du paquet installé**.
///
/// POURQUOI jamais écrite en dur : « une version figée dans une chaîne de
/// caractères finit toujours par mentir, et c'est la première chose qu'on
/// demande à quelqu'un qui signale un défaut ». Le pied de l'écran Réglages
/// affiche exactement ce que le magasin a installé.
///
/// POURQUOI un provider et pas un appel direct : `PackageInfo.fromPlatform()`
/// exige un canal de plateforme, absent d'un test de widget. Le surcharger est
/// la seule façon de rendre l'écran testable sans démarrer un téléphone.
final FutureProvider<String> versionInstalleeProvider = FutureProvider<String>((
  Ref ref,
) async {
  final PackageInfo infos = await PackageInfo.fromPlatform();
  final String build = infos.buildNumber;
  return build.isEmpty
      ? 'version ${infos.version}'
      : 'version ${infos.version} (build $build)';
});
