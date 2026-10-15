import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';
import 'package:flutter/services.dart' show MissingPluginException;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../../core/network/local_data_purger.dart';
import 'tables/pending_ops_table.dart';

part 'database.g.dart';

/// La base locale.
///
/// # Pourquoi ce fichier est gelé
///
/// C'est l'un des **deux** endroits où les cinq parcours devraient normalement
/// écrire tous ensemble — l'autre est `core/router/app_router.dart`. On l'évite
/// par la même mécanique : les **six fichiers de schéma sont importés d'avance**,
/// un par module, vides pour ceux qui n'ont encore rien à cacher. Chaque parcours
/// ajoute ses tables dans **son** fichier `.drift`, personne ne rouvre celui-ci,
/// et les cinq branches se fusionnent sans conflit.
///
/// # Pourquoi les tables des modules sont en `.drift` et pas en Dart
///
/// Une annotation `@DriftDatabase(tables: [...])` est une **liste littérale** :
/// cinq parcours qui y ajoutent chacun une ligne produisent cinq conflits sur
/// les mêmes lignes. Un `include:` de fichiers `.drift` déplace la déclaration
/// dans le fichier du parcours, où elle n'est en concurrence avec personne.
///
/// # Pourquoi il n'y a pas de suite de migrations numérotées
///
/// La base locale est un **cache de lecture jetable** plus une file d'écriture.
/// Les tables manquantes sont créées à l'ouverture (`CREATE TABLE IF NOT
/// EXISTS`), ce qui permet à un parcours d'ajouter une table sans toucher à ce
/// fichier ni au numéro de version. En échange, une modification de colonne
/// **sur une table existante** exige une vraie migration — et c'est une demande
/// adressée au responsable du socle, pas une modification directe (§8.1,
/// règle 2).
@DriftDatabase(
  tables: <Type>[PendingOps],
  include: <String>{
    'schema/schema_auth.drift',
    'schema/schema_home.drift',
    'schema/schema_verify.drift',
    'schema/schema_lms.drift',
    'schema/schema_community.drift',
    'schema/schema_profile.drift',
  },
)
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_open());

  /// Constructeur de test : n'importe quel exécuteur, y compris en mémoire.
  AppDatabase.withExecutor(super.executor);

  @override
  int get schemaVersion => 1;

  @override
  MigrationStrategy get migration => MigrationStrategy(
    onCreate: (Migrator m) => m.createAll(),
    /*
     | LA montée de version : on RECONSTRUIT le cache, on PRÉSERVE la file.
     |
     | Ce que faisait ce point avant, et pourquoi c'était une bombe à retardement
     | ------------------------------------------------------------------------
     | Il appelait `m.createAll()`, qui émet des `CREATE TABLE IF NOT EXISTS`.
     | Sur une installation existante, cela crée les tables absentes et **ne
     | touche à aucune table déjà présente**. Ajouter une colonne puis passer
     | `schemaVersion` à 2 n'aurait donc produit AUCUN `ALTER TABLE` : au
     | premier lancement d'après mise à jour, la requête aurait cherché une
     | colonne que SQLite n'a pas, et l'application aurait planté à l'ouverture
     | — pour tous ceux qui METTENT À JOUR, jamais pour ceux qui installent.
     | Seule une désinstallation les en aurait sortis, et `purgeAll()` n'aurait
     | rien rattrapé : c'est la FORME de la table qui est fausse, pas son
     | contenu. Invisible tant que `schemaVersion` valait 1, puisque ce bloc
     | n'avait jamais tourné une seule fois.
     |
     | Pourquoi effacer plutôt que migrer colonne par colonne
     | ------------------------------------------------------
     | Parce que la nature des données le dit : cette base est « un cache de
     | lecture jetable plus une file d'écriture ». Un cache se **rebâtit depuis
     | le serveur** — le reconstruire coûte un rechargement, l'écrire en
     | `stepByStep` sur six modules coûterait une suite de migrations à tenir
     | pour des données qu'on jette de toute façon à la déconnexion.
     |
     | La file d'écriture, elle, ne se rebâtit PAS : `pending_ops` contient le
     | travail que la personne a fait hors ligne et que le serveur n'a pas
     | encore reçu. L'effacer ferait disparaître son message, son vote, sa leçon
     | terminée. Elle est donc la seule table épargnée.
     |
     | Ce que cela impose au socle
     | ---------------------------
     | Le jour où `pending_ops` elle-même change de forme, ce raccourci ne suffit
     | plus : il faudra une vraie étape de migration pour elle seule. C'est la
     | demande adressée au responsable du socle dont parle le bloc ci-dessus
     | (§8.1, règle 2), et elle ne concerne qu'une table sur toutes.
     */
    onUpgrade: (Migrator m, int from, int to) async {
      for (final TableInfo<Table, Object?> table in allTables) {
        if (table.actualTableName == pendingOps.actualTableName) {
          continue;
        }
        await m.deleteTable(table.actualTableName);
      }

      await m.createAll();
    },
    beforeOpen: (OpeningDetails details) async {
      // Les tables ajoutees par un parcours apparaissent ici, sans bump de
      // version : `createAll` emet des `CREATE TABLE IF NOT EXISTS`, donc
      // l'appel est sans effet sur ce qui existe deja.
      await createMigrator().createAll();
      // Les cles etrangeres ne sont pas actives par defaut dans SQLite. Sans
      // cette ligne, une contrainte declaree dans un `.drift` de parcours
      // serait ecrite mais jamais appliquee — le pire des deux mondes.
      await customStatement('PRAGMA foreign_keys = ON');
    },
  );

  /// Efface **tout** : cache de lecture et file d'écriture.
  ///
  /// POURQUOI la purge est totale et non sélective : sur un téléphone partagé —
  /// le cas ordinaire au Bénin (arbitrage n° 59) — « un cache de lecture oublié
  /// est une fuite de données » (aide-mémoire du contrat, §12).
  ///
  /// POURQUOI on vide les tables au lieu de supprimer le fichier : la base reste
  /// ouverte pendant la déconnexion, et un fichier supprimé sous une connexion
  /// active laisse un descripteur pendant.
  Future<void> purgeAll() async {
    await transaction(() async {
      for (final TableInfo<Table, Object?> table in allTables) {
        await customStatement('DELETE FROM "${table.actualTableName}"');
      }
    });
  }

  static QueryExecutor _open() => driftDatabase(name: 'verifall');
}

/// La base, injectée par Riverpod (§1.5 : « un provider par dépendance
/// externe »).
final Provider<AppDatabase> appDatabaseProvider = Provider<AppDatabase>((
  Ref ref,
) {
  final AppDatabase database = AppDatabase();
  ref.onDispose(database.close);
  return database;
});

/// Le pont entre `core/` et Drift, relié dans `main.dart`.
///
/// POURQUOI ce détour : `core/` n'a pas à connaître Drift (§1.2, les deux règles
/// de dépendance), et la purge doit pouvoir être vérifiée en test sans démarrer
/// une base.
/// # Ce que la purge couvre, et ce qu'elle oubliait
///
/// Elle vidait la base — cache de lecture et file d'écriture — et s'arrêtait là.
/// Or une soumission mise en file **copie le fichier** dans le stockage privé de
/// l'application, et ces copies survivaient à la déconnexion : une photo ou un
/// enregistrement d'un compte restait lisible par le suivant, sur le téléphone
/// partagé que le socle cite lui-même comme cas ordinaire. La purge se disait
/// « totale et non sélective » ; elle ne l'était pas. Arbitrage n° 140.
class DriftLocalDataPurger implements LocalDataPurger {
  const DriftLocalDataPurger(this._database, {this.dossierDeSupport});

  final AppDatabase _database;

  /// Injecté pour le test : le vrai appel dépend d'une extension de plateforme.
  final Future<Directory> Function()? dossierDeSupport;

  @override
  Future<void> purgeAll() async {
    await _database.purgeAll();
    await _effacerLesMediasCopies();
  }

  Future<void> _effacerLesMediasCopies() async {
    final Directory support;
    try {
      support = await (dossierDeSupport ?? getApplicationSupportDirectory)();
    } on MissingPluginException {
      // Hors appareil — un test sur la machine hote — il n'existe pas de dossier
      // de support, donc rien a effacer. Sur un telephone, cet appel ne peut pas
      // echouer ainsi : une vraie erreur d'effacement, elle, remonte, et
      // `AuthController.signOut` la journalise comme la fuite potentielle
      // qu'elle est.
      return;
    }
    final Directory medias = Directory(
      p.join(support.path, dossierDesMediasEnFile),
    );
    if (medias.existsSync()) {
      await medias.delete(recursive: true);
    }
  }
}
