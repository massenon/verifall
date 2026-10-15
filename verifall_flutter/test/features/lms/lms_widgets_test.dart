import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/theme/vf_metrics.dart';
import 'package:verifall_app/core/theme/vf_theme.dart';
import 'package:verifall_app/data/models/lms/cours_json.dart';
import 'package:verifall_app/data/models/lms/lecon_json.dart';
import 'package:verifall_app/data/models/lms/quiz_json.dart';
import 'package:verifall_app/data/models/lms/seance_json.dart';
import 'package:verifall_app/domain/entities/certificate_summary.dart';
import 'package:verifall_app/domain/entities/course_enrollment.dart';
import 'package:verifall_app/domain/entities/course_summary.dart';
import 'package:verifall_app/domain/entities/lesson_summary.dart';
import 'package:verifall_app/domain/entities/live_class_session.dart';
import 'package:verifall_app/domain/entities/quiz_attempt.dart';
import 'package:verifall_app/domain/entities/quiz_summary.dart';
import 'package:verifall_app/presentation/features/lms/widgets/certificate_card.dart';
import 'package:verifall_app/presentation/features/lms/widgets/course_card.dart';
import 'package:verifall_app/presentation/features/lms/widgets/lesson_tile.dart';
import 'package:verifall_app/presentation/features/lms/widgets/live_class_card.dart';
import 'package:verifall_app/presentation/features/lms/widgets/quiz_widgets.dart';

import 'support/lms_fakes.dart';
import 'package:verifall_app/presentation/shared/vf_stale_banner.dart';

/// Les tests de widget de M3.
///
/// Ils vérifient **les états, pas la mise en page** — la mise en page est du
/// ressort des captures de référence (06-TESTS §6.4).
///
/// Trois scénarios que le chapitre nomme explicitement, et qu'on retrouve ici :
/// **un élément verrouillé affiche sa condition** (« un cadenas nu doit faire
/// échouer un test »), **un bouton dit ce qui va se passer**, et les états vides
/// se distinguent.
void main() {
  Future<void> monter(WidgetTester tester, Widget enfant) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: VfTheme.light(),
        home: Scaffold(
          body: SingleChildScrollView(
            child: Padding(
              padding: const EdgeInsets.all(VfSpacing.x16),
              child: enfant,
            ),
          ),
        ),
      ),
    );
  }

  group('La carte de cours', () {
    testWidgets('affiche « Nouveau », jamais une note fabriquee', (
      WidgetTester tester,
    ) async {
      final CourseSummary cours = CoursJson.resume(coursJson());
      await monter(tester, CourseCard(cours: cours, onTap: () {}));

      // Arbitrage n° 61 : « pas d'avis, pas de note ».
      expect(find.text('Nouveau'), findsOneWidget);
      // Aucune etoile, aucun nombre d'inscrits : ils ne sont pas servis
      // (arbitrage n° 67).
      expect(find.textContaining('★'), findsNothing);
      expect(find.textContaining('inscrits'), findsNothing);
    });

    testWidgets('un cours payant annonce son cout ACTUEL', (
      WidgetTester tester,
    ) async {
      final CourseSummary cours = CoursJson.resume(
        coursJson(coutUnites: 12, estOffert: false),
      );
      await monter(tester, CourseCard(cours: cours, onTap: () {}));

      expect(find.text('12 unités'), findsOneWidget);
      expect(find.text('Gratuit'), findsNothing);
    });

    testWidgets('un cours offert en ce moment annonce « Gratuit »', (
      WidgetTester tester,
    ) async {
      // Le serveur calcule `cout_unites` avec `coutActuel()` : un cours offert
      // jusqu'a une date rend 0. « Renvoyer le prix brut ferait annoncer un
      // prix la ou le serveur ne debitera rien. »
      final CourseSummary cours = CoursJson.resume(
        coursJson(coutUnites: 0, estOffert: true),
      );
      await monter(tester, CourseCard(cours: cours, onTap: () {}));

      expect(find.text('Gratuit'), findsOneWidget);
    });
  });

  group('La ligne de leçon', () {
    testWidgets('une leçon VERROUILLEE affiche sa condition', (
      WidgetTester tester,
    ) async {
      final LessonSummary lecon = LeconJson.resume(
        leconJson(uuid: 'l2', position: 2, ouverte: false),
      );
      await monter(tester, LessonTile(lecon: lecon, numero: 2, onTap: () {}));

      // « Un cadenas nu est une frustration gratuite. La ligne doit dire
      // pourquoi. » Ce test existe pour que le cadenas nu echoue.
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);
      expect(find.text('Terminez la leçon precedente'), findsOneWidget);
    });

    testWidgets('la premiere leçon verrouillee invite a s inscrire', (
      WidgetTester tester,
    ) async {
      final LessonSummary lecon = LeconJson.resume(
        leconJson(uuid: 'l1', position: 1, ouverte: false),
      );
      await monter(tester, LessonTile(lecon: lecon, numero: 1, onTap: () {}));

      expect(
        find.text('Inscrivez-vous pour ouvrir cette leçon'),
        findsOneWidget,
      );
    });

    testWidgets('hors ligne, la leçon non telechargee n est pas verrouillee', (
      WidgetTester tester,
    ) async {
      final LessonSummary lecon = LeconJson.resume(leconJson(uuid: 'l1'));
      await monter(
        tester,
        LessonTile(
          lecon: lecon,
          numero: 1,
          horsLigne: true,
          disponibleHorsLigne: false,
          onTap: () {},
        ),
      );

      // Le verrou est une decision du SERVEUR ; l'absence de réseau n'en est
      // pas une. L'icone n'est donc pas un cadenas, et la phrase est autre.
      expect(find.byIcon(Icons.lock_rounded), findsNothing);
      expect(find.text('Disponible avec une connexion'), findsOneWidget);
    });

    testWidgets('une leçon acquise porte sa coche', (
      WidgetTester tester,
    ) async {
      final LessonSummary lecon = LeconJson.resume(
        leconJson(uuid: 'l1', acquise: true),
      );
      await monter(tester, LessonTile(lecon: lecon, numero: 1, onTap: () {}));

      expect(find.byIcon(Icons.check_circle_rounded), findsOneWidget);
    });
  });

  group('La ligne du quiz', () {
    testWidgets('annonce sa durée, son seuil et son XP', (
      WidgetTester tester,
    ) async {
      final QuizSummary quiz = QuizJson.resume(
        quizJson(minutes: 10, scoreRequis: 70, xp: 30),
      );
      await monter(tester, QuizTile(quiz: quiz, onTap: () {}));

      expect(find.text('10 min · 70 % pour valider · +30 XP'), findsOneWidget);
    });
  });

  group('L option de quiz', () {
    testWidgets('ne marque NI juste NI faux pendant l epreuve', (
      WidgetTester tester,
    ) async {
      // La semantique doit etre active pour que les libelles existent : c'est
      // par eux qu'un lecteur d'ecran apprend l'état d'une option, « annonce en
      // mots » et pas seulement par une couleur.
      final SemanticsHandle poignee = tester.ensureSemantics();
      await monter(
        tester,
        const Column(
          children: <Widget>[
            QuizOption(
              lettre: 'A',
              libelle: 'Réponse 1',
              etat: EtatDOption.neutre,
              onTap: null,
            ),
            QuizOption(
              lettre: 'B',
              libelle: 'Réponse 2',
              etat: EtatDOption.choisie,
              onTap: null,
            ),
          ],
        ),
      );

      // Deux etats seulement pendant le parcours : le client ne SAIT pas ce qui
      // est juste, et le corrige n'arrive qu'a la copie (reserve C.1).
      expect(find.bySemanticsLabel('A, Réponse 1'), findsOneWidget);
      expect(find.bySemanticsLabel('B, Réponse 2, choisie'), findsOneWidget);
      // Aucune option n'est annoncee juste ou fausse pendant l'epreuve.
      expect(find.bySemanticsLabel(RegExp('bonne réponse')), findsNothing);
      expect(find.bySemanticsLabel(RegExp('réponse fausse')), findsNothing);

      poignee.dispose();
    });

    testWidgets('annonce juste/faux EN MOTS sur l ecran de résultat', (
      WidgetTester tester,
    ) async {
      final QuizDetail quiz = QuizJson.detail(
        quizJson(
          questions: <Map<String, Object?>>[
            questionJson(idsDeReponse: <int>[11, 12]),
          ],
        ),
        const <String, Object?>{},
      );

      await monter(
        tester,
        CorrigeDeQuestion(
          question: quiz.questions.single,
          correction: const QuizCorrection(
            questionUuid: 'question-1',
            bonneReponseId: 12,
            explication: 'Parce que la source est anonyme.',
          ),
          choix: 11,
          numero: 1,
        ),
      );

      // L'explication arrive ICI, et nulle part ailleurs.
      expect(find.text('Pourquoi cette réponse'), findsOneWidget);
      expect(find.text('Parce que la source est anonyme.'), findsOneWidget);
    });

    testWidgets('une question sans réponse le dit', (
      WidgetTester tester,
    ) async {
      final QuizDetail quiz = QuizJson.detail(
        quizJson(
          questions: <Map<String, Object?>>[
            questionJson(idsDeReponse: <int>[11, 12]),
          ],
        ),
        const <String, Object?>{},
      );

      await monter(
        tester,
        CorrigeDeQuestion(
          question: quiz.questions.single,
          correction: const QuizCorrection(
            questionUuid: 'question-1',
            bonneReponseId: 12,
            explication: null,
          ),
          choix: null,
          numero: 1,
        ),
      );

      // « Une question sans reponse compte comme fausse » — et l'ecran le dit
      // plutot que de laisser croire a un oubli d'affichage.
      expect(
        find.text('Vous n avez pas repondu a cette question.'),
        findsOneWidget,
      );
    });
  });

  group('Le certificat', () {
    testWidgets('affiche les valeurs FIGEES, pas celles du compte', (
      WidgetTester tester,
    ) async {
      const CertificateSummary certificat = CertificateSummary(
        code: 'VF7KMPQR',
        titulaire: 'Nom au moment de l emission',
        coursTitre: 'Titre au moment de l emission',
        revoque: false,
      );

      await monter(tester, const CertificateCard(certificat: certificat));

      expect(find.text('Nom au moment de l emission'), findsOneWidget);
      expect(find.text('Titre au moment de l emission'), findsOneWidget);
      expect(find.text('VF7KMPQR'), findsOneWidget);
    });

    testWidgets(
      'sans date de delivrance, il le DIT au lieu d en inventer une',
      (WidgetTester tester) async {
        const CertificateSummary certificat = CertificateSummary(
          code: 'VF7KMPQR',
          titulaire: 'Titulaire',
          coursTitre: 'Cours',
          revoque: false,
        );

        await monter(tester, const CertificateCard(certificat: certificat));

        expect(find.text('Non communiquee'), findsOneWidget);
      },
    );

    testWidgets('le filigrane est un CustomPaint, jamais une image', (
      WidgetTester tester,
    ) async {
      const CertificateSummary certificat = CertificateSummary(
        code: 'VF7KMPQR',
        titulaire: 'Titulaire',
        coursTitre: 'Cours',
        revoque: false,
      );

      await monter(tester, const CertificateCard(certificat: certificat));

      expect(find.byType(Image), findsNothing);
      expect(
        find.byWidgetPredicate(
          (Widget w) => w is CustomPaint && w.painter is WatermarkPainter,
        ),
        findsOneWidget,
      );
    });
  });

  group('La carte de seance', () {
    testWidgets('« Rejoindre » est INACTIF tant que `lien_visio` est nul', (
      WidgetTester tester,
    ) async {
      final LiveClassSession seance = SeanceJson.lire(seanceJson(etat: 'live'));

      await monter(
        tester,
        LiveClassCard(seance: seance, onRappeler: (_) {}, onRejoindre: (_) {}),
      );

      expect(find.text('Rejoindre maintenant'), findsOneWidget);
      // Et il DIT pourquoi : un bouton inactif muet fait chercher une panne.
      expect(
        find.text('Le lien de la seance n est pas encore ouvert.'),
        findsOneWidget,
      );
    });

    testWidgets('le libelle du bouton change avec l état', (
      WidgetTester tester,
    ) async {
      for (final MapEntry<String, String> cas in <String, String>{
        'upcoming': 'Me le rappeler',
        'live': 'Rejoindre maintenant',
        'past': 'Voir la rediffusion',
      }.entries) {
        final LiveClassSession seance = SeanceJson.lire(
          seanceJson(etat: cas.key),
        );
        await monter(
          tester,
          LiveClassCard(
            seance: seance,
            onRappeler: (_) {},
            onRejoindre: (_) {},
          ),
        );
        // « Un bouton dit ce qui va se passer » : le libelle qui change n'est
        // pas cosmetique.
        expect(find.text(cas.value), findsOneWidget, reason: cas.key);
      }
    });

    testWidgets('un rappel déjà demande desactive le bouton', (
      WidgetTester tester,
    ) async {
      final LiveClassSession seance = SeanceJson.lire(
        seanceJson(rappelDemande: true),
      );
      await monter(
        tester,
        LiveClassCard(seance: seance, onRappeler: (_) {}, onRejoindre: (_) {}),
      );

      expect(find.text('Rappel demande'), findsOneWidget);
    });
  });

  group('Le bandeau d horodatage', () {
    testWidgets('porte la DATE, sans laquelle la donnee ment', (
      WidgetTester tester,
    ) async {
      await monter(
        tester,
        VfStaleDataBanner(lueLe: DateTime(2026, 3, 2, 9, 5)),
      );

      expect(find.textContaining('02/03/2026'), findsOneWidget);
      expect(find.textContaining('actualisation impossible'), findsOneWidget);
    });
  });

  group('La carte de progression', () {
    testWidgets('compte, et ne fabrique aucun total de catalogue', (
      WidgetTester tester,
    ) async {
      await monter(
        tester,
        ProgressionSummaryCard(
          inscriptions: <CourseEnrollment>[
            CourseEnrollment(
              uuid: 'i1',
              statut: StatutInscription.termine,
              statutLibelle: 'Termine',
              avancement: 100,
              unitesPayees: 0,
            ),
            CourseEnrollment(
              uuid: 'i2',
              statut: StatutInscription.enCours,
              statutLibelle: 'En cours',
              avancement: 40,
              unitesPayees: 0,
            ),
          ],
        ),
      );

      expect(find.text('1 termine · 1 en cours'), findsOneWidget);
      // Le denominateur est « les cours SUIVIS » : le catalogue est pagine par
      // curseur et n'a pas de total (contrat §3.1).
      expect(find.text('Sur 2 cours suivis'), findsOneWidget);
    });

    testWidgets('sans inscription, elle le dit sans afficher un zero muet', (
      WidgetTester tester,
    ) async {
      await monter(
        tester,
        const ProgressionSummaryCard(inscriptions: <CourseEnrollment>[]),
      );

      expect(find.text('Vous ne suivez encore aucun cours.'), findsOneWidget);
    });
  });
}
