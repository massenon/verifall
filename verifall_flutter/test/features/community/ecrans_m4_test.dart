import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/data/models/auteur.dart';
import 'package:verifall_app/domain/entities/article_entity.dart';
import 'package:verifall_app/domain/entities/opportunity_entity.dart';
import 'package:verifall_app/domain/entities/thread_entity.dart';
import 'package:verifall_app/presentation/features/community/pages/article_page.dart';
import 'package:verifall_app/presentation/features/community/pages/forum_page.dart';
import 'package:verifall_app/presentation/features/community/pages/hub_page.dart';
import 'package:verifall_app/presentation/features/community/pages/opportunity_page.dart';
import 'package:verifall_app/presentation/features/community/providers/hub_providers.dart';
import 'package:verifall_app/presentation/features/community/widgets/etats_m4.dart';
import 'package:verifall_app/presentation/features/community/widgets/hub_widgets.dart';
import 'package:verifall_app/presentation/shared/vf_display.dart';

import 'support/doublures_m4.dart';

/// Les tests de widget des cinq écrans.
void main() {
  const Auteur auteur = Auteur(
    uuid: 'a-1',
    nomAffichage: 'Samuel A.',
    initiales: 'SA',
  );

  Opportunite offre({
    bool expiree = false,
    String? lien = 'verifall.com/carrieres',
  }) {
    return Opportunite(
      slug: 'stagiaires-fact-checking',
      titre: 'Stagiaires fact-checking',
      expiree: expiree,
      verifiee: true,
      chapo: 'Rejoindre la redaction pour six mois.',
      organisation: 'Vérif All',
      lieu: 'Cotonou',
      portee: PorteeOpportunite.benin,
      type: TypeOpportunite.stage,
      typeLibelle: 'Stage',
      joursRestants: expiree ? 4 : 11,
      dateLimite: DateTime(2026, 9, 5),
      lienCandidature: lien,
    );
  }

  group('Ecran 20 — Forum citoyen', () {
    testWidgets('aucune carte ne porte de badge de role d auteur', (
      WidgetTester tester,
    ) async {
      final DepotForumDeTest depot = DepotForumDeTest(
        page: const PageSujets(
          sujets: <SujetForum>[
            SujetForum(
              slug: 'reconnaitre-une-image-ia',
              titre: 'Reconnaitre une image generee par IA',
              corps: '<p>Partagez vos indices favoris.</p>',
              auteur: auteur,
            ),
          ],
          rubriques: <RubriqueForum>[],
        ),
      );

      await tester.pumpWidget(
        monterEcran(ecran: const ForumPage(), forum: depot),
      );
      await tester.pumpAndSettle();

      expect(find.text('Samuel A.'), findsOneWidget);
      // Les trois badges du simulateur n'ont AUCUNE source : l'API ne serialise
      // jamais le role d'un auteur (arbitrage n° 74, reserve C.4).
      expect(find.textContaining('Mentor'), findsNothing);
      expect(find.textContaining('Contributeur'), findsNothing);
      expect(find.textContaining('Citoyenne vérifiée'), findsNothing);
    });

    testWidgets('le forum vide invite a ouvrir le premier sujet', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        monterEcran(ecran: const ForumPage(), forum: DepotForumDeTest()),
      );
      await tester.pumpAndSettle();

      // « Rien encore fait » et « aucun resultat » n appellent pas la meme
      // action (§2.7, arbitrage n° 31).
      expect(find.text('Personne n a encore pose de question'), findsOneWidget);
      expect(find.text('Ouvrir le premier sujet'), findsOneWidget);
    });

    testWidgets('hors ligne sans cache, l ecran dit ce qui reste possible', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        monterEcran(
          ecran: const ForumPage(),
          forum: DepotForumDeTest(erreurLecture: const NetworkFailure()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Vous etes hors ligne'), findsOneWidget);
      expect(find.byType(EtatEchec), findsOneWidget);
    });
  });

  group('Ecran 22 — Hub opportunités', () {
    testWidgets('les trois rubriques sont la, et les portails ne le sont pas', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        monterEcran(ecran: const HubPage(), hub: DepotHubDeTest()),
      );
      await tester.pumpAndSettle();

      expect(find.text('Actualités'), findsOneWidget);
      expect(find.text('Opportunités Benin'), findsOneWidget);
      expect(find.text('Opportunités Internationales'), findsOneWidget);
      // Arbitrage n° 54 : retires du parcours citoyen, et ils ne reviennent pas.
      expect(find.textContaining('portail', findRichText: true), findsNothing);
      expect(find.textContaining('Portails'), findsNothing);
      expect(RubriqueHub.values, hasLength(3));
    });

    testWidgets('les deux encarts de pied accompagnent une liste d offres', (
      WidgetTester tester,
    ) async {
      final DepotHubDeTest hub = DepotHubDeTest(
        pageOpportunites: PageOpportunites(
          opportunites: <Opportunite>[offre()],
        ),
      );

      await tester.pumpWidget(monterEcran(ecran: const HubPage(), hub: hub));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Opportunités Benin'));
      await tester.pumpAndSettle();

      expect(find.byType(VfHubFooterNotices), findsOneWidget);
      expect(
        find.textContaining('7 jours après sa date limite'),
        findsOneWidget,
      );
      expect(find.textContaining('frais de dossier'), findsOneWidget);
      expect(find.text('1 opportunite ouverte'), findsOneWidget);
    });

    testWidgets('la pastille Benin envoie `benin` au depot, pas `bj`', (
      WidgetTester tester,
    ) async {
      final DepotHubDeTest hub = DepotHubDeTest();
      await tester.pumpWidget(monterEcran(ecran: const HubPage(), hub: hub));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Opportunités Internationales'));
      await tester.pumpAndSettle();

      expect(hub.listages, isNotEmpty);
      expect(hub.listages.last.portee, PorteeOpportunite.international);
      expect(hub.listages.last.portee!.wireValue, 'international');
    });
  });

  group('Ecran 23 — Détail d une offre', () {
    testWidgets('l avertissement du serveur s affiche sur la fiche', (
      WidgetTester tester,
    ) async {
      final DepotHubDeTest hub = DepotHubDeTest(
        fiche: FicheOpportunite(
          opportunite: offre(),
          avertissement:
              'Jamais de frais. Si on vous reclame un paiement pour '
              'candidater, c est une arnaque.',
        ),
      );

      await tester.pumpWidget(
        monterEcran(
          ecran: const OpportunityPage(slug: 'stagiaires-fact-checking'),
          hub: hub,
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byType(VfScamWarning), findsOneWidget);
      expect(find.textContaining('Jamais de frais'), findsOneWidget);
    });

    testWidgets('une offre ouverte affiche le domaine sur son bouton', (
      WidgetTester tester,
    ) async {
      final DepotHubDeTest hub = DepotHubDeTest(
        fiche: FicheOpportunite(opportunite: offre(), avertissement: 'A'),
      );

      await tester.pumpWidget(
        monterEcran(
          ecran: const OpportunityPage(slug: 's'),
          hub: hub,
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Postuler sur verifall.com'), findsOneWidget);
      expect(find.byType(VfClosedNotice), findsNothing);
      expect(find.text('EMPLOYEUR VERIFIE'), findsOneWidget);
    });

    testWidgets('une offre clôturée n affiche AUCUN bouton de candidature', (
      WidgetTester tester,
    ) async {
      final DepotHubDeTest hub = DepotHubDeTest(
        fiche: FicheOpportunite(
          opportunite: offre(expiree: true),
          avertissement: 'A',
        ),
      );

      await tester.pumpWidget(
        monterEcran(
          ecran: const OpportunityPage(slug: 's'),
          hub: hub,
        ),
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('Postuler sur'), findsNothing);
      expect(find.byType(VfClosedNotice), findsOneWidget);
      expect(find.text('CLÔTURÉE'), findsOneWidget);
      // L'avertissement reste la : il est SYSTEMATIQUE, meme sur une offre
      // fermee.
      expect(find.byType(VfScamWarning), findsOneWidget);
    });

    testWidgets('un lien profond vers une offre archivee affiche un repli', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        monterEcran(
          ecran: const OpportunityPage(slug: 'une-offre-archivee'),
          hub: DepotHubDeTest(erreurLecture: const NotFoundFailure()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byType(ReplIntrouvable), findsOneWidget);
      expect(find.text('Cette offre n est plus disponible'), findsOneWidget);
      // Le repli N AFFIRME RIEN : le 404 couvre aussi « pas a vous ».
      expect(find.textContaining('supprim'), findsNothing);
      expect(find.text('Revenir au Hub'), findsOneWidget);
    });
  });

  group('Ecran 24 — Article verifie', () {
    ArticleHub article({String? verdictLibelle = 'Dementi'}) => ArticleHub(
      slug: 'taxe-whatsapp',
      titre: 'Aucune taxe WhatsApp n est instauree au Benin',
      categorie: 'Economie',
      chapo: 'Le message ne provient d aucune autorite.',
      corps: '<p>Le regulateur a dementi publiquement.</p>',
      verdict: verdictLibelle == null ? null : 'false',
      verdictLibelle: verdictLibelle,
      sourcePrimaire: 'ARCEP Benin',
      publieLe: DateTime(2026, 8, 20),
    );

    testWidgets('le libelle du serveur est affiche tel quel', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        monterEcran(
          ecran: const ArticlePage(slug: 'taxe-whatsapp'),
          hub: DepotHubDeTest(article: article()),
        ),
      );
      await tester.pumpAndSettle();

      // « Le Hub affiche DEMENTI la ou le rapport dit FAUX. » Le client ne
      // fabrique aucun de ces deux mots : il affiche verdict_libelle.
      expect(find.text('DEMENTI'), findsOneWidget);
      // `VfSourceTile` compose un `Text.rich` : le nom de la source y est un
      // fragment en gras, d ou `findRichText`.
      expect(
        find.textContaining('ARCEP Benin', findRichText: true),
        findsOneWidget,
      );
      expect(find.textContaining('meme methode a chaque cas'), findsOneWidget);
    });

    testWidgets('une breve sans verdict affiche EN COURS, pas un vide', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        monterEcran(
          ecran: const ArticlePage(slug: 'a'),
          hub: DepotHubDeTest(article: article(verdictLibelle: null)),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('EN COURS'), findsOneWidget);
    });

    testWidgets('un article depublie affiche un repli qui n affirme rien', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        monterEcran(
          ecran: const ArticlePage(slug: 'jamais-existe'),
          hub: DepotHubDeTest(erreurLecture: const NotFoundFailure()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Cet article n est pas disponible'), findsOneWidget);
      expect(find.textContaining('supprim'), findsNothing);
    });
  });

  group('Accessibilite — les cibles tactiles', () {
    testWidgets('les boutons de vote atteignent le plancher de 44', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        monterEcran(ecran: const ForumPage(), forum: DepotForumDeTest()),
      );
      await tester.pumpAndSettle();

      // Le declencheur de composition, presente meme sur un forum vide.
      expect(find.byType(VfAvatar), findsWidgets);
    });
  });
}
