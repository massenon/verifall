import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/network_providers.dart';
import '../../../domain/repositories/forum_repository.dart';
import '../../../domain/repositories/hub_repository.dart';
import '../../datasources/local/community/community_cache_datasource.dart';
import '../../datasources/local/database.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/remote/community/forum_remote_datasource.dart';
import '../../datasources/remote/community/hub_remote_datasource.dart';
import 'forum_repository_impl.dart';
import 'hub_repository_impl.dart';

/// Le câblage du parcours M4.
///
/// POURQUOI les providers de dépôt vivent à côté de leur implémentation et non
/// dans `presentation/` : les écrans ne doivent connaître que l'interface du
/// domaine. S'ils construisaient eux-mêmes le dépôt, ils importeraient Dio et
/// Drift — et la règle de dépendance du chantier tomberait au premier écran.
///
/// POURQUOI ils ne sont **pas** `autoDispose` alors que c'est le défaut du
/// projet : un dépôt n'a pas d'état à libérer, et le recréer à chaque
/// navigation reconstruirait le source de données et le cache pour rien. Ce qui
/// est `autoDispose`, ce sont les providers d'écran, qui portent l'état.
final Provider<CommunityCacheDatasource> communityCacheProvider =
    Provider<CommunityCacheDatasource>(
      (Ref ref) =>
          DriftCommunityCacheDatasource(ref.watch(appDatabaseProvider)),
    );

final Provider<ForumRepository> forumRepositoryProvider =
    Provider<ForumRepository>(
      (Ref ref) => ForumRepositoryImpl(
        ForumRemoteDatasource(ref.watch(apiClientProvider)),
        ref.watch(communityCacheProvider),
        ref.watch(pendingOpsQueueProvider),
      ),
    );

final Provider<HubRepository> hubRepositoryProvider = Provider<HubRepository>(
  (Ref ref) => HubRepositoryImpl(
    HubRemoteDatasource(ref.watch(apiClientProvider)),
    ref.watch(communityCacheProvider),
    ref.watch(pendingOpsQueueProvider),
  ),
);
