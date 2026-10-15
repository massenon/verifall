import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/network_providers.dart';
import '../../../domain/repositories/dashboard_repository.dart';
import '../../datasources/local/database.dart';
import '../../datasources/local/home/dashboard_local_source.dart';
import '../../datasources/remote/home/dashboard_remote_source.dart';
import 'dashboard_repository_impl.dart';

/// L'injection du parcours M2, côté « Accueil ».

final Provider<DashboardRemoteSource> dashboardRemoteSourceProvider =
    Provider<DashboardRemoteSource>(
      (Ref ref) => DashboardRemoteSource(ref.watch(apiClientProvider)),
    );

final Provider<DashboardLocalSource> dashboardLocalSourceProvider =
    Provider<DashboardLocalSource>(
      (Ref ref) => DashboardLocalSource(ref.watch(appDatabaseProvider)),
    );

final Provider<DashboardRepository> dashboardRepositoryProvider =
    Provider<DashboardRepository>(
      (Ref ref) => DashboardRepositoryImpl(
        distant: ref.watch(dashboardRemoteSourceProvider),
        local: ref.watch(dashboardLocalSourceProvider),
      ),
    );
