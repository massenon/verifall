import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/data/datasources/local/pending_ops.dart';
import 'package:verifall_app/data/datasources/local/verify/verification_local_source.dart';
import 'package:verifall_app/data/datasources/remote/verify/verification_remote_source.dart';
import 'package:verifall_app/data/models/api_envelope.dart';
import 'package:verifall_app/data/repositories/verify/verification_repository_impl.dart';
import 'package:verifall_app/domain/entities/tool_outil.dart';
import 'package:verifall_app/domain/entities/verification_entite.dart';
import 'package:verifall_app/domain/entities/verification_rapport.dart';
import 'package:verifall_app/domain/entities/verification_type_contenu.dart';
import 'package:verifall_app/domain/repositories/verification_repository.dart';

import '../../support/fakes.dart';
import 'support/verify_fakes.dart';

/// Un cache local qui ne fait rien, pour les tests qui ne le regardent pas.
///
/// POURQUOI il ne monte pas de base Drift : « `flutter test` tourne sur la
/// machine hôte, où la bibliothèque native SQLite n'est pas forcément
/// installée » — le même raisonnement que le magasin de file en mémoire du
/// socle. Ce que ces tests vérifient n'a rien à voir avec SQLite.
class _CacheMuet implements VerificationLocalSource {
  final List<Verification> ecrites = <Verification>[];

  @override
  Future<void> ecrireLaListe(List<Verification> verifications) async =>
      ecrites.addAll(verifications);

  @override
  Future<void> ecrireUne(Verification verification) async =>
      ecrites.add(verification);

  @override
  Future<List<Verification>> lireLaListe() async => ecrites;

  @override
  Future<Verification?> lireUne(String uuid) async => null;

  @override
  Future<DateTime?> dateDeLaListe() async => null;

  @override
  Future<void> ecrireLesOutils(AnnuaireOutils annuaire) async {}

  @override
  Future<({AnnuaireOutils? annuaire, DateTime? luLe})> lireLesOutils() async =>
      (annuaire: null, luLe: null);

  @override
  dynamic noSuchMethod(Invocation invocation) =>
      throw UnimplementedError('${invocation.memberName}');
}

void main() {
  late InMemoryPendingOpsStore file;
  late AppLogger journal;

  setUp(() {
    file = InMemoryPendingOpsStore();
    journal = AppLogger(config: testConfig(), sink: RecordingLogSink());
  });

  VerificationRepositoryImpl depot(ScriptedAdapter adaptateur) =>
      VerificationRepositoryImpl(
        distant: VerificationRemoteSource(clientDeTest(adaptateur)),
        local: _CacheMuet(),
        file: file,
        journal: journal,
      );

  group('Soumission - deux appels, jamais un multipart unique', () {
    test('un media passe par POST /uploads PUIS POST /vérifications', () async {
      final File fichier = File(
        '${Directory.systemTemp.createTempSync().path}/photo.jpg',
      )..writeAsBytesSync(<int>[1, 2, 3]);

      final ScriptedAdapter adaptateur = ScriptedAdapter((
        RequestOptions options,
      ) {
        if (options.path.endsWith('/uploads')) {
          return jsonReponse(<String, Object?>{
            'data': <String, Object?>{
              'televersement': 'tlv-1',
              'nom': 'photo.jpg',
              'octets': 3,
            },
          }, status: 201);
        }
        return jsonReponse(<String, Object?>{
          'data': verificationJson(statut: 'queued', terminee: false),
          'meta': <String, Object?>{'solde': 30},
        }, status: 201);
      });

      await depot(adaptateur).soumettre(
        acteUuid: 'acte-1',
        type: TypeContenu.image,
        affirmation: 'photo.jpg',
        cheminFichierLocal: fichier.path,
        nomFichier: 'photo.jpg',
      );

      expect(adaptateur.recues, hasLength(2));
      expect(adaptateur.recues.first.path, endsWith('/uploads'));
      expect(adaptateur.recues.last.path, endsWith('/verifications'));

      // Le second appel porte l'identifiant de televersement, pas le fichier :
      // « la soumission qui suit ne pese que quelques centaines d'octets, ce
      // qui la rend rejouable sans douleur ».
      final Object? corps = adaptateur.recues.last.data;
      expect(corps, isA<Map<String, Object?>>());
      expect(
        (corps! as Map<String, Object?>)['televersement'],
        equals('tlv-1'),
      );
    });

    test('le corps ne porte JAMAIS de cout', () async {
      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions _) => jsonReponse(<String, Object?>{
          'data': verificationJson(),
        }, status: 201),
      );

      await depot(adaptateur).soumettre(
        acteUuid: 'acte-2',
        type: TypeContenu.texte,
        affirmation: 'Une affirmation assez longue pour passer.',
      );

      final Map<String, Object?> corps =
          adaptateur.recues.single.data! as Map<String, Object?>;
      // « Le cout n'est jamais envoye par le client : il est lu du type. »
      expect(corps.keys, isNot(contains('cout')));
      expect(corps.keys, isNot(contains('cout_unites')));
      expect(corps.keys, unorderedEquals(<String>['type', 'affirmation']));
    });
  });

  group('Idempotence - une cle par ACTE, pas par tentative', () {
    test('POST /vérifications porte l en-tete Idempotency-Key', () async {
      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions _) => jsonReponse(<String, Object?>{
          'data': verificationJson(),
          'meta': <String, Object?>{'solde': 28},
        }, status: 201),
      );

      await depot(adaptateur).soumettre(
        acteUuid: 'acte-stable',
        type: TypeContenu.texte,
        affirmation: 'Une affirmation assez longue pour passer.',
      );

      expect(
        adaptateur.recues.single.headers['Idempotency-Key'],
        equals('acte-stable'),
      );
    });

    test('une réponse perdue met l acte en file AVEC LA MEME cle, '
        'et aucun second debit n a lieu', () async {
      // Une reponse perdue est indiscernable d'une requete perdue : le
      // telephone ne sait pas si la verification a ete lancee.
      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions options) => throw DioException.connectionError(
          requestOptions: options,
          reason: 'réponse perdue',
        ),
      );

      final ResultatSoumission resultat = await depot(adaptateur).soumettre(
        acteUuid: 'acte-perdu',
        type: TypeContenu.texte,
        affirmation: 'Une affirmation assez longue pour passer.',
      );

      expect(resultat, isA<SoumissionEnAttente>());

      final List<PendingOp> enFile = await file.all();
      expect(enFile, hasLength(1));
      // LA MEME cle : c'est ce qui empeche d'etre debite deux fois pour une
      // seule question. Une cle regeneree a l'envoi ne protegerait de rien.
      expect(enFile.single.clientUuid, equals('acte-perdu'));
      expect(enFile.single.idempotencyKey, equals('acte-perdu'));
      expect(enFile.single.kind, equals(PendingOpKind.soumettreVerification));
    });

    test('hors ligne, le media est GARDE localement pour le rejeu', () async {
      final File copie = File(
        '${Directory.systemTemp.createTempSync().path}/abc.jpg',
      )..writeAsBytesSync(<int>[1, 2, 3]);

      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions options) => throw DioException.connectionError(
          requestOptions: options,
          reason: 'hors ligne',
        ),
      );

      await depot(adaptateur).soumettre(
        acteUuid: 'acte-media',
        type: TypeContenu.image,
        affirmation: 'photo.jpg',
        cheminFichierLocal: copie.path,
        nomFichier: 'photo.jpg',
      );

      // Le televersement serveur expire au bout de deux heures : un rejeu
      // au-dela doit re-televerser, donc la file garde la copie locale.
      expect((await file.all()).single.localFilePath, equals(copie.path));
      // Et la charge NE PORTE PAS de televersement : il n'a jamais abouti.
      expect(
        (await file.all()).single.payload.keys,
        isNot(contains('televersement')),
      );
    });

    test(
      'une copie locale disparue redemande le fichier, sans parler de réseau',
      () async {
        final ScriptedAdapter adaptateur = ScriptedAdapter(
          (RequestOptions _) => jsonReponse(<String, Object?>{'data': null}),
        );

        await expectLater(
          depot(adaptateur).soumettre(
            acteUuid: 'acte-perdu-fichier',
            type: TypeContenu.image,
            affirmation: 'photo.jpg',
            cheminFichierLocal: '/chemin/qui/n/existe/pas.jpg',
            nomFichier: 'photo.jpg',
          ),
          throwsA(isA<NotFoundFailure>()),
        );
      },
    );
  });

  group('Lecture - le cache et l analyse en cours', () {
    test('lire() SANS autorisation de cache remonte l échec plutôt que '
        'de servir une valeur perimee', () async {
      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions options) => throw DioException.connectionError(
          requestOptions: options,
          reason: 'hors ligne',
        ),
      );

      // « L'état d'une analyse en cours ne doit JAMAIS venir du cache » : y
      // montrer une valeur d'il y a cinq minutes fait croire a une analyse
      // bloquee.
      await expectLater(
        depot(adaptateur).lire('uuid-1'),
        throwsA(isA<NetworkFailure>()),
      );
    });
  });

  group('Liste - pagination par curseur, filtres au serveur', () {
    test('les filtres et le curseur repartent ENSEMBLE', () async {
      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions _) => jsonReponse(<String, Object?>{
          'data': <Map<String, Object?>>[verificationJson()],
          'meta': <String, Object?>{
            'page': <String, Object?>{
              'per_page': 20,
              'next_cursor': 'curseur-2',
              'prev_cursor': null,
              'has_more': true,
            },
          },
        }),
      );

      await depot(
        adaptateur,
      ).lister(cursor: 'curseur-1', verdict: 'false', recherche: 'intox');

      final Map<String, dynamic> q = adaptateur.recues.single.queryParameters;
      // « Le curseur n'encode qu'une position dans l'ordre de tri. Un appel qui
      // l'envoie SANS les filtres ne repond pas en erreur : il rend la page
      // suivante de la liste NON filtree. »
      expect(q['cursor'], equals('curseur-1'));
      expect(q['verdict'], equals('false'));
      expect(q['q'], equals('intox'));
      // `page` n'existe pas.
      expect(q.keys, isNot(contains('page')));
    });

    test('« Tous » n envoie aucun parametre de verdict', () async {
      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions _) => jsonReponse(<String, Object?>{
          'data': <Map<String, Object?>>[],
          'meta': <String, Object?>{
            'page': <String, Object?>{
              'per_page': 20,
              'next_cursor': null,
              'has_more': false,
            },
          },
        }),
      );

      await depot(adaptateur).lister();

      // « Omettre le parametre signifie aucun filtre » (contrat 3.6).
      expect(
        adaptateur.recues.single.queryParameters.keys,
        isNot(contains('verdict')),
      );
    });

    test('fin de liste : next_cursor null ET has_more faux', () async {
      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions _) => jsonReponse(<String, Object?>{
          'data': <Map<String, Object?>>[verificationJson()],
          'meta': <String, Object?>{
            'page': <String, Object?>{
              'per_page': 20,
              'next_cursor': null,
              'has_more': false,
            },
          },
        }),
      );

      final CursorPage<Verification> page = await depot(adaptateur).lister();
      expect(page.page.isLastPage, isTrue);
      // Il n'y a PAS de total : le decompte porte sur ce qui est charge.
      expect(page.loadedCount, equals(1));
    });
  });

  group('Contestation', () {
    test('le corps porte `détail` et rien d autre par defaut', () async {
      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions _) => jsonReponse(<String, Object?>{
          'data': <String, Object?>{'uuid': 's-1', 'message': 'transmise'},
        }, status: 201),
      );

      await depot(
        adaptateur,
      ).contester('uuid-1', detail: 'Ce verdict me semble faux parce que...');

      final Map<String, Object?> corps =
          adaptateur.recues.single.data! as Map<String, Object?>;
      expect(corps.keys, equals(<String>['detail']));
      expect(
        adaptateur.recues.single.path,
        endsWith('/verifications/uuid-1/contest'),
      );
    });
  });

  group('Televersement - plafonds du contrat', () {
    test('le type part en multipart avec le champ `fichier`', () async {
      final File fichier = File(
        '${Directory.systemTemp.createTempSync().path}/son.mp3',
      )..writeAsBytesSync(<int>[1, 2, 3, 4]);

      final ScriptedAdapter adaptateur = ScriptedAdapter(
        (RequestOptions _) => jsonReponse(<String, Object?>{
          'data': <String, Object?>{
            'televersement': 'tlv-2',
            'nom': 'son.mp3',
            'octets': 4,
          },
        }, status: 201),
      );

      final VerificationRemoteSource distant = VerificationRemoteSource(
        clientDeTest(adaptateur),
      );
      final televersement = await distant.televerser(
        type: TypeContenu.audio.televersement!,
        cheminLocal: fichier.path,
        nomFichier: 'son.mp3',
      );

      expect(televersement.uuid, equals('tlv-2'));
      expect(adaptateur.recues.single.data, isA<FormData>());
      final FormData corps = adaptateur.recues.single.data! as FormData;
      expect(
        corps.fields.map((MapEntry<String, String> e) => e.key),
        contains('type'),
      );
      expect(
        corps.files.map((MapEntry<String, MultipartFile> e) => e.key),
        contains('fichier'),
      );
    });
  });

  group('Le rapport est null, jamais vide', () {
    test('`rapport: null` ne construit AUCUN rapport', () {
      final Verification v = Verification.fromJson(
        verificationJson(statut: 'analyzing', terminee: false, rapport: null),
      );
      expect(v.rapport, isNull);
      expect(v.aUnRapport, isFalse);
    });

    test('`rapport: {}` ne construit pas davantage un verdict vide', () {
      // Le contrat promet `null` et jamais `{}` — mais si un jour un mandataire
      // en produisait un, le client ne doit pas fabriquer un verdict vide :
      // « c'est le pire malentendu possible sur une plateforme de
      // fact-checking ».
      final Verification v = Verification.fromJson(
        verificationJson(rapport: const <String, Object?>{}),
      );
      expect(v.rapport, isNull);
    });

    test('un rapport rendu porte le libelle DU SERVEUR', () {
      final Verification v = Verification.fromJson(
        verificationJson(rapport: rapportJson()),
      );
      expect(v.rapport!.verdict!.valeur, equals('false'));
      expect(v.rapport!.verdict!.libelle, equals('Faux'));
    });

    test('les champs inconnus sont ignores, pas rejetes', () {
      final Map<String, Object?> avecDuNeuf = verificationJson()
        ..['un_champ_ajoute_plus_tard'] = 42;
      expect(
        () => Verification.fromJson(
          jsonDecode(jsonEncode(avecDuNeuf)) as Map<String, Object?>,
        ),
        returnsNormally,
      );
    });
  });

  group(
    'Les preuves de la Phase 11 sont lues (audit Flutter/Laravel du 8 octobre 2026)',
    () {
      test('une preuve servie porte son nom, sa position et sa fiabilite', () {
        final Verification v = Verification.fromJson(
          verificationJson(
            rapport: rapportJson(preuves: <Map<String, Object?>>[preuveJson()]),
          ),
        );

        expect(v.rapport!.preuves, hasLength(1));
        final Preuve preuve = v.rapport!.preuves.single;
        expect(preuve.nom, equals('Agence Fact-Check'));
        expect(preuve.url, equals('https://exemple.org/article'));
        expect(preuve.domaine, equals('exemple.org'));
        expect(preuve.position!.valeur, equals('refutes'));
        expect(preuve.position!.libelle, equals('Contredit'));
        expect(preuve.fiabilite, equals(88));
        expect(preuve.recuperee, isTrue);
      });

      test('sans cle `preuves`, la liste est vide — jamais une erreur', () {
        // La cle n'existait pas avant la Phase 11 : un ancien rapport en cache
        // local, ecrit par une version anterieure de l'app, ne la porte pas.
        final Map<String, Object?> rapportSansPreuves = rapportJson()
          ..remove('preuves');
        final Verification v = Verification.fromJson(
          verificationJson(rapport: rapportSansPreuves),
        );

        expect(v.rapport!.preuves, isEmpty);
      });

      test('le cache local se relit a l identique (aller-retour toJson)', () {
        final VerificationRapport original = VerificationRapport.fromJson(
          rapportJson(preuves: <Map<String, Object?>>[preuveJson()]),
        );
        final VerificationRapport relu = VerificationRapport.fromJson(
          original.toJson(),
        );

        expect(relu.preuves, hasLength(1));
        expect(relu.preuves.single.nom, equals(original.preuves.single.nom));
        expect(
          relu.preuves.single.position!.valeur,
          equals(original.preuves.single.position!.valeur),
        );
      });
    },
  );
}
