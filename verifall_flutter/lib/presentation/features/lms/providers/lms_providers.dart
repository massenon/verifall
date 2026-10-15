import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/network/network_providers.dart';
import '../../../../data/datasources/local/database.dart';
import '../../../../data/datasources/local/lms/lms_local_data_source.dart';
import '../../../../data/datasources/local/pending_ops.dart';
import '../../../../data/datasources/remote/lms/lms_remote_data_source.dart';
import '../../../../data/repositories/lms/certificate_repository_impl.dart';
import '../../../../data/repositories/lms/course_repository_impl.dart';
import '../../../../data/repositories/lms/live_class_repository_impl.dart';
import '../../../../data/repositories/lms/quiz_repository_impl.dart';
import '../../../../domain/repositories/certificate_repository.dart';
import '../../../../domain/repositories/course_repository.dart';
import '../../../../domain/repositories/live_class_repository.dart';
import '../../../../domain/repositories/quiz_repository.dart';
import '../../../../domain/usecases/lms/telecharger_cours.dart';

/// Le câblage du parcours M3.
///
/// POURQUOI tout passe par des providers : « un provider par dépendance
/// externe » (§1.5), et « aucun singleton statique — un objet global survivrait
/// à la déconnexion et garderait en mémoire ce qui devait être purgé ». C'est
/// aussi ce qui permet à un test de widget de remplacer un dépôt entier sans
/// toucher au réseau ni à la base.

final Provider<LmsRemoteDataSource> lmsRemoteProvider =
    Provider<LmsRemoteDataSource>(
      (Ref ref) => LmsRemoteDataSource(ref.watch(apiClientProvider)),
    );

final Provider<LmsLocalDataSource> lmsLocalProvider =
    Provider<LmsLocalDataSource>(
      (Ref ref) => LmsLocalDataSource(ref.watch(appDatabaseProvider)),
    );

final Provider<CourseRepository> courseRepositoryProvider =
    Provider<CourseRepository>(
      (Ref ref) => CourseRepositoryImpl(
        distant: ref.watch(lmsRemoteProvider),
        local: ref.watch(lmsLocalProvider),
        file: ref.watch(pendingOpsQueueProvider),
      ),
    );

final Provider<QuizRepository> quizRepositoryProvider =
    Provider<QuizRepository>(
      (Ref ref) => QuizRepositoryImpl(
        distant: ref.watch(lmsRemoteProvider),
        // La copie de quiz passe par le MAGASIN et non par la file : le dépôt
        // doit choisir lui-même la clé d'idempotence de l'acte, puis tenter
        // l'envoi immédiat avec cette clé-là. Une clé regénérée à l'envoi ne
        // protégerait de rien (§7.3).
        fileStore: ref.watch(pendingOpsStoreProvider),
      ),
    );

final Provider<CertificateRepository> certificateRepositoryProvider =
    Provider<CertificateRepository>(
      (Ref ref) => CertificateRepositoryImpl(
        distant: ref.watch(lmsRemoteProvider),
        local: ref.watch(lmsLocalProvider),
      ),
    );

final Provider<LiveClassRepository> liveClassRepositoryProvider =
    Provider<LiveClassRepository>(
      (Ref ref) => LiveClassRepositoryImpl(
        distant: ref.watch(lmsRemoteProvider),
        local: ref.watch(lmsLocalProvider),
        file: ref.watch(pendingOpsQueueProvider),
      ),
    );

final Provider<TelechargerCours> telechargerCoursProvider =
    Provider<TelechargerCours>(
      (Ref ref) => TelechargerCours(ref.watch(courseRepositoryProvider)),
    );
