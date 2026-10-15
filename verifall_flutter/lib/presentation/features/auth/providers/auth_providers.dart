import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/logging/logging_providers.dart';
import '../../../../core/network/auth_controller.dart';
import '../../../../core/network/local_data_purger.dart';
import '../../../../core/network/network_providers.dart';
import '../../../../data/datasources/remote/auth/auth_remote_datasource.dart';
import '../../../../data/datasources/remote/auth/nom_appareil.dart';
import '../../../../data/repositories/auth/auth_repository_impl.dart';
import '../../../../domain/repositories/auth_repository.dart';
import '../../../../domain/usecases/auth/fermer_session.dart';
import '../../../../domain/usecases/auth/ouvrir_session.dart';

/// Le câblage de M1.
///
/// POURQUOI tout est ici et pas éparpillé dans les six écrans : c'est le seul
/// fichier qu'un test doit surcharger pour remplacer le réseau. Six providers
/// déclarés dans six pages auraient donné six surcharges à écrire dans chaque
/// test, et l'une des six aurait fini par être oubliée — un test qui appelle
/// vraiment le réseau ne se voit qu'en salle de recette.

/// Comment l'application se nomme auprès du serveur.
///
/// Surchargeable : c'est le point d'injection qui accueillera `device_info_plus`
/// le jour où le socle le déclarera (voir `nom_appareil.dart`).
final Provider<NomAppareil> nomAppareilProvider = Provider<NomAppareil>(
  (Ref ref) => const NomAppareilSysteme(),
);

final Provider<AuthRemoteDataSource> authRemoteDataSourceProvider =
    Provider<AuthRemoteDataSource>(
      (Ref ref) => AuthRemoteDataSource(
        ref.watch(apiClientProvider),
        ref.watch(nomAppareilProvider),
      ),
    );

final Provider<AuthRepository> authRepositoryProvider =
    Provider<AuthRepository>(
      (Ref ref) => AuthRepositoryImpl(ref.watch(authRemoteDataSourceProvider)),
    );

/// **Le** chemin par lequel un jeton entre dans l'application.
///
/// Les trois contrôleurs d'écran qui reçoivent un jeton — connexion, code,
/// nouveau mot de passe — passent par ce provider et par aucun autre.
final Provider<OuvrirSession> ouvrirSessionProvider = Provider<OuvrirSession>(
  (Ref ref) => OuvrirSession(
    ref.watch(authControllerProvider.notifier),
    ref.watch(tokenStoreProvider),
    ref.watch(localDataPurgerProvider),
  ),
);

/// La déconnexion et sa purge.
///
/// POURQUOI elle est exposée par M1 alors que le bouton appartient à M5 : la
/// mécanique de session est à M1, et deux purges écrites séparément en
/// oublieraient chacune une moitié.
final Provider<FermerSession> fermerSessionProvider = Provider<FermerSession>(
  (Ref ref) => FermerSession(
    ref.watch(authRepositoryProvider),
    ref.watch(authControllerProvider.notifier),
    ref.watch(appLoggerProvider),
  ),
);
