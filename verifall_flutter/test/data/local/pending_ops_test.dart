import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/core/network/api_endpoints.dart';
import 'package:verifall_app/core/network/token_store.dart';
import 'package:verifall_app/data/datasources/local/pending_ops.dart';
import 'package:verifall_app/data/datasources/local/tables/pending_ops_table.dart';

import '../../support/fakes.dart';

/// Les tests de la file d'écriture.
///
/// C'est « le composant le plus difficile à reproduire à la main » (06-TESTS
/// §6.3), et celui dont un défaut fait disparaître le travail de quelqu'un — ou
/// le débite deux fois.
class _Adapter implements HttpClientAdapter {
  _Adapter(this.respond);

  final ResponseBody Function(RequestOptions options) respond;
  final List<RequestOptions> received = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    received.add(options);
    return respond(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody _json(Object body, {int status = 200}) => ResponseBody.fromString(
  jsonEncode(body),
  status,
  headers: <String, List<String>>{
    Headers.contentTypeHeader: <String>[Headers.jsonContentType],
  },
);

ResponseBody _ok() => _json(<String, Object?>{'data': null});

ResponseBody _error(String code, int status) => _json(<String, Object?>{
  'error': <String, Object?>{'code': code, 'message': 'Refus du serveur.'},
}, status: status);

void main() {
  late InMemoryPendingOpsStore store;
  late AppLogger logger;

  setUp(() {
    store = InMemoryPendingOpsStore();
    logger = AppLogger(config: testConfig(), sink: RecordingLogSink());
  });

  ApiClient clientWith(_Adapter adapter) => ApiClient.create(
    baseUrl: 'https://exemple.invalid',
    tokenStore: InMemoryTokenStore(token: 'jeton'),
    logger: logger,
    clientVersion: '1.0.0',
    onUnauthenticated: () async {},
    onClientObsolete: () {},
    onAccountInactive: () {},
    onAccountBeingDeleted: () {},
    adapter: adapter,
  );

  PendingOpsQueue queueWith(
    _Adapter adapter, {
    DateTime Function()? clock,
    int maxAttempts = 5,
  }) => PendingOpsQueue(
    store: store,
    client: clientWith(adapter),
    logger: logger,
    maxAttempts: maxAttempts,
    clock: clock,
  );

  group('Mise en file', () {
    test('la cle est generee a l acte, une fois', () async {
      // « Le client genere un UUID PAR ACTE, pas par tentative. Une cle
      // regeneree a l'envoi ne protege de rien. » (§7.3)
      final PendingOpsQueue queue = queueWith(_Adapter((_) => _ok()));

      final String uuid = await queue.enqueue(
        kind: PendingOpKind.soumettreVerification,
        endpoint: ApiEndpoints.verifications,
        payload: <String, Object?>{'type': 'texte'},
      );

      final PendingOp op = (await store.all()).single;
      expect(op.clientUuid, uuid);
      expect(op.idempotencyKey, uuid);
      expect(op.attempts, 0);
      expect(op.status, PendingOpStatus.enAttente);
    });

    test('seuls les deux actes qui coutent portent une cle d idempotence', () {
      // Le contrat n'exige la cle que sur `POST /verifications` et
      // `POST /me/quiz/{uuid}/attempt` (§7.3). Les autres sont proteges en base
      // ou idempotents par service.
      final Set<PendingOpKind> withKey = PendingOpKind.values
          .where((PendingOpKind k) => k.requiresIdempotencyKey)
          .toSet();
      expect(withKey, <PendingOpKind>{
        PendingOpKind.soumettreVerification,
        PendingOpKind.rendreCopieQuiz,
      });
    });
  });

  group('Rejeu', () {
    test('les actes partent dans l ordre de creation', () async {
      // « Une reponse publiee avant le sujet qui la porte n'a pas de sens. »
      final _Adapter adapter = _Adapter((_) => _ok());
      final PendingOpsQueue queue = queueWith(adapter);

      await store.enqueue(
        PendingOp(
          clientUuid: 'b',
          kind: PendingOpKind.voterSujet,
          endpoint: '/forum/threads/deux/vote',
          payload: const <String, Object?>{},
          createdAt: DateTime.utc(2026, 8, 25, 10, 5),
        ),
      );
      await store.enqueue(
        PendingOp(
          clientUuid: 'a',
          kind: PendingOpKind.terminerLecon,
          endpoint: '/me/lessons/un/complete',
          payload: const <String, Object?>{},
          createdAt: DateTime.utc(2026, 8, 25, 10, 0),
        ),
      );

      await queue.replayAll();

      expect(
        adapter.received.map((RequestOptions o) => o.path).toList(),
        <String>['/me/lessons/un/complete', '/forum/threads/deux/vote'],
      );
      expect(store.ops, isEmpty);
    });

    test('un succes retire l operation de la file', () async {
      final PendingOpsQueue queue = queueWith(_Adapter((_) => _ok()));
      await queue.enqueue(
        kind: PendingOpKind.terminerLecon,
        endpoint: '/me/lessons/x/complete',
      );

      await queue.replayAll();
      expect(store.ops, isEmpty);
    });

    test('la meme cle repart identique a chaque reprise', () async {
      // C'est le test qui protege l'argent de quelqu'un : deux tentatives du
      // meme acte doivent porter la MEME cle, sinon le serveur facture deux fois.
      int calls = 0;
      final _Adapter adapter = _Adapter((_) {
        calls++;
        return calls == 1 ? _error('erreur_serveur', 500) : _ok();
      });
      final PendingOpsQueue queue = queueWith(adapter);

      final String uuid = await queue.enqueue(
        kind: PendingOpKind.soumettreVerification,
        endpoint: ApiEndpoints.verifications,
      );

      await queue.replayAll();
      // La premiere tentative a echoue : l'operation est reprogrammee.
      expect(store.ops.single.attempts, 1);
      expect(store.ops.single.status, PendingOpStatus.enAttente);

      // On force l'echeance et on rejoue.
      await store.update(store.ops.single.copyWith(clearNextAttemptAt: true));
      await queue.replayAll();

      final List<String?> keys = adapter.received
          .map((RequestOptions o) => o.headers['Idempotency-Key'] as String?)
          .toList();
      expect(keys, <String?>[uuid, uuid]);
      expect(store.ops, isEmpty);
    });
  });

  group('Échecs', () {
    test('404 retire l operation : la ressource a disparu', () async {
      // Offre archivee, sujet supprime. « Ne pas la rejouer indefiniment. »
      final PendingOpsQueue queue = queueWith(
        _Adapter((_) => _error('introuvable', 404)),
      );

      await queue.enqueue(
        kind: PendingOpKind.signaler,
        endpoint: '/forum/threads/disparu/report',
      );
      await queue.replayAll();

      expect(store.ops, isEmpty);
    });

    test('429 suspend le cycle et respecte le delai', () async {
      // « Arret net sur 429 en respectant retry_after. Reessayer ne fait
      // qu'aggraver. »
      final _Adapter adapter = _Adapter(
        (_) => _json(<String, Object?>{
          'error': <String, Object?>{
            'code': 'debit_depasse',
            'message': 'Plafond atteint.',
          },
          'meta': <String, Object?>{'retry_after': 120},
        }, status: 429),
      );
      final DateTime now = DateTime.utc(2026, 8, 25, 12);
      final PendingOpsQueue queue = queueWith(adapter, clock: () => now);

      await store.enqueue(
        PendingOp(
          clientUuid: 'a',
          kind: PendingOpKind.soumettreVerification,
          endpoint: ApiEndpoints.verifications,
          payload: const <String, Object?>{},
          createdAt: now.subtract(const Duration(minutes: 2)),
        ),
      );
      await store.enqueue(
        PendingOp(
          clientUuid: 'b',
          kind: PendingOpKind.voterSujet,
          endpoint: '/forum/threads/x/vote',
          payload: const <String, Object?>{},
          createdAt: now.subtract(const Duration(minutes: 1)),
        ),
      );

      await queue.replayAll();

      // La seconde operation n'a PAS ete tentee : le cycle s'arrete net.
      expect(adapter.received.length, 1);
      final PendingOp first = (await store.find('a'))!;
      expect(first.status, PendingOpStatus.enAttente);
      expect(first.nextAttemptAt, now.add(const Duration(seconds: 120)));
      // Un 429 ne consomme pas de tentative : ce n'est pas l'acte qui est en
      // cause, c'est le rythme.
      expect(first.attempts, 0);
    });

    test('402 au rejeu est definitif, et le dit', () async {
      // « Le solde a change entre la mise en file et l'envoi. L'acte echoue, et
      // l'ecran le dit avec un renvoi vers la recharge. »
      final PendingOpsQueue queue = queueWith(
        _Adapter((_) => _error('solde_insuffisant', 402)),
      );

      await queue.enqueue(
        kind: PendingOpKind.sInscrireAuCours,
        endpoint: '/courses/un-cours/enroll',
      );
      await queue.replayAll();

      final PendingOp op = store.ops.single;
      expect(op.status, PendingOpStatus.enPanne);
      expect(op.lastError, 'Refus du serveur.');
      // Elle reste dans la file : « une file qui abandonne en silence fait
      // disparaitre le travail de quelqu'un ».
      expect(store.ops.length, 1);
    });

    test(
      'après epuisement des tentatives, l operation passe en panne',
      () async {
        final PendingOpsQueue queue = queueWith(
          _Adapter((_) => _error('erreur_serveur', 500)),
          maxAttempts: 2,
        );

        await queue.enqueue(
          kind: PendingOpKind.terminerLecon,
          endpoint: '/me/lessons/x/complete',
        );

        // Premiere tentative : reprogrammee.
        await queue.replayAll();
        expect(store.ops.single.status, PendingOpStatus.enAttente);
        await store.update(store.ops.single.copyWith(clearNextAttemptAt: true));

        // Seconde : plafond atteint, panne visible.
        await queue.replayAll();
        expect(store.ops.single.status, PendingOpStatus.enPanne);
        expect(store.ops.single.attempts, 2);
      },
    );

    test('une operation en panne se reprend explicitement', () async {
      final PendingOpsQueue queue = queueWith(_Adapter((_) => _ok()));
      await store.enqueue(
        PendingOp(
          clientUuid: 'a',
          kind: PendingOpKind.voterSujet,
          endpoint: '/forum/threads/x/vote',
          payload: const <String, Object?>{},
          createdAt: DateTime.utc(2026, 8, 25),
          attempts: 5,
          status: PendingOpStatus.enPanne,
          lastError: 'Refus du serveur.',
        ),
      );

      await queue.retry('a');

      final PendingOp op = (await store.find('a'))!;
      expect(op.status, PendingOpStatus.enAttente);
      expect(op.attempts, 0);
      expect(op.lastError, isNull);
    });

    test('l abandon est un geste de l utilisateur', () async {
      final PendingOpsQueue queue = queueWith(_Adapter((_) => _ok()));
      final String uuid = await queue.enqueue(
        kind: PendingOpKind.voterSujet,
        endpoint: '/forum/threads/x/vote',
      );
      await queue.cancel(uuid);
      expect(store.ops, isEmpty);
    });
  });

  group('Recul exponentiel', () {
    test('le delai croit, et il porte une part d alea', () {
      final PendingOpsQueue queue = queueWith(_Adapter((_) => _ok()));

      // « Sans l'alea, tous les clients reviennent a la meme seconde apres une
      // panne et achevent le serveur. » On verifie donc un PLAFOND croissant,
      // pas une valeur fixe.
      for (int attempt = 1; attempt <= 4; attempt++) {
        final Duration delay = queue.backoffFor(attempt);
        expect(delay.inSeconds, greaterThanOrEqualTo(1));
        expect(delay.inSeconds, lessThanOrEqualTo(30 * (1 << (attempt - 1))));
      }
    });
  });

  group('Ce qui n a PAS le droit d être en file', () {
    test('ni recharge, ni suppression de compte, ni notifications', () {
      // « Un paiement ne se met pas en file, jamais » ; une suppression rejouee
      // trois jours plus tard est irrattrapable ; les endpoints de notification
      // n'existent pas (contrat §11).
      final Set<String> actes = PendingOpKind.values
          .map((PendingOpKind k) => k.wireValue)
          .toSet();
      expect(actes.any((String a) => a.contains('wallet')), false);
      expect(actes.any((String a) => a.contains('topup')), false);
      expect(actes.any((String a) => a.contains('suppression')), false);
      expect(actes.any((String a) => a.contains('notification')), false);
    });
  });
}
