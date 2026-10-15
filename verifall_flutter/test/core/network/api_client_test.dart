import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/core/network/api_endpoints.dart';
import 'package:verifall_app/core/network/interceptors/headers_interceptor.dart';
import 'package:verifall_app/core/network/token_store.dart';
import 'package:verifall_app/data/models/api_envelope.dart';
import 'package:verifall_app/data/models/api_error_code.dart';
import 'package:verifall_app/data/models/auth_token.dart';

import '../../support/fakes.dart';

/// Un adaptateur qui répond ce qu'on lui dit, et garde ce qu'on lui a demandé.
///
/// POURQUOI un adaptateur plutôt qu'un serveur local : on veut vérifier les
/// **en-têtes envoyés** et le **comportement sur chaque code**, pas la pile
/// réseau du système.
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter(this.respond);

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

ResponseBody _json(
  Object body, {
  int status = 200,
  Map<String, List<String>> headers = const <String, List<String>>{},
}) {
  return ResponseBody.fromString(
    jsonEncode(body),
    status,
    headers: <String, List<String>>{
      Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      ...headers,
    },
  );
}

void main() {
  late InMemoryTokenStore tokens;
  late RecordingLogSink sink;
  late AppLogger logger;
  late int unauthenticatedCalls;
  late int obsoleteCalls;
  late int inactiveCalls;
  late int deletionCalls;

  setUp(() {
    tokens = InMemoryTokenStore();
    sink = RecordingLogSink();
    logger = AppLogger(config: testConfig(), sink: sink);
    unauthenticatedCalls = 0;
    obsoleteCalls = 0;
    inactiveCalls = 0;
    deletionCalls = 0;
  });

  ApiClient build(_ScriptedAdapter adapter) => ApiClient.create(
    baseUrl: 'https://exemple.invalid',
    tokenStore: tokens,
    logger: logger,
    clientVersion: '1.2.0',
    onUnauthenticated: () async => unauthenticatedCalls++,
    onClientObsolete: () => obsoleteCalls++,
    onAccountInactive: () => inactiveCalls++,
    onAccountBeingDeleted: () => deletionCalls++,
    adapter: adapter,
  );

  group('En-tetes', () {
    test(
      'la version majeure vient du contrat, pas de la configuration',
      () async {
        final _ScriptedAdapter adapter = _ScriptedAdapter(
          (_) => _json(<String, Object?>{'data': null}),
        );
        await build(adapter).get(ApiEndpoints.me);

        expect(
          adapter.received.single.uri.path,
          '${ApiEndpoints.versionPrefix}${ApiEndpoints.me}',
        );
      },
    );

    test('X-Client-Version part systematiquement', () async {
      // Facultatif au contrat, envoye quand meme : c'est ce qui rend le plancher
      // de version utilisable le jour ou il faut couper une version dangereuse.
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{'data': null}),
      );
      await build(adapter).get(ApiEndpoints.me);

      expect(
        adapter.received.single.headers[HeadersInterceptor.clientVersionHeader],
        '1.2.0',
      );
    });

    test('le jeton est pose quand il existe, retire quand il manque', () async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{'data': null}),
      );
      final ApiClient client = build(adapter);

      await client.get(ApiEndpoints.tools);
      expect(adapter.received.last.headers.containsKey('Authorization'), false);

      await tokens.write('17|abc');
      await client.get(ApiEndpoints.tools);
      expect(adapter.received.last.headers['Authorization'], 'Bearer 17|abc');
    });

    test('un identifiant de correlation accompagne chaque requete', () async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{'data': null}),
      );
      await build(adapter).get(ApiEndpoints.me);

      expect(
        adapter.received.single.headers[HeadersInterceptor.correlationHeader],
        isA<String>().having((String v) => v.isNotEmpty, 'non vide', true),
      );
    });
  });

  group('Idempotency-Key', () {
    test('la cle est posee telle quelle sur une ecriture', () async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) =>
            _json(<String, Object?>{'data': <String, Object?>{}}, status: 201),
      );
      await build(adapter).post(
        ApiEndpoints.verifications,
        body: <String, Object?>{'type': 'texte'},
        idempotencyKey: 'acte-unique-1234',
      );

      expect(
        adapter.received.single.headers[HeadersInterceptor.idempotencyHeader],
        'acte-unique-1234',
      );
    });

    test('la cle est bornee a 128 caractères', () async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{'data': null}),
      );
      await build(
        adapter,
      ).post(ApiEndpoints.verifications, idempotencyKey: 'x' * 200);

      final Object? sent =
          adapter.received.single.headers[HeadersInterceptor.idempotencyHeader];
      expect((sent! as String).length, HeadersInterceptor.idempotencyMaxLength);
    });

    test('Idempotent-Replay: true est un SUCCES, pas une erreur', () async {
      // « C'est la confirmation que l'acte a abouti la premiere fois. L'ecran
      // affiche le resultat normalement. » (05-HORS-LIGNE §5.6)
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(
          <String, Object?>{
            'data': <String, Object?>{'uuid': 'abc'},
          },
          headers: <String, List<String>>{
            'idempotent-replay': <String>['true'],
          },
        ),
      );

      final ApiEnvelope envelope = await build(
        adapter,
      ).post(ApiEndpoints.verifications, idempotencyKey: 'acte-1');

      expect(envelope.hasError, false);
      expect(envelope.dataObject['uuid'], 'abc');
    });
  });

  group('Enveloppe de réponse', () {
    test('{ "data": null } n est PAS une erreur', () async {
      // `GET /me/courses/resume` repond exactement cela quand il n'y a rien a
      // reprendre. Un client qui traiterait null comme un echec afficherait une
      // erreur sur un compte neuf.
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{'data': null}),
      );

      final ApiEnvelope envelope = await build(
        adapter,
      ).get(ApiEndpoints.coursesResume);

      expect(envelope.hasError, false);
      expect(envelope.hasDataKey, true);
      expect(envelope.data, isNull);
    });

    test('un champ inconnu est ignore, jamais une exception', () async {
      // « Le client doit ignorer les champs qu'il ne connait pas » (§6.2) :
      // c'est ce qui rend toute evolution de l'API possible.
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{
          'data': <String, Object?>{
            'token': 'tok',
            'type': 'Bearer',
            'expire_le': '2026-09-24T10:12:00+00:00',
            'utilisateur': <String, Object?>{
              'uuid': 'u-1',
              'prenom': 'Rita',
              'email': 'rita@exemple.invalid',
              'unites': 12,
              'champ_du_futur': <String, Object?>{'imbrique': true},
            },
            'autre_nouveaute': 42,
          },
          'meta': <String, Object?>{'inconnu': true},
        }),
      );

      final ApiEnvelope envelope = await build(
        adapter,
      ).post(ApiEndpoints.login);
      final AuthToken token = AuthToken.fromJson(envelope.dataObject);

      expect(token.token, 'tok');
      expect(token.utilisateur.prenom, 'Rita');
      expect(token.utilisateur.unites, 12);
    });

    test('absent et present-mais-nul se distinguent', () async {
      // Le contrat §8 : un champ PRESENT ET NUL signifie « connu, mais vide » ;
      // un champ ABSENT signifie « non applicable dans ce contexte ».
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{
          'data': <String, Object?>{'rapport': null},
        }),
      );

      final ApiEnvelope envelope = await build(
        adapter,
      ).get(ApiEndpoints.verification('u'));

      expect(envelope.dataObject.containsKey('rapport'), true);
      expect(envelope.dataObject['rapport'], isNull);
      expect(envelope.dataObject.containsKey('verdict'), false);
    });
  });

  group('Pagination par curseur', () {
    test('le curseur est renvoye tel quel et `page` n existe pas', () async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{
          'data': <Object?>[],
          'meta': <String, Object?>{
            'page': <String, Object?>{
              'per_page': 20,
              'next_cursor': 'eyJpZCI6MTQyfQ',
              'prev_cursor': null,
              'has_more': true,
            },
          },
        }),
      );

      final ApiEnvelope envelope = await build(
        adapter,
      ).getPage(ApiEndpoints.myVerifications, cursor: 'eyJpZCI6MTAwfQ');

      final Map<String, dynamic> query =
          adapter.received.single.queryParameters;
      expect(query['cursor'], 'eyJpZCI6MTAwfQ');
      expect(query.containsKey('page'), false);
      expect(envelope.page!.nextCursor, 'eyJpZCI6MTQyfQ');
      expect(envelope.page!.isLastPage, false);
    });

    test('limit est borne au plafond du contrat', () async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{'data': <Object?>[]}),
      );

      await build(adapter).getPage(ApiEndpoints.forumThreads, limit: 200);

      expect(
        adapter.received.single.queryParameters['limit'],
        PageMeta.maxLimit,
      );
    });

    test('fin de liste : next_cursor nul ET has_more faux', () {
      const PageMeta page = PageMeta(perPage: 20, hasMore: false);
      expect(page.isLastPage, true);
      expect(page.nextCursor, isNull);
    });
  });

  group('Traduction des erreurs', () {
    Future<Failure> failureFor(
      String code,
      int status, {
      Map<String, Object?> extra = const <String, Object?>{},
      Map<String, List<String>> headers = const <String, List<String>>{},
    }) async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(
          <String, Object?>{
            'error': <String, Object?>{
              'code': code,
              'message': 'Message du serveur.',
              ...extra,
            },
            if (extra.containsKey('meta')) 'meta': extra['meta'],
          },
          status: status,
          headers: headers,
        ),
      );
      try {
        await build(adapter).get(ApiEndpoints.me);
        fail('un échec etait attendu');
      } on Failure catch (failure) {
        return failure;
      }
    }

    test('les deux 403 de COMPTE sont traites GLOBALEMENT (n° 136)', () async {
      // Ils decrivent l'etat du compte, pas celui de la requete : « toute route
      // repond `compte_en_suppression`, sauf `GET /me` et `POST /auth/logout` ».
      // Tant qu'ils n'etaient pas ici, l'information n'entrait dans
      // l'application que par `GET /me`, appele par le seul parcours du compte.
      await failureFor('compte_inactif', 403);
      expect(inactiveCalls, 1);
      expect(deletionCalls, 0);

      await failureFor('compte_en_suppression', 403);
      expect(deletionCalls, 1);
    });

    test('le TROISIEME 403 reste a l ecran (n° 136)', () async {
      // `droit_manquant` est un refus qui appartient a l'ecran : lui seul sait
      // dire ce que la personne peut faire a la place.
      await failureFor('droit_manquant', 403);
      expect(inactiveCalls, 0);
      expect(deletionCalls, 0);
      expect(unauthenticatedCalls, 0);
    });

    test('un 403 SANS code metier ne ferme rien (n° 136)', () async {
      // Un 403 pose par un mandataire d'infrastructure ne doit envoyer personne
      // sur un ecran de suppression de compte.
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{}, status: 403),
      );
      try {
        await build(adapter).get(ApiEndpoints.me);
      } on Failure {
        // L'echec importe peu ici : ce qui compte est qu'aucun rappel global
        // n'ait ete declenche.
      }
      expect(inactiveCalls, 0);
      expect(deletionCalls, 0);
    });

    test('chaque code produit sa catégorie', () async {
      expect(
        await failureFor('non_authentifie', 401),
        isA<UnauthenticatedFailure>(),
      );
      expect(
        await failureFor('solde_insuffisant', 402),
        isA<InsufficientBalanceFailure>(),
      );
      expect(
        await failureFor('compte_inactif', 403),
        isA<InactiveAccountFailure>(),
      );
      expect(
        await failureFor('compte_en_suppression', 403),
        isA<AccountBeingDeletedFailure>(),
      );
      expect(await failureFor('droit_manquant', 403), isA<ForbiddenFailure>());
      expect(await failureFor('introuvable', 404), isA<NotFoundFailure>());
      expect(await failureFor('methode_refusee', 405), isA<ClientBugFailure>());
      expect(await failureFor('validation', 422), isA<ValidationFailure>());
      expect(
        await failureFor('action_impossible', 422),
        isA<ImpossibleActionFailure>(),
      );
      expect(await failureFor('conflit', 409), isA<ConflictFailure>());
      expect(
        await failureFor('client_obsolete', 426),
        isA<ObsoleteClientFailure>(),
      );
      expect(await failureFor('debit_depasse', 429), isA<RateLimitedFailure>());
      expect(await failureFor('erreur_serveur', 500), isA<ServerFailure>());
    });

    test('un code inconnu ne fait pas tomber le client', () async {
      // « Ajouter une valeur au catalogue des codes d'erreur » est une evolution
      // COMPATIBLE (§6.2). Un client qui planterait figerait l'API pour toujours.
      final Failure failure = await failureFor('code_du_futur', 418);
      expect(failure, isA<UnexpectedFailure>());
      expect(failure.serverMessage, 'Message du serveur.');
    });

    test('le message du serveur est conserve tel quel', () async {
      final Failure failure = await failureFor('droit_manquant', 403);
      expect(failure.serverMessage, 'Message du serveur.');
      expect(failure.hasServerMessage, true);
    });

    test('détails n accompagne que la validation', () async {
      final Failure failure = await failureFor(
        'validation',
        422,
        extra: <String, Object?>{
          'details': <String, Object?>{
            'email': <String>['L adresse est invalide.'],
          },
        },
      );
      expect(
        (failure as ValidationFailure).firstMessageFor('email'),
        'L adresse est invalide.',
      );
    });

    test('429 porte son delai', () async {
      final Failure failure = await failureFor(
        'debit_depasse',
        429,
        extra: <String, Object?>{
          'meta': <String, Object?>{'retry_after': 42},
        },
      );
      expect(
        (failure as RateLimitedFailure).retryAfter,
        const Duration(seconds: 42),
      );
    });

    test('426 porte le plancher de version', () async {
      final Failure failure = await failureFor(
        'client_obsolete',
        426,
        headers: <String, List<String>>{
          'x-minimum-client-version': <String>['2.0.0'],
        },
      );
      expect((failure as ObsoleteClientFailure).minimumVersion, '2.0.0');
    });
  });

  group('Session', () {
    test('401 declenche la déconnexion, et AUCUN renouvellement', () async {
      // « Il n'y a pas de jeton de rafraichissement. Ne pas boucler sur une
      // tentative de renouvellement — il n'y a rien a renouveler. » (§1.4)
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{
          'error': <String, Object?>{
            'code': 'non_authentifie',
            'message': 'Session expiree.',
          },
        }, status: 401),
      );

      await expectLater(
        build(adapter).get(ApiEndpoints.me),
        throwsA(isA<UnauthenticatedFailure>()),
      );

      expect(unauthenticatedCalls, 1);
      // Une seule requete : pas de rejeu, pas de tentative de renouvellement.
      expect(adapter.received.length, 1);
    });

    test('426 leve l ecran bloquant, globalement', () async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{
          'error': <String, Object?>{
            'code': 'client_obsolete',
            'message': 'Mettez a jour.',
          },
        }, status: 426),
      );

      await expectLater(
        build(adapter).get(ApiEndpoints.me),
        throwsA(isA<ObsoleteClientFailure>()),
      );
      expect(obsoleteCalls, 1);
    });
  });

  group('Journalisation', () {
    test('ni le jeton ni le corps ne sortent dans le journal', () async {
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{
          'data': <String, Object?>{'token': '17|secret-absolu'},
        }),
      );
      await tokens.write('17|secret-absolu');

      await build(adapter).post(
        ApiEndpoints.login,
        body: <String, Object?>{
          'email': 'rita@exemple.invalid',
          'mot_de_passe': 'un-mot-de-passe',
        },
      );

      final String journal = sink.entries
          .map((LogEntry e) => e.toString())
          .join('\n');
      expect(journal, isNot(contains('secret-absolu')));
      expect(journal, isNot(contains('un-mot-de-passe')));
      expect(journal, isNot(contains('rita@exemple.invalid')));
      // Ce qui DOIT y etre : methode, chemin, code, duree.
      expect(journal, contains('POST'));
      expect(journal, contains('200'));
    });

    test('le chemin journalise ne porte ni slug ni uuid', () async {
      // Un slug de sujet de forum est une phrase ecrite par quelqu'un.
      final _ScriptedAdapter adapter = _ScriptedAdapter(
        (_) => _json(<String, Object?>{'data': null}),
      );

      await build(
        adapter,
      ).get(ApiEndpoints.forumThread('cette-video-montre-t-elle-le-ministre'));

      final String journal = sink.entries
          .map((LogEntry e) => e.toString())
          .join('\n');
      expect(journal, isNot(contains('cette-video-montre')));
      expect(journal, contains('/forum/threads/{}'));
    });
  });

  group('Codes du contrat', () {
    test('les treize codes sont couverts, plus le cas inconnu', () {
      // Le catalogue est FERME (§2.2). Ce test verrouille son cardinal : un
      // quatorzieme code ajoute cote serveur doit passer par une relecture.
      expect(ApiErrorCode.values.length, 14);
      expect(ApiErrorCode.parse('code_jamais_vu'), ApiErrorCode.inconnu);
      expect(ApiErrorCode.parse('solde_insuffisant').httpStatus, 402);
      expect(ApiErrorCode.parse('solde_insuffisant').suggestsTopUp, true);
    });
  });
}
