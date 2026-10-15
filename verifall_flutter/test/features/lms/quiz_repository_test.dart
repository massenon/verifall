import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:uuid/uuid.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/data/datasources/local/pending_ops.dart';
import 'package:verifall_app/data/datasources/remote/lms/lms_remote_data_source.dart';
import 'package:verifall_app/data/repositories/lms/quiz_repository_impl.dart';
import 'package:verifall_app/domain/entities/quiz_summary.dart';
import 'package:verifall_app/domain/repositories/quiz_repository.dart';

import '../../support/fakes.dart';
import 'support/lms_fakes.dart';

/// Le quiz : `Idempotency-Key`, correction serveur, et rien en cache.
void main() {
  late AdaptateurScripte adaptateur;
  late InMemoryPendingOpsStore fileStore;
  late QuizRepositoryImpl depot;

  setUp(() {
    fileStore = InMemoryPendingOpsStore();
    adaptateur = AdaptateurScripte(
      (_) => reponseJson(enveloppe(<String, Object?>{})),
    );
    depot = QuizRepositoryImpl(
      distant: LmsRemoteDataSource(clientDeTest(adaptateur)),
      fileStore: fileStore,
      uuid: const Uuid(),
    );
  });

  group('Le paquet installable ne contient AUCUNE bonne réponse', () {
    test('les questions arrivent sans corrige', () async {
      adaptateur.repondre = (_) => reponseJson(
        enveloppe(
          quizJson(
            questions: <Map<String, Object?>>[
              questionJson(idsDeReponse: <int>[11, 12]),
            ],
          ),
          meta: <String, Object?>{'commence_le': '2026-03-02T09:00:00Z'},
        ),
      );

      final QuizDetail quiz = await depot.charger('cours-neutre');

      // Le type lui-meme n'a AUCUN champ pour la bonne reponse — pas meme
      // optionnel : « un champ optionnel finit par etre rempli ».
      final QuizQuestion question = quiz.questions.single;
      expect(question.reponses.map((QuizAnswer r) => r.id), <int>[11, 12]);
      expect(quiz.commenceLe, isNotNull);
    });

    test(
      'le chronometre part de meta.commence_le, pas de l horloge locale',
      () async {
        adaptateur.repondre = (_) => reponseJson(
          enveloppe(
            quizJson(minutes: 10),
            meta: <String, Object?>{'commence_le': '2026-03-02T09:00:00Z'},
          ),
        );

        final QuizDetail quiz = await depot.charger('cours-neutre');

        // L'échéance se deduit de l'heure du SERVEUR. Une horloge de telephone
        // reculee ne rallonge pas l'epreuve.
        expect(quiz.echeance, DateTime.utc(2026, 3, 2, 9, 10));
        expect(
          quiz.echeance!.difference(DateTime.utc(2026, 3, 2, 9, 5)),
          const Duration(minutes: 5),
        );
      },
    );

    test('sans commence_le, le chronometre n existe pas', () async {
      adaptateur.repondre = (_) => reponseJson(enveloppe(quizJson()));

      final QuizDetail quiz = await depot.charger('cours-neutre');

      // Il ne se replie PAS sur l'horloge du telephone : pas d'heure de
      // depart, pas de chronometre.
      expect(quiz.commenceLe, isNull);
      expect(quiz.echeance, isNull);
    });
  });

  group('La cle d idempotence', () {
    test('est generee a l ACTE, envoyee en en-tete, et la ligne de file '
        'porte la meme', () async {
      String? cleEnvoyee;
      adaptateur.repondre = (RequestOptions options) {
        cleEnvoyee = options.headers['Idempotency-Key'] as String?;
        return reponseJson(
          enveloppe(<String, Object?>{
            'uuid': 'tentative-1',
            'score': 80,
            'reussi': true,
            'duree_s': 120,
            'corrige': <Object?>[],
          }),
          statut: 201,
        );
      };

      final IssueDeCopie issue = await depot.rendreLaCopie(
        quizUuid: 'quiz-1',
        reponses: <String, int>{'question-1': 11},
      );

      expect(issue, isA<CopieCorrigee>());
      expect(cleEnvoyee, isNotNull);
      expect(cleEnvoyee!.length, lessThanOrEqualTo(128));
      // La copie a abouti : la ligne de file n'a plus lieu d'etre.
      expect(await fileStore.all(), isEmpty);
    });

    test(
      'sur coupure, la copie reste en file avec SA cle — celle de l acte',
      () async {
        adaptateur.couperLeReseau();

        final IssueDeCopie issue = await depot.rendreLaCopie(
          quizUuid: 'quiz-1',
          reponses: <String, int>{'question-1': 11},
        );

        expect(issue, isA<CopieEnFile>());
        final PendingOp acte = (await fileStore.all()).single;
        expect(acte.kind, PendingOpKind.rendreCopieQuiz);
        // C'est LE point : la cle de la ligne de file est celle de l'acte, et
        // toutes les reprises la reutiliseront. « Une cle regeneree a l'envoi
        // ne protege de rien. »
        expect(acte.idempotencyKey, isNotNull);
        expect(acte.idempotencyKey, (issue as CopieEnFile).cleIdempotence);
        expect(acte.payload['reponses'], <String, int>{'question-1': 11});
      },
    );

    test('une copie VIDE est acceptee et part quand meme', () async {
      Object? corpsEnvoye;
      adaptateur.repondre = (RequestOptions options) {
        corpsEnvoye = options.data;
        return reponseJson(
          enveloppe(<String, Object?>{
            'uuid': 'tentative-2',
            'score': 0,
            'reussi': false,
            'duree_s': 600,
            'corrige': <Object?>[],
          }),
          statut: 201,
        );
      };

      await depot.rendreLaCopie(
        quizUuid: 'quiz-1',
        reponses: const <String, int>{},
      );

      // « C'est la copie de quelqu'un dont le chronometre est arrive au bout. »
      expect(corpsEnvoye, <String, Object?>{'reponses': <String, int>{}});
    });

    test('un refus nomme du serveur ne reste PAS en file', () async {
      adaptateur.repondre = (_) => reponseJson(<String, Object?>{
        'error': <String, Object?>{
          'code': 'action_impossible',
          'message': 'Le temps imparti est ecoule.',
        },
      }, statut: 422);

      await expectLater(
        depot.rendreLaCopie(
          quizUuid: 'quiz-1',
          reponses: <String, int>{'question-1': 11},
        ),
        throwsA(isA<ImpossibleActionFailure>()),
      );

      // « Les echecs ne sont pas memorises. Rejouer un echec figerait une
      // erreur passagere, et le client reessaierait eternellement. »
      expect(await fileStore.all(), isEmpty);
    });
  });

  group('Le corrige', () {
    test('arrive UNE SEULE FOIS, avec la copie', () async {
      adaptateur.repondre = (_) => reponseJson(
        enveloppe(<String, Object?>{
          'uuid': 'tentative-3',
          'score': 50,
          'reussi': false,
          'duree_s': 90,
          'corrige': <Object?>[
            <String, Object?>{
              'question': 'question-1',
              'bonne_reponse': 12,
              'explication': 'Parce que.',
            },
          ],
        }),
        statut: 201,
      );

      final IssueDeCopie issue = await depot.rendreLaCopie(
        quizUuid: 'quiz-1',
        reponses: <String, int>{'question-1': 11},
      );

      final CopieCorrigee corrigee = issue as CopieCorrigee;
      expect(corrigee.resultat.corrige.single.bonneReponseId, 12);
      expect(corrigee.resultat.corrige.single.explication, 'Parce que.');
      // Le score affiche en grand est CELUI DU SERVEUR.
      expect(corrigee.resultat.score, 50);
      // Le comptage des bonnes reponses ne corrige rien : il compare deux
      // valeurs que le serveur a fournies.
      expect(
        corrigee.resultat.bonnesReponses(<String, int>{'question-1': 11}),
        0,
      );
      expect(
        corrigee.resultat.bonnesReponses(<String, int>{'question-1': 12}),
        1,
      );
    });
  });
}
