import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/network/connectivity_providers.dart';
import '../../../../core/network/network_providers.dart';
import '../../../../data/datasources/local/database.dart';
import '../../../../data/datasources/local/pending_ops.dart';
import '../../../../data/datasources/local/profile/compte_local_datasource.dart';
import '../../../../data/datasources/remote/profile/compte_remote_datasource.dart';
import '../../../../data/repositories/profile/gamification_repository_impl.dart';
import '../../../../data/repositories/profile/profile_repository_impl.dart';
import '../../../../data/repositories/profile/referral_repository_impl.dart';
import '../../../../data/repositories/profile/settings_repository_impl.dart';
import '../../../../data/repositories/profile/wallet_repository_impl.dart';
import '../../../../domain/repositories/gamification_repository.dart';
import '../../../../domain/repositories/profile_repository.dart';
import '../../../../domain/repositories/referral_repository.dart';
import '../../../../domain/repositories/settings_repository.dart';
import '../../../../domain/repositories/wallet_repository.dart';
import '../../../../domain/usecases/profile/enregistrer_le_profil.dart';
import '../../../../domain/usecases/profile/exporter_mes_donnees.dart';
import '../../../../domain/usecases/profile/recharger_des_unites.dart';
import '../../../../domain/usecases/profile/regler_une_preference.dart';
import '../../../../domain/usecases/profile/supprimer_mon_compte.dart';

/// Le câblage de M5.
///
/// POURQUOI tout est ici et pas éparpillé dans les six écrans : c'est le seul
/// fichier qu'un test doit surcharger pour remplacer le réseau et la base. Six
/// providers déclarés dans six pages auraient donné six surcharges à écrire dans
/// chaque test, et l'une des six aurait fini par être oubliée — un test qui
/// appelle vraiment le réseau ne se voit qu'en salle de recette.

// --- Sources ----------------------------------------------------------------

final Provider<CompteRemoteDataSource> compteRemoteDataSourceProvider =
    Provider<CompteRemoteDataSource>(
      (Ref ref) => CompteRemoteDataSource(client: ref.watch(apiClientProvider)),
    );

final Provider<CompteLocalDataSource> compteLocalDataSourceProvider =
    Provider<CompteLocalDataSource>(
      (Ref ref) => DriftCompteLocalDataSource(ref.watch(appDatabaseProvider)),
    );

// --- Depots -----------------------------------------------------------------

final Provider<ProfileRepository> profileRepositoryProvider =
    Provider<ProfileRepository>(
      (Ref ref) => ProfileRepositoryImpl(
        distant: ref.watch(compteRemoteDataSourceProvider),
        local: ref.watch(compteLocalDataSourceProvider),
        file: ref.watch(pendingOpsQueueProvider),
      ),
    );

final Provider<WalletRepository> walletRepositoryProvider =
    Provider<WalletRepository>(
      (Ref ref) => WalletRepositoryImpl(
        distant: ref.watch(compteRemoteDataSourceProvider),
      ),
    );

final Provider<GamificationRepository> gamificationRepositoryProvider =
    Provider<GamificationRepository>(
      (Ref ref) => GamificationRepositoryImpl(
        distant: ref.watch(compteRemoteDataSourceProvider),
        local: ref.watch(compteLocalDataSourceProvider),
      ),
    );

final Provider<ReferralRepository> referralRepositoryProvider =
    Provider<ReferralRepository>(
      (Ref ref) => ReferralRepositoryImpl(
        distant: ref.watch(compteRemoteDataSourceProvider),
        local: ref.watch(compteLocalDataSourceProvider),
      ),
    );

final Provider<SettingsRepository> settingsRepositoryProvider =
    Provider<SettingsRepository>(
      (Ref ref) => SettingsRepositoryImpl(
        distant: ref.watch(compteRemoteDataSourceProvider),
        local: ref.watch(compteLocalDataSourceProvider),
        file: ref.watch(pendingOpsQueueProvider),
      ),
    );

// --- Reseau -----------------------------------------------------------------

/// « Sommes-nous hors ligne ? », sous une forme injectable.
///
/// POURQUOI une fonction et pas un booléen lu directement : les trois actes qui
/// refusent le hors ligne — recharge, export, suppression — le consultent **au
/// moment du geste**, pas au moment où l'écran a été construit. Entre les deux,
/// on peut avoir perdu ou retrouvé le réseau.
///
/// POURQUOI l'absence de réponse vaut « en ligne » : « ne jamais bloquer
/// l'interface sur cet état ; on tente, et on traite l'échec » (05-HORS-LIGNE
/// §5.12). Tant que la connectivité n'a rien dit, on essaie.
final Provider<Future<bool> Function()> horsLigneProvider =
    Provider<Future<bool> Function()>((Ref ref) {
      return () async =>
          ref.read(networkStatusProvider).value == NetworkStatus.horsLigne;
    });

// --- Cas d'usage ------------------------------------------------------------

final Provider<EnregistrerLeProfil> enregistrerLeProfilProvider =
    Provider<EnregistrerLeProfil>(
      (Ref ref) => EnregistrerLeProfil(ref.watch(profileRepositoryProvider)),
    );

final Provider<RechargerDesUnites> rechargerDesUnitesProvider =
    Provider<RechargerDesUnites>(
      (Ref ref) => RechargerDesUnites(
        repository: ref.watch(walletRepositoryProvider),
        estHorsLigne: ref.watch(horsLigneProvider),
      ),
    );

final Provider<ReglerUnePreference> reglerUnePreferenceProvider =
    Provider<ReglerUnePreference>(
      (Ref ref) => ReglerUnePreference(ref.watch(settingsRepositoryProvider)),
    );

final Provider<ExporterMesDonnees> exporterMesDonneesProvider =
    Provider<ExporterMesDonnees>(
      (Ref ref) => ExporterMesDonnees(
        repository: ref.watch(settingsRepositoryProvider),
        estHorsLigne: ref.watch(horsLigneProvider),
      ),
    );

final Provider<SupprimerMonCompte> supprimerMonCompteProvider =
    Provider<SupprimerMonCompte>(
      (Ref ref) => SupprimerMonCompte(
        repository: ref.watch(settingsRepositoryProvider),
        estHorsLigne: ref.watch(horsLigneProvider),
      ),
    );

// --- La deconnexion ---------------------------------------------------------
//
// `deconnexionProvider` vivait ici : c'etait « la seule dependance de M5 vers
// M1 ». Il vit desormais au socle (`core/session/deconnexion.dart`), parce que
// le bouton rouge du tiroir en a besoin lui aussi et qu'un composant du socle
// ne peut pas importer un provider de parcours. M5 le lit, il ne le declare
// plus — et sa dependance vers M1 a disparu. Voir l'arbitrage n° 137.

// --- Reessai des providers --------------------------------------------------
//
// `sansReessaiAutomatique` vivait ici. Il vit desormais au socle
// (`core/state/riverpod_retry.dart`) : M2 ecrivait la meme ligne pour la meme
// raison, et M1, M3 et M4 ne l'ecrivaient pas du tout — trois parcours
// rejouaient donc en silence pendant que deux s'en gardaient. Le reglage est
// maintenant pose une fois, sur le conteneur. Voir l'arbitrage n° 132.
