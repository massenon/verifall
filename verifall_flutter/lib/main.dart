import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
// `Override` et les types de famille vivent dans `misc.dart`, que
// `flutter_riverpod` ne reexporte pas (arbitrage n° 142).
import 'package:riverpod/misc.dart' show Override;

import 'app.dart';
import 'core/config/app_config.dart';
import 'core/config/config_providers.dart';
import 'core/network/local_data_purger.dart';
import 'core/network/network_providers.dart';
import 'core/session/deconnexion.dart';
import 'core/state/riverpod_retry.dart';
import 'core/telemetry/identifiant_installation.dart';
import 'core/telemetry/telemetry_providers.dart';
import 'data/datasources/local/database.dart';
import 'data/datasources/remote/auth/nom_appareil.dart';
import 'presentation/features/auth/providers/auth_providers.dart';

/// La racine de composition.
///
/// POURQUOI tout le câblage est ici et nulle part ailleurs : c'est le seul
/// endroit de l'application qui a le droit de connaître à la fois `core/` et
/// `data/`. Les deux règles de dépendance (§1.2) tiennent parce que ce fichier
/// existe.
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // La configuration est lue UNE SEULE FOIS, au demarrage. Le reste du code lit
  // cette instance, jamais l'environnement (§1.7).
  final AppConfig config = AppConfig.fromEnvironment();

  // Le garde-fou de derniere ligne : la session de developpement ne peut pas
  // exister en production. Meme raisonnement que l'adaptateur simule du back,
  // qui « refuse de tourner en production » (arbitrage n° 19).
  config.assertNoDeveloperSessionInProduction();

  // La version vient du paquet installe, jamais d'une chaine ecrite a la main
  // (07-LIVRAISON §7.3).
  final String clientVersion = await readClientVersion();

  // Le modele reel du telephone, lu une fois. Il NOMME le jeton dans la liste
  // des sessions ; `null` quand la plateforme ne dit rien d'utile, et le champ
  // est alors simplement absent (arbitrage n° 141).
  final String? nomAppareil = await readDeviceName();

  // L'identifiant ANONYME d'installation, resolu avant tout le reste : le
  // gestionnaire d'erreur global ne peut pas attendre une `Future`, il doit
  // etre pret a la premiere exception, y compris celle du demarrage (07-
  // LIVRAISON §7.6).
  final String identifiantInstallation = await IdentifiantDInstallation()
      .lireOuCreer();

  final ProviderContainer container = ProviderContainer(
    // LA politique de reessai des providers, posee UNE FOIS pour les cinq
    // parcours. Riverpod 3 rejoue de lui-meme un provider en echec ; la reprise
    // de l'application est deja ecrite dans les intercepteurs du socle, et une
    // seconde couche invisible depuis l'ecran multiplierait les appels
    // (arbitrage n° 132).
    retry: sansReessaiAutomatique,
    // Le type est ECRIT. Il ne l'etait pas : `Override` n'est pas reexporte par
    // `flutter_riverpod`, et le nommer exigeait de dependre d'un paquet
    // transitif. `riverpod` est declare depuis l'arbitrage n° 142.
    overrides: <Override>[
      appConfigProvider.overrideWithValue(config),
      clientVersionProvider.overrideWithValue(clientVersion),
      identifiantResoluProvider.overrideWithValue(identifiantInstallation),
      // Le pont entre `core/` et Drift. Il n'y a pas d'implementation par
      // defaut : une purge qui ne purge rien, en silence, est une fuite de
      // donnees sur un telephone partage.
      localDataPurgerProvider.overrideWith(
        (Ref ref) => PurgeurCompose(<LocalDataPurger>[
          DriftLocalDataPurger(ref.watch(appDatabaseProvider)),
          // L'identifiant ANONYME de telemetrie est une donnee locale comme une
          // autre : le §7.6 veut qu'il « se purge a la deconnexion, comme le
          // reste ». Sur un telephone partage — le cas ordinaire au Benin
          // (arbitrage n° 59) — le conserver rattacherait les incidents de la
          // personne SUIVANTE a la trace de la precedente.
          PurgeDeLIdentifiant(ref.watch(identifiantDInstallationProvider)),
        ]),
      ),
      // La deconnexion COMPLETE : elle revoque le jeton cote serveur avant de
      // purger. Le defaut du socle ne fait que purger localement — il protege,
      // mais laisse le jeton vivant jusqu'a son echeance. La mecanique de
      // session appartient a M1 (§8.2) ; c'est ici qu'elle se branche.
      deconnexionProvider.overrideWith(
        (Ref ref) =>
            () => ref.read(fermerSessionProvider)(),
      ),
      // Le modele reel du telephone. `NomAppareilSysteme` reste le repli quand
      // la plateforme ne dit rien : il envoie le systeme et sa version, ce qui
      // est moins precis mais jamais faux.
      if (nomAppareil != null)
        nomAppareilProvider.overrideWithValue(NomAppareilFixe(nomAppareil)),
    ],
  );

  /*
   | LES GESTIONNAIRES D'ERREUR GLOBAUX.
   |
   | Ils n'existaient PAS : ni `FlutterError.onError`, ni
   | `PlatformDispatcher.instance.onError`, ni `runZonedGuarded`. Une exception
   | non capturee s'imprimait dans la console de developpement et disparaissait.
   | Sur le telephone de quelqu'un, elle ne laissait aucune trace nulle part —
   | et le champ `telemetryDsn` de la configuration etait lu sans qu'aucun
   | fichier ne le consomme.
   |
   | Rien ne quitte l'appareil pour autant : la transmission attend un puits
   | configure ET un consentement, dont aucun n'existe encore. Ce qui change,
   | c'est qu'un incident est desormais JOURNALISE au lieu d'etre perdu.
   */
  container.read(rapporteurDePlantageProvider).brancher();

  runApp(
    UncontrolledProviderScope(container: container, child: const VerifAllApp()),
  );
}
