import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:drift/drift.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/errors/failure.dart';
import '../../../core/logging/app_logger.dart';
import '../../../core/logging/logging_providers.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/network_providers.dart';
import 'database.dart';
import 'tables/pending_ops_table.dart';

/// Les actes que le contrat autorise à mettre en file (§7.2).
///
/// POURQUOI la liste est fermée : « ne rien y ajouter sans vérifier comment
/// l'acte survit à un rejeu » (05-HORS-LIGNE §5.7). Chaque valeur porte la
/// raison pour laquelle un rejeu ne produit pas de doublon.
///
/// Trois actes que le simulateur suggérait n'y sont **pas**, et c'est délibéré :
/// le marquage des notifications (les endpoints n'existent pas), la recharge
/// d'unités (« un paiement ne se met pas en file, jamais ») et la suppression de
/// compte (« irrattrapable si rejouée trois jours plus tard »).
enum PendingOpKind {
  /// `POST /me/lessons/{uuid}/complete` — contrainte d'unicité en base.
  terminerLecon('lecon.terminer', requiresIdempotencyKey: false),

  /// `POST /courses/{slug}/enroll` — idempotent par service. **Débite des
  /// unités** : c'est l'acte où un doublon coûte de l'argent à quelqu'un.
  sInscrireAuCours('cours.inscription', requiresIdempotencyKey: false),

  /// `POST /live-classes/{uuid}/remind` — contrainte d'unicité en base.
  rappelSeance('seance.rappel', requiresIdempotencyKey: false),

  /// `POST /forum/threads/{slug}/vote` — contrainte d'unicité en base.
  voterSujet('forum.vote', requiresIdempotencyKey: false),

  /// `POST /forum/threads/{slug}/report`, `/hub/opportunities/{slug}/report` —
  /// contrainte d'unicité en base.
  signaler('signalement', requiresIdempotencyKey: false),

  /// `POST /verifications` — **`Idempotency-Key` obligatoire**.
  ///
  /// « Une réponse perdue est indiscernable d'une requête perdue. Le téléphone
  /// qui ne reçoit rien ne sait pas si la vérification a été lancée — et s'il
  /// rejoue, la personne est débitée deux fois pour une seule question. »
  soumettreVerification('verification.soumettre', requiresIdempotencyKey: true),

  /// `POST /me/quiz/{uuid}/attempt` — **`Idempotency-Key` obligatoire**.
  rendreCopieQuiz('quiz.copie', requiresIdempotencyKey: true),

  /// `PATCH /me` — dernière écriture gagnante côté serveur (§7.4).
  modifierProfil('profil.modifier', requiresIdempotencyKey: false),

  /// `PATCH /me/settings` — attend une **valeur voulue**, jamais une bascule.
  ///
  /// POURQUOI c'est capital pour la file : « une bascule perd sa cible quand la
  /// requête est rejouée après une coupure, et l'utilisateur retrouve le réglage
  /// inverse de ce qu'il a demandé » (§10.2).
  reglerPreference('preference.regler', requiresIdempotencyKey: false);

  const PendingOpKind(this.wireValue, {required this.requiresIdempotencyKey});

  final String wireValue;

  /// Le contrat n'exige la clé que sur **les deux actes qui coûtent** (§7.3) ;
  /// les autres sont protégés en base ou idempotents par service.
  final bool requiresIdempotencyKey;

  static PendingOpKind? parse(String? raw) {
    for (final PendingOpKind kind in PendingOpKind.values) {
      if (kind.wireValue == raw) {
        return kind;
      }
    }
    return null;
  }
}

/// Une opération en attente.
@immutable
class PendingOp {
  const PendingOp({
    required this.clientUuid,
    required this.kind,
    required this.endpoint,
    required this.payload,
    required this.createdAt,
    this.method = 'POST',
    this.localFilePath,
    this.attempts = 0,
    this.nextAttemptAt,
    this.lastError,
    this.status = PendingOpStatus.enAttente,
  });

  /// L'identifiant client, généré **au moment où l'utilisateur agit**.
  final String clientUuid;

  final PendingOpKind kind;
  final String endpoint;
  final String method;
  final Map<String, Object?> payload;

  /// La copie locale du fichier, pour une soumission de vérification : le
  /// téléversement expire au bout de deux heures côté serveur.
  final String? localFilePath;

  final DateTime createdAt;
  final int attempts;
  final DateTime? nextAttemptAt;
  final String? lastError;
  final PendingOpStatus status;

  /// La clé d'idempotence de cet acte, ou `null` quand le contrat ne l'exige
  /// pas.
  ///
  /// C'est **le même** identifiant que [clientUuid] : une seule valeur, générée
  /// une fois, réutilisée à chaque reprise.
  String? get idempotencyKey => kind.requiresIdempotencyKey ? clientUuid : null;

  bool isDue(DateTime now) {
    if (status != PendingOpStatus.enAttente) {
      return false;
    }
    final DateTime? next = nextAttemptAt;
    return next == null || !next.isAfter(now);
  }

  PendingOp copyWith({
    int? attempts,
    DateTime? nextAttemptAt,
    bool clearNextAttemptAt = false,
    String? lastError,
    bool clearLastError = false,
    PendingOpStatus? status,
  }) {
    return PendingOp(
      clientUuid: clientUuid,
      kind: kind,
      endpoint: endpoint,
      method: method,
      payload: payload,
      localFilePath: localFilePath,
      createdAt: createdAt,
      attempts: attempts ?? this.attempts,
      nextAttemptAt: clearNextAttemptAt
          ? null
          : (nextAttemptAt ?? this.nextAttemptAt),
      lastError: clearLastError ? null : (lastError ?? this.lastError),
      status: status ?? this.status,
    );
  }

  @override
  String toString() => 'PendingOp(${kind.wireValue}, ${status.name})';
}

/// Le magasin de la file.
///
/// POURQUOI une interface : la logique de rejeu — ordre, recul, abandon, retrait
/// d'une opération orpheline — est « le composant le plus difficile à reproduire
/// à la main » (06-TESTS §6.3). L'interface permet de la tester sans base
/// native, donc réellement.
abstract interface class PendingOpsStore {
  /// Ajoute un acte. Le [PendingOp.clientUuid] fait office de clé : ré-ajouter
  /// le même acte n'en crée pas un second.
  Future<void> enqueue(PendingOp op);

  /// Les opérations **dans l'ordre de création**.
  ///
  /// POURQUOI l'ordre est une règle et pas une commodité : « une réponse publiée
  /// avant le sujet qui la porte n'a pas de sens » (§5.7).
  Future<List<PendingOp>> all();

  Future<PendingOp?> find(String clientUuid);

  Future<void> update(PendingOp op);

  Future<void> remove(String clientUuid);

  Future<void> clear();

  /// Le nombre d'opérations non abandonnées, pour le bandeau
  /// « `<n>` action(s) en attente d'envoi ».
  Stream<int> watchPendingCount();
}

/// L'implémentation Drift.
class DriftPendingOpsStore implements PendingOpsStore {
  DriftPendingOpsStore(this._db);

  final AppDatabase _db;

  @override
  Future<void> enqueue(PendingOp op) async {
    await _db.into(_db.pendingOps).insertOnConflictUpdate(_toRow(op));
  }

  @override
  Future<List<PendingOp>> all() async {
    final List<PendingOpRow> rows =
        await (_db.select(_db.pendingOps)
              ..orderBy(<OrderClauseGenerator<$PendingOpsTable>>[
                ($PendingOpsTable t) => OrderingTerm.asc(t.createdAt),
              ]))
            .get();
    return rows.map(_fromRow).nonNulls.toList(growable: false);
  }

  @override
  Future<PendingOp?> find(String clientUuid) async {
    final PendingOpRow? row =
        await (_db.select(_db.pendingOps)
              ..where(($PendingOpsTable t) => t.clientUuid.equals(clientUuid)))
            .getSingleOrNull();
    return row == null ? null : _fromRow(row);
  }

  @override
  Future<void> update(PendingOp op) async {
    await _db.update(_db.pendingOps).replace(_toRow(op));
  }

  @override
  Future<void> remove(String clientUuid) async {
    await (_db.delete(
      _db.pendingOps,
    )..where(($PendingOpsTable t) => t.clientUuid.equals(clientUuid))).go();
  }

  @override
  Future<void> clear() async {
    await _db.delete(_db.pendingOps).go();
  }

  @override
  Stream<int> watchPendingCount() {
    return (_db.select(_db.pendingOps)..where(
          ($PendingOpsTable t) =>
              t.status.equalsValue(PendingOpStatus.enPanne).not(),
        ))
        .watch()
        .map((List<PendingOpRow> rows) => rows.length);
  }

  PendingOpRow _toRow(PendingOp op) => PendingOpRow(
    clientUuid: op.clientUuid,
    op: op.kind.wireValue,
    endpoint: op.endpoint,
    method: op.method,
    payload: jsonEncode(op.payload),
    localFilePath: op.localFilePath,
    createdAt: op.createdAt,
    attempts: op.attempts,
    nextAttemptAt: op.nextAttemptAt,
    lastError: op.lastError,
    status: op.status,
  );

  PendingOp? _fromRow(PendingOpRow row) {
    final PendingOpKind? kind = PendingOpKind.parse(row.op);
    if (kind == null) {
      // Une operation dont l'acte n'existe plus dans cette version du client :
      // on ne la rejoue pas a l'aveugle. Elle est ignoree ici et retiree par le
      // ramassage de la file.
      return null;
    }
    final Object? decoded = jsonDecode(row.payload);
    return PendingOp(
      clientUuid: row.clientUuid,
      kind: kind,
      endpoint: row.endpoint,
      method: row.method,
      payload: decoded is Map
          ? decoded.map(
              (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
            )
          : const <String, Object?>{},
      localFilePath: row.localFilePath,
      createdAt: row.createdAt,
      attempts: row.attempts,
      nextAttemptAt: row.nextAttemptAt,
      lastError: row.lastError,
      status: row.status,
    );
  }
}

/// Ce que le rejeu d'une opération a produit.
enum ReplayOutcome {
  /// Le serveur a accepté. L'opération est retirée.
  reussi,

  /// Échec temporaire : on réessaiera plus tard, avec un recul plus long.
  differe,

  /// Refus définitif : l'opération passe en panne **et le dit**.
  abandonne,

  /// La ressource cible a disparu : l'opération est retirée avec un message,
  /// jamais rejouée indéfiniment.
  orpheline,

  /// Le serveur demande d'attendre : on arrête net le cycle en cours.
  suspendu,
}

/// Le moteur de rejeu.
///
/// # Les règles, et pourquoi elles sont des règles
///
/// - **Dans l'ordre de création, une opération à la fois.** Une réponse publiée
///   avant le sujet qui la porte n'a pas de sens.
/// - **Recul exponentiel** entre les tentatives.
/// - **Arrêt net sur `429`**, en respectant `retry_after` : réessayer ne fait
///   qu'aggraver.
/// - **`402` au rejeu** : le solde a changé entre la mise en file et l'envoi.
///   L'acte échoue, et l'écran le dit avec un renvoi vers la recharge.
/// - **`404`** : la ressource a disparu (offre archivée, sujet supprimé).
///   Retirer l'opération avec un message.
/// - **Après épuisement des tentatives**, l'opération passe en panne **et le
///   dit** : « une file qui abandonne en silence fait disparaître le travail de
///   quelqu'un ».
///
/// Ce que ce moteur ne fait **jamais** : débiter ou créditer une unité. « Le
/// solde est un fait serveur » (§5.9).
class PendingOpsQueue {
  PendingOpsQueue({
    required this.store,
    required this.client,
    required this.logger,
    this.maxAttempts = 5,
    this.baseBackoff = const Duration(seconds: 30),
    DateTime Function()? clock,
    Uuid? uuid,
    Random? random,
  }) : _clock = clock ?? DateTime.now,
       _uuid = uuid ?? const Uuid(),
       _random = random ?? Random();

  /// Les trois dependances sont injectees et publiques : la file est le
  /// composant le plus difficile a reproduire a la main, et ses tests doivent
  /// pouvoir relire ce qu'on lui a donne.
  final PendingOpsStore store;
  final ApiClient client;
  final AppLogger logger;
  final DateTime Function() _clock;
  final Uuid _uuid;
  final Random _random;

  /// Le nombre de tentatives au-delà duquel l'opération passe en panne.
  final int maxAttempts;

  final Duration baseBackoff;

  bool _running = false;

  /// Met un acte en file et rend son identifiant client.
  ///
  /// POURQUOI l'identifiant est généré **ici**, à l'instant de l'acte, et pas au
  /// moment de l'envoi : c'est toute la sécurité du mécanisme. « Une clé
  /// regénérée à l'envoi ne protège de rien » (§5.6).
  Future<String> enqueue({
    required PendingOpKind kind,
    required String endpoint,
    Map<String, Object?> payload = const <String, Object?>{},
    String method = 'POST',
    String? localFilePath,
  }) async {
    final String clientUuid = _uuid.v4();
    await store.enqueue(
      PendingOp(
        clientUuid: clientUuid,
        kind: kind,
        endpoint: endpoint,
        method: method,
        payload: payload,
        localFilePath: localFilePath,
        createdAt: _clock(),
      ),
    );
    logger.info(LogChannel.pendingOps, 'Acte mis en file : ${kind.wireValue}');
    return clientUuid;
  }

  Stream<int> watchPendingCount() => store.watchPendingCount();

  Future<List<PendingOp>> snapshot() => store.all();

  /// Rejoue la file.
  ///
  /// Déclenché par le retour du réseau, par le passage au premier plan, et par
  /// une action manuelle depuis le bandeau (§5.7).
  ///
  /// POURQUOI un verrou : deux cycles concurrents enverraient deux fois le même
  /// acte. La clé d'idempotence protège les deux qui coûtent ; les autres
  /// s'appuient sur une contrainte de base, et un doublon y produirait une erreur
  /// affichée à quelqu'un qui n'a rien fait de mal.
  Future<void> replayAll() async {
    if (_running) {
      return;
    }
    _running = true;
    try {
      final DateTime now = _clock();
      final List<PendingOp> ops = await store.all();
      for (final PendingOp op in ops) {
        if (!op.isDue(now)) {
          continue;
        }
        final ReplayOutcome outcome = await replayOne(op);
        if (outcome == ReplayOutcome.suspendu) {
          // 429 : on respecte le delai annonce et on arrete le cycle. Continuer
          // sur les suivantes ne ferait qu'aggraver le plafond.
          break;
        }
      }
    } finally {
      _running = false;
    }
  }

  /// Rejoue **une** opération, et rend ce qui s'est passé.
  @visibleForTesting
  Future<ReplayOutcome> replayOne(PendingOp op) async {
    await store.update(op.copyWith(status: PendingOpStatus.enCours));
    try {
      await _send(op);
      await store.remove(op.clientUuid);
      logger.info(LogChannel.pendingOps, 'Acte abouti : ${op.kind.wireValue}');
      return ReplayOutcome.reussi;
    } on RateLimitedFailure catch (failure) {
      // Le serveur dit quand ce sera de nouveau possible : on l'ecoute, on ne
      // consomme pas une tentative pour cela.
      await store.update(
        op.copyWith(
          status: PendingOpStatus.enAttente,
          nextAttemptAt: _clock().add(
            failure.retryAfter ?? const Duration(minutes: 1),
          ),
          lastError: failure.serverMessage,
        ),
      );
      return ReplayOutcome.suspendu;
    } on NotFoundFailure catch (failure) {
      // La ressource a disparu : offre archivee, sujet supprime. La rejouer
      // indefiniment ne la fera pas revenir.
      await store.remove(op.clientUuid);
      logger.warning(
        LogChannel.pendingOps,
        'Acte orphelin retire : ${op.kind.wireValue}',
        data: <String, Object?>{'message': failure.serverMessage},
      );
      return ReplayOutcome.orpheline;
    } on Failure catch (failure) {
      return _handleFailure(op, failure);
    }
  }

  Future<ReplayOutcome> _handleFailure(PendingOp op, Failure failure) async {
    final bool definitif = switch (failure) {
      // Le solde a change entre la mise en file et l'envoi : c'est le cas reel,
      // et il se dit a l'utilisateur avec un renvoi vers la recharge. Le rejouer
      // ne le resoudra pas.
      InsufficientBalanceFailure() => true,
      ValidationFailure() => true,
      ImpossibleActionFailure() => true,
      ForbiddenFailure() => true,
      ConflictFailure() => true,
      ClientBugFailure() => true,
      ObsoleteClientFailure() => true,
      // 401 : la session est deja fermee par l'intercepteur, et la base — donc
      // la file — vient d'etre purgee. Rien a reprogrammer.
      UnauthenticatedFailure() => true,
      InactiveAccountFailure() => true,
      AccountBeingDeletedFailure() => true,
      _ => false,
    };

    final int attempts = op.attempts + 1;
    if (definitif || attempts >= maxAttempts) {
      await store.update(
        op.copyWith(
          attempts: attempts,
          status: PendingOpStatus.enPanne,
          lastError: failure.serverMessage,
          clearNextAttemptAt: true,
        ),
      );
      logger.warning(
        LogChannel.pendingOps,
        'Acte en panne : ${op.kind.wireValue} après $attempts tentative(s)',
      );
      return ReplayOutcome.abandonne;
    }

    await store.update(
      op.copyWith(
        attempts: attempts,
        status: PendingOpStatus.enAttente,
        nextAttemptAt: _clock().add(backoffFor(attempts)),
        lastError: failure.serverMessage,
      ),
    );
    return ReplayOutcome.differe;
  }

  /// Le recul exponentiel, avec sa part d'aléa.
  ///
  /// POURQUOI l'aléa ici aussi : au retour du réseau après une coupure de
  /// quartier, tous les téléphones rejouent leur file en même temps.
  Duration backoffFor(int attempts) {
    final int ceilingSeconds =
        baseBackoff.inSeconds * pow(2, attempts - 1).toInt();
    return Duration(seconds: _random.nextInt(ceilingSeconds) + 1);
  }

  /// Reprogramme une opération en panne, depuis le détail du bandeau.
  Future<void> retry(String clientUuid) async {
    final PendingOp? op = await store.find(clientUuid);
    if (op == null) {
      return;
    }
    await store.update(
      op.copyWith(
        attempts: 0,
        status: PendingOpStatus.enAttente,
        clearNextAttemptAt: true,
        clearLastError: true,
      ),
    );
  }

  /// Abandonne explicitement une opération. **C'est un geste de l'utilisateur**,
  /// jamais une décision de la file.
  Future<void> cancel(String clientUuid) => store.remove(clientUuid);

  Future<void> _send(PendingOp op) async {
    switch (op.method.toUpperCase()) {
      case 'PATCH':
        await client.patch(
          op.endpoint,
          body: op.payload,
          idempotencyKey: op.idempotencyKey,
        );
      case 'DELETE':
        await client.delete(op.endpoint, body: op.payload);
      default:
        await client.post(
          op.endpoint,
          body: op.payload,
          idempotencyKey: op.idempotencyKey,
        );
    }
  }
}

final Provider<PendingOpsStore> pendingOpsStoreProvider =
    Provider<PendingOpsStore>(
      (Ref ref) => DriftPendingOpsStore(ref.watch(appDatabaseProvider)),
    );

final Provider<PendingOpsQueue> pendingOpsQueueProvider =
    Provider<PendingOpsQueue>(
      (Ref ref) => PendingOpsQueue(
        store: ref.watch(pendingOpsStoreProvider),
        client: ref.watch(apiClientProvider),
        logger: ref.watch(appLoggerProvider),
      ),
    );

/// Le compteur du bandeau « `<n>` action(s) en attente d'envoi ».
///
/// POURQUOI le bandeau est permanent tant que la file n'est pas vide : « ne
/// jamais faire croire qu'une écriture a abouti » (§5.7).
final StreamProvider<int> pendingOpsCountProvider = StreamProvider<int>(
  (Ref ref) => ref.watch(pendingOpsQueueProvider).watchPendingCount(),
);
