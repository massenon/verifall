import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/logging/logging_providers.dart';
import '../../../core/network/network_providers.dart';
import '../../../domain/repositories/notification_repository.dart';
import '../../../domain/repositories/tool_repository.dart';
import '../../../domain/repositories/verification_repository.dart';
import '../../datasources/local/database.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/local/verify/verification_local_source.dart';
import '../../datasources/remote/verify/verification_remote_source.dart';
import 'tool_repository_impl.dart';
import 'verification_repository_impl.dart';

/// L'injection du parcours M2, côté « Vérifier ».
///
/// POURQUOI ces providers vivent dans `data/` et non dans un dossier
/// `features/` : ils sont consommés par **deux** dossiers de fonctionnalité —
/// l'accueil et la vérification. Les poser dans l'un obligerait l'autre à
/// l'importer, et « aucun import croisé entre deux dossiers `features/` »
/// (§8.1, règle 4). Le socle place d'ailleurs `pendingOpsStoreProvider` au même
/// endroit, pour la même raison.

final Provider<VerificationRemoteSource> verificationRemoteSourceProvider =
    Provider<VerificationRemoteSource>(
      (Ref ref) => VerificationRemoteSource(ref.watch(apiClientProvider)),
    );

final Provider<VerificationLocalSource> verificationLocalSourceProvider =
    Provider<VerificationLocalSource>(
      (Ref ref) => VerificationLocalSource(ref.watch(appDatabaseProvider)),
    );

final Provider<VerificationRepository> verificationRepositoryProvider =
    Provider<VerificationRepository>(
      (Ref ref) => VerificationRepositoryImpl(
        distant: ref.watch(verificationRemoteSourceProvider),
        local: ref.watch(verificationLocalSourceProvider),
        file: ref.watch(pendingOpsStoreProvider),
        journal: ref.watch(appLoggerProvider),
      ),
    );

final Provider<ToolRepository> toolRepositoryProvider =
    Provider<ToolRepository>(
      (Ref ref) => ToolRepositoryImpl(
        distant: ref.watch(verificationRemoteSourceProvider),
        local: ref.watch(verificationLocalSourceProvider),
      ),
    );

/// Le dépôt des notifications — **branché sur l'implémentation qui refuse**.
///
/// Voir `NotificationsNonServies` : aucun endpoint n'existe, et une liste vide
/// renvoyée en silence serait « un indicateur qu'on ne mesure pas ». Le jour où
/// la table existera, on remplace **cette ligne** et rien d'autre.
final Provider<NotificationRepository> notificationRepositoryProvider =
    Provider<NotificationRepository>(
      (Ref ref) => const NotificationsNonServies(),
    );

// Le solde d'unites vivait ici. Il vit desormais au socle
// (`core/session/solde_unites.dart`) : la pastille qui l'affiche y etait
// deja, et quatre ecrans l'alimentaient de quatre facons differentes.
// Voir l'arbitrage n° 135.
