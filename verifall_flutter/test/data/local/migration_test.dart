import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sqlite3/sqlite3.dart';
import 'package:verifall_app/data/datasources/local/database.dart';

/// La montée de version de la base locale.
///
/// # Pourquoi ce fichier existe
///
/// `onUpgrade` appelait `m.createAll()`, qui émet des `CREATE TABLE IF NOT
/// EXISTS` : il crée les tables absentes et **ne touche à aucune table déjà
/// présente**. Une colonne ajoutée à une table existante n'aurait donc jamais
/// été créée chez quelqu'un qui MET À JOUR — la requête suivante aurait cherché
/// une colonne que SQLite n'a pas, et l'application aurait planté à chaque
/// ouverture. Seule une désinstallation l'en aurait sorti.
///
/// Le défaut était invisible : `schemaVersion` valait 1, donc `onUpgrade`
/// n'avait jamais tourné une seule fois. Il se serait déclenché à la première
/// migration APRÈS mise en magasin, c'est-à-dire au moment où le parc installé
/// est le plus large.
///
/// # Ce que ces tests éprouvent
///
/// Deux propriétés, et elles s'opposent — c'est tout l'intérêt :
///
///   1. une table de CACHE dont la forme a changé est refaite à neuf ;
///   2. la FILE D'ÉCRITURE, elle, survit — elle contient le travail que la
///      personne a fait hors ligne et que le serveur n'a pas encore reçu.
///
/// # Comment on simule une ancienne installation
///
/// On ouvre une base SQLite brute, on y écrit un schéma d'AVANT — une table de
/// cache à l'ancienne forme, la file à la forme courante — puis on pose
/// `user_version = 1`. Drift, en ouvrant la même base à la version courante,
/// constate l'écart et déclenche `onUpgrade`. C'est exactement le chemin que
/// suit un téléphone qui reçoit une mise à jour.
///
/// # Pourquoi une sous-classe à la version 2
///
/// `AppDatabase.schemaVersion` vaut 1 et n'a jamais bougé : il n'existe donc
/// aucune version antérieure d'où monter, et `onUpgrade` ne peut pas être
/// déclenché tel quel. **C'est exactement pourquoi le défaut a pu dormir.**
///
/// On simule donc la PREMIÈRE montée de version — celle qui arrivera un jour, et
/// qui aurait planté le parc installé — en surchargeant le seul numéro, la
/// stratégie de migration restant celle du produit.
class _BaseEnVersion2 extends AppDatabase {
  _BaseEnVersion2(super.executeur) : super.withExecutor();

  @override
  int get schemaVersion => 2;
}

void main() {
  late Database brute;

  /// Écrit un schéma d'ancienne version dans la base brute.
  ///
  /// `verification_cache` — une VRAIE table du schéma — y est écrite avec une
  /// colonne `titre` que la version courante ne connaît pas, et sans les
  /// colonnes qu'elle attend. Si la migration se contentait de `CREATE TABLE IF
  /// NOT EXISTS`, la table garderait cette forme périmée.
  ///
  /// Le nom compte : `onUpgrade` ne peut effacer que les tables DÉCLARÉES au
  /// schéma courant. Une table inventée pour le test ne prouverait rien.
  void poserUneAncienneInstallation() {
    brute.execute('''
      CREATE TABLE verification_cache (
        uuid TEXT NOT NULL PRIMARY KEY,
        titre TEXT NOT NULL
      );
    ''');
    brute.execute(
      "INSERT INTO verification_cache (uuid, titre) VALUES ('v-1', 'Ancien');",
    );

    // La file d'écriture, à la forme COURANTE : c'est elle qui doit survivre.
    brute.execute('''
      CREATE TABLE pending_ops (
        client_uuid TEXT NOT NULL PRIMARY KEY,
        op TEXT NOT NULL,
        endpoint TEXT NOT NULL,
        method TEXT NOT NULL DEFAULT 'POST',
        payload TEXT NOT NULL,
        local_file_path TEXT NULL,
        created_at INTEGER NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 0,
        next_attempt_at INTEGER NULL,
        last_error TEXT NULL,
        status TEXT NOT NULL DEFAULT 'enAttente'
      );
    ''');
    brute.execute('''
      INSERT INTO pending_ops
        (client_uuid, op, endpoint, method, payload, created_at, attempts, status)
      VALUES
        ('op-hors-ligne', 'forum.reply', '/forum/threads/x/replies', 'POST',
         '{}', 0, 0, 'enAttente');
    ''');

    // La version d'AVANT. C'est ce chiffre qui déclenche `onUpgrade`.
    brute.execute('PRAGMA user_version = 1;');
  }

  setUp(() {
    brute = sqlite3.openInMemory();
  });

  tearDown(() {
    brute.close();
  });

  test('refait à neuf une table de cache dont la forme a changé', () async {
    poserUneAncienneInstallation();

    final AppDatabase base = _BaseEnVersion2(
      NativeDatabase.opened(brute, closeUnderlyingOnClose: false),
    );
    addTearDown(base.close);

    // Une lecture quelconque force l'ouverture, donc la migration.
    await base.customSelect('SELECT 1').get();

    final List<String> colonnes = brute
        .select("PRAGMA table_info('verification_cache');")
        .map((Row r) => r['name'] as String)
        .toList();

    /*
     | LE test. Avec l'ancien `createAll()`, `titre` serait encore là : la table
     | existait, donc `CREATE TABLE IF NOT EXISTS` ne faisait rien, et la
     | colonne périmée survivait à toutes les mises à jour.
     */
    expect(
      colonnes,
      isNot(contains('titre')),
      reason:
          'La table de cache a gardé sa forme périmée : la migration n’a rien fait.',
    );
    // Et elle a bien repris la forme COURANTE, pas seulement perdu l'ancienne.
    expect(
      colonnes,
      containsAll(<String>['charge_json', 'terminee', 'fetched_at']),
    );
  });

  test('PRÉSERVE la file d’écriture, qui ne se rebâtit pas', () async {
    /*
     | La contrepartie indispensable. Un cache se recharge depuis le serveur ;
     | une opération en attente contient le travail que la personne a fait hors
     | ligne — son message, son vote, sa leçon terminée. L'effacer le ferait
     | disparaître sans que personne ne le sache.
     */
    poserUneAncienneInstallation();

    final AppDatabase base = _BaseEnVersion2(
      NativeDatabase.opened(brute, closeUnderlyingOnClose: false),
    );
    addTearDown(base.close);

    final List<PendingOpRow> restantes = await base
        .select(base.pendingOps)
        .get();

    expect(restantes, hasLength(1));
    expect(restantes.single.clientUuid, 'op-hors-ligne');
    expect(restantes.single.op, 'forum.reply');
  });

  test('une installation NEUVE reçoit toutes les tables', () async {
    // Le chemin `onCreate`, qui ne doit pas avoir été abîmé au passage.
    final AppDatabase base = AppDatabase.withExecutor(
      NativeDatabase.opened(brute, closeUnderlyingOnClose: false),
    );
    addTearDown(base.close);

    await base.customSelect('SELECT 1').get();

    final Set<String> presentes = brute
        .select("SELECT name FROM sqlite_master WHERE type = 'table';")
        .map((Row r) => r['name'] as String)
        .toSet();

    for (final TableInfo<Table, Object?> table in base.allTables) {
      expect(
        presentes,
        contains(table.actualTableName),
        reason:
            'Table absente d’une installation neuve : ${table.actualTableName}',
      );
    }
  });
}
