import 'package:verifall_app/core/config/app_config.dart';
import 'package:verifall_app/core/config/app_environment.dart';
import 'package:verifall_app/core/network/local_data_purger.dart';
import 'package:verifall_app/data/datasources/local/pending_ops.dart';
import 'package:verifall_app/data/datasources/local/tables/pending_ops_table.dart';

/// Les doublures du socle.
///
/// POURQUOI elles vivent dans `test/support/` et pas dans `lib/` : elles ne sont
/// utiles qu'aux tests, et une doublure livrée dans le paquet finit par être
/// utilisée pour de vrai par mégarde.
///
/// POURQUOI on ne se sert d'aucune bibliothèque de simulacres : la règle du
/// chantier « jamais les données du simulateur en dur dans un test » (§6.8) va
/// avec des fabriques explicites. Une doublure écrite à la main dit ce qu'elle
/// fait ; un simulacre configuré à distance ne le dit pas.

/// Une configuration de test, neutre par défaut.
AppConfig testConfig({
  AppEnvironment environment = AppEnvironment.development,
  String apiBaseUrl = 'https://exemple.invalid',
  String webBaseUrl = '',
  bool devSession = false,
  String devToken = '',
}) {
  return AppConfig(
    environment: environment,
    apiBaseUrl: apiBaseUrl,
    webBaseUrl: webBaseUrl,
    realtimeHost: '',
    realtimeKey: '',
    realtimeCluster: '',
    realtimeUseTls: true,
    telemetryDsn: '',
    // Le meme clamp que le constructeur reel : la session de developpement ne
    // peut pas exister en production, meme dans un test.
    devSessionEnabled: devSession && environment.allowsDeveloperTools,
    devSessionToken: devToken,
    devSessionUserUuid: 'uuid-de-test',
    devSessionUserPrenom: 'Test',
    devSessionUserEmail: 'test@exemple.invalid',
    devSessionUserUnites: 0,
  );
}

/// Une purge qui compte les appels, pour vérifier qu'elle a bien eu lieu.
class RecordingLocalDataPurger implements LocalDataPurger {
  int calls = 0;

  @override
  Future<void> purgeAll() async => calls++;
}

/// Un magasin de file en mémoire.
///
/// POURQUOI il existe alors que Drift a un mode « en mémoire » : `flutter test`
/// tourne sur la machine hôte, où la bibliothèque native SQLite n'est pas
/// forcément installée. Tester la logique de rejeu — ordre, recul, abandon,
/// retrait d'une opération orpheline — ne doit pas dépendre de cela.
class InMemoryPendingOpsStore implements PendingOpsStore {
  final List<PendingOp> ops = <PendingOp>[];

  @override
  Future<void> enqueue(PendingOp op) async {
    ops.removeWhere((PendingOp o) => o.clientUuid == op.clientUuid);
    ops.add(op);
  }

  @override
  Future<List<PendingOp>> all() async {
    final List<PendingOp> sorted = List<PendingOp>.of(ops)
      ..sort((PendingOp a, PendingOp b) => a.createdAt.compareTo(b.createdAt));
    return sorted;
  }

  @override
  Future<PendingOp?> find(String clientUuid) async {
    for (final PendingOp op in ops) {
      if (op.clientUuid == clientUuid) {
        return op;
      }
    }
    return null;
  }

  @override
  Future<void> update(PendingOp op) async {
    final int index = ops.indexWhere(
      (PendingOp o) => o.clientUuid == op.clientUuid,
    );
    if (index >= 0) {
      ops[index] = op;
    }
  }

  @override
  Future<void> remove(String clientUuid) async {
    ops.removeWhere((PendingOp o) => o.clientUuid == clientUuid);
  }

  @override
  Future<void> clear() async => ops.clear();

  @override
  Stream<int> watchPendingCount() => Stream<int>.value(
    ops.where((PendingOp o) => o.status != PendingOpStatus.enPanne).length,
  );
}
