import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
// `Override` n'est pas exporte par l'entree principale de Riverpod 3.
import 'package:flutter_riverpod/misc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/core/theme/vf_theme.dart';
import 'package:verifall_app/domain/entities/live_class_session.dart';
import 'package:verifall_app/domain/entities/quiz_attempt.dart';
import 'package:verifall_app/domain/entities/quiz_summary.dart';
import 'package:verifall_app/domain/repositories/live_class_repository.dart';
import 'package:verifall_app/domain/repositories/quiz_repository.dart';
import 'package:verifall_app/presentation/features/lms/pages/live_classes_page.dart';
import 'package:verifall_app/presentation/features/lms/pages/quiz_page.dart';
import 'package:verifall_app/presentation/features/lms/providers/lms_providers.dart';
import 'package:verifall_app/presentation/shared/vf_confetti.dart';

/// Les tests d'écran de M3.
///
/// Les providers sont remplacés par des surcharges Riverpod ; **aucun test de
/// widget ne touche le réseau ni la base** (06-TESTS §6.4).
void main() {
  Future<void> monter(
    WidgetTester tester,
    Widget page, {
    required List<Override> surcharges,
  }) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: surcharges,
        child: MaterialApp.router(
          theme: VfTheme.light(),
          routerConfig: GoRouter(
            routes: <RouteBase>[GoRoute(path: '/', builder: (_, _) => page)],
          ),
        ),
      ),
    );
  }

  group('L ecran de quiz — reserve C.1', () {
    testWidgets(
      'pendant l epreuve, AUCUN corrige ni marque juste/faux n apparaît',
      (WidgetTester tester) async {
        final _QuizFake depot = _QuizFake(
          quiz: _quizDeTest(),
          resultat: _resultatDeTest(reussi: true),
        );

        await monter(
          tester,
          const QuizPage(slug: 'cours-neutre'),
          surcharges: <Override>[
            quizRepositoryProvider.overrideWithValue(depot),
          ],
        );
        await tester.pump();

        expect(find.text('Intitule neutre'), findsOneWidget);
        // Le corrige n'existe pas encore : il n'a pas franchi le reseau.
        expect(find.text('Pourquoi cette réponse'), findsNothing);
        expect(find.text('Le corrige'), findsNothing);
      },
    );

    testWidgets('le corrige se deroule sur l ecran de RESULTAT', (
      WidgetTester tester,
    ) async {
      final _QuizFake depot = _QuizFake(
        quiz: _quizDeTest(),
        resultat: _resultatDeTest(reussi: true),
      );

      await monter(
        tester,
        const QuizPage(slug: 'cours-neutre'),
        surcharges: <Override>[quizRepositoryProvider.overrideWithValue(depot)],
      );
      await tester.pump();

      await tester.tap(find.text('Réponse 1'));
      await tester.pump();
      await tester.tap(find.text('Rendre ma copie'));
      await tester.pump();
      await tester.pump();

      // Le score en grand est CELUI DU SERVEUR.
      expect(find.text('80 %'), findsOneWidget);
      // Et le corrige arrive ici, une seule fois.
      expect(find.text('Le corrige'), findsOneWidget);
      expect(find.text('Pourquoi cette réponse'), findsOneWidget);
      expect(find.text('Parce que la source est anonyme.'), findsOneWidget);
    });

    testWidgets('confettis a la réussite', (WidgetTester tester) async {
      final _QuizFake depot = _QuizFake(
        quiz: _quizDeTest(),
        resultat: _resultatDeTest(reussi: true),
      );

      await monter(
        tester,
        const QuizPage(slug: 'cours-neutre'),
        surcharges: <Override>[quizRepositoryProvider.overrideWithValue(depot)],
      );
      await tester.pump();
      await tester.tap(find.text('Rendre ma copie'));
      await tester.pump();
      await tester.pump();

      expect(find.byType(VfConfetti), findsOneWidget);
      await tester.pumpAndSettle(const Duration(seconds: 4));
    });

    testWidgets('JAMAIS de confettis a l échec', (WidgetTester tester) async {
      final _QuizFake depot = _QuizFake(
        quiz: _quizDeTest(),
        resultat: _resultatDeTest(reussi: false),
      );

      await monter(
        tester,
        const QuizPage(slug: 'cours-neutre'),
        surcharges: <Override>[quizRepositoryProvider.overrideWithValue(depot)],
      );
      await tester.pump();
      await tester.tap(find.text('Rendre ma copie'));
      await tester.pump();
      await tester.pump();

      // « Confettis a la reussite, jamais a l'échec » — c'est ecrit dans le
      // pack UX et dans le point de vigilance du simulateur.
      expect(find.byType(VfConfetti), findsNothing);
      expect(find.text('Encore un effort'), findsOneWidget);
      // Et le bouton dit ce qui va se passer : « Reessayer le quiz » est un
      // vrai parcours, pas une faveur (arbitrage n° 40).
      expect(find.text('Réessayer le quiz'), findsOneWidget);
    });

    testWidgets(
      'hors ligne, la copie attend le réseau — AUCUN score n est calcule',
      (WidgetTester tester) async {
        final _QuizFake depot = _QuizFake(
          quiz: _quizDeTest(),
          issueForcee: const CopieEnFile('cle-de-l-acte'),
        );

        await monter(
          tester,
          const QuizPage(slug: 'cours-neutre'),
          surcharges: <Override>[
            quizRepositoryProvider.overrideWithValue(depot),
          ],
        );
        await tester.pump();
        await tester.tap(find.text('Rendre ma copie'));
        await tester.pump();
        await tester.pump();

        expect(find.text('Résultat en attente de connexion'), findsOneWidget);
        // Aucun pourcentage a l'ecran : « jamais un score calcule localement ».
        expect(find.textContaining('%'), findsNothing);
      },
    );

    testWidgets('hors ligne, le quiz ne s ouvre PAS depuis un cache', (
      WidgetTester tester,
    ) async {
      final _QuizFake depot = _QuizFake(
        quiz: _quizDeTest(),
        echecDeChargement: const NetworkFailure(),
      );

      await monter(
        tester,
        const QuizPage(slug: 'cours-neutre'),
        surcharges: <Override>[quizRepositoryProvider.overrideWithValue(depot)],
      );
      await tester.pump();

      expect(find.text('Le quiz n a pas pu s ouvrir'), findsOneWidget);
      // Le message dit POURQUOI : la correction est serveur, donc le quiz ne
      // peut pas s'ouvrir depuis un cache.
      expect(
        find.textContaining('la correction est faite par le serveur'),
        findsOneWidget,
      );
    });
  });

  group('Les Live Classes — trois etats vides DISTINCTS', () {
    testWidgets('chaque onglet dit autre chose', (WidgetTester tester) async {
      final _SeancesFake depot = _SeancesFake(const <LiveClassSession>[]);

      await monter(
        tester,
        const LiveClassesPage(),
        surcharges: <Override>[
          liveClassRepositoryProvider.overrideWithValue(depot),
        ],
      );
      await tester.pump();

      // « Un etat vide unique et generique sur les trois onglets ferait croire
      // a une panne. »
      expect(find.text('Aucune seance programmee'), findsOneWidget);

      await tester.tap(find.text('En direct'));
      await tester.pump();
      expect(find.text('Rien en direct pour l instant'), findsOneWidget);

      await tester.tap(find.text('Rediffusions'));
      await tester.pump();
      expect(find.text('Aucune rediffusion disponible'), findsOneWidget);
    });

    testWidgets('la confirmation de rappel NE PROMET PAS de notification', (
      WidgetTester tester,
    ) async {
      final _SeancesFake depot = _SeancesFake(<LiveClassSession>[
        const LiveClassSession(
          uuid: 'seance-1',
          titre: 'Seance neutre',
          description: '',
          etat: EtatSeance.aVenir,
          minutes: 60,
          rappelDemande: false,
        ),
      ]);

      await monter(
        tester,
        const LiveClassesPage(),
        surcharges: <Override>[
          liveClassRepositoryProvider.overrideWithValue(depot),
        ],
      );
      await tester.pump();

      await tester.tap(find.text('Me le rappeler'));
      await tester.pumpAndSettle();

      // Reserve C.2 : `remind` enregistre l'intention, mais l'envoi du rappel
      // J-1 attend l'infrastructure de notifications. La confirmation dit donc
      // ce qui est vrai, et rien de plus.
      expect(
        find.text('C est note — retrouvez la seance dans Live Classes.'),
        findsOneWidget,
      );
      expect(find.textContaining('notification'), findsNothing);
    });
  });
}

QuizDetail _quizDeTest() => QuizDetail(
  quiz: const QuizSummary(
    uuid: 'quiz-1',
    titre: 'Quiz neutre',
    minutes: 10,
    scoreRequis: 70,
    xp: 30,
  ),
  questions: const <QuizQuestion>[
    QuizQuestion(
      uuid: 'question-1',
      position: 1,
      intitule: 'Intitule neutre',
      reponses: <QuizAnswer>[
        QuizAnswer(id: 11, position: 1, libelle: 'Réponse 1'),
        QuizAnswer(id: 12, position: 2, libelle: 'Réponse 2'),
      ],
    ),
  ],
  // Pas de `commence_le` : le chronometre ne s'affiche pas, et surtout il ne
  // se replie pas sur l'horloge du telephone.
  commenceLe: null,
);

QuizAttemptResult _resultatDeTest({required bool reussi}) => QuizAttemptResult(
  uuid: 'tentative-1',
  score: reussi ? 80 : 20,
  reussi: reussi,
  dureeSecondes: 120,
  corrige: const <QuizCorrection>[
    QuizCorrection(
      questionUuid: 'question-1',
      bonneReponseId: 12,
      explication: 'Parce que la source est anonyme.',
    ),
  ],
);

class _QuizFake implements QuizRepository {
  _QuizFake({
    required this.quiz,
    this.resultat,
    this.issueForcee,
    this.echecDeChargement,
  });

  final QuizDetail quiz;
  final QuizAttemptResult? resultat;
  final IssueDeCopie? issueForcee;
  final Failure? echecDeChargement;

  @override
  Future<QuizDetail> charger(String coursSlug) async {
    final Failure? echec = echecDeChargement;
    if (echec != null) {
      throw echec;
    }
    return quiz;
  }

  @override
  Future<IssueDeCopie> rendreLaCopie({
    required String quizUuid,
    required Map<String, int> reponses,
  }) async => issueForcee ?? CopieCorrigee(resultat!);
}

class _SeancesFake implements LiveClassRepository {
  _SeancesFake(this.seancesRendues);

  final List<LiveClassSession> seancesRendues;

  @override
  Future<SeancesLues> seances() async => SeancesLues(
    seances: seancesRendues,
    lueLe: DateTime(2026, 3, 2, 9),
    vientDuCache: false,
  );

  @override
  Future<bool> demanderUnRappel(String uuid) async => true;
}
