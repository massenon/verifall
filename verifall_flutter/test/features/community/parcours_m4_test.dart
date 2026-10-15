import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/core/network/auth_controller.dart';
import 'package:verifall_app/core/router/routes_community.dart';
import 'package:verifall_app/core/router/vf_routes.dart';
import 'package:verifall_app/data/models/auteur.dart';
import 'package:verifall_app/data/repositories/community/community_data_providers.dart';
import 'package:verifall_app/domain/entities/thread_entity.dart';
import 'package:verifall_app/presentation/features/community/widgets/etats_m4.dart';

import 'support/doublures_m4.dart';
import 'package:verifall_app/presentation/shared/vf_stale_banner.dart';

/// Le parcours M4 de bout en bout : routes, liens profonds, cache.
void main() {
  /// Monte le parcours **à froid**, sur une adresse donnée.
  ///
  /// POURQUOI `initialLocation` et pas une navigation : c'est la seule façon de
  /// reproduire « application fermée ». Un lien profond doit se résoudre sans
  /// aucune pile de navigation derrière lui — « un lien partagé sur WhatsApp
  /// survit plus longtemps que son contenu » (§1.4).
  Widget parcoursAFroid({
    required String adresse,
    DepotForumDeTest? forum,
    DepotHubDeTest? hub,
  }) {
    final GoRouter routeur = GoRouter(
      initialLocation: adresse,
      routes: <RouteBase>[
        ShellRoute(
          builder: (_, _, Widget enfant) => Scaffold(body: enfant),
          routes: communityShellRoutes,
        ),
        ...communityRootRoutes,
      ],
    );
    return ProviderScope(
      overrides: [
        authControllerProvider.overrideWith(SessionDeTest.new),
        if (forum != null) forumRepositoryProvider.overrideWithValue(forum),
        if (hub != null) hubRepositoryProvider.overrideWithValue(hub),
      ],
      child: MaterialApp.router(routerConfig: routeur),
    );
  }

  group('Les chemins portent un slug, jamais un entier', () {
    test('les trois routes de détail sont construites depuis VfRoutes', () {
      expect(
        VfRoutes.forumThread('la-taxe-whatsapp'),
        '/forum/la-taxe-whatsapp',
      );
      expect(
        VfRoutes.hubOpportunity('stagiaires'),
        '/hub/opportunites/stagiaires',
      );
      expect(
        VfRoutes.hubArticle('la-taxe-whatsapp'),
        '/hub-infos/la-taxe-whatsapp',
      );
    });

    test('le lien profond `verifall://hub/{slug}` se traduit', () {
      expect(
        VfDeepLinks.resolve(Uri.parse('verifall://hub/la-taxe-whatsapp')),
        '/hub-infos/la-taxe-whatsapp',
      );
      expect(
        VfDeepLinks.resolve(Uri.parse('verifall://hub/opportunites/stage')),
        '/hub/opportunites/stage',
      );
    });
  });

  group('Le partage des routes entre les deux listes', () {
    test('le forum et le Hub portent la barre basse ; les trois autres non', () {
      expect(communityShellRoutes, hasLength(2));
      expect(
        communityRootRoutes,
        hasLength(3),
        reason:
            'Lecture d un sujet, d une offre, d un article : ces trois ecrans '
            '<< ne doivent pas faire reapparaitre la barre a mi-parcours >>.',
      );
    });
  });

  group('Un lien profond, application fermee', () {
    testWidgets('vers un article depublie : un repli qui n affirme rien', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        parcoursAFroid(
          adresse: '/hub-infos/une-breve-retiree',
          hub: DepotHubDeTest(erreurLecture: const NotFoundFailure()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byType(ReplIntrouvable), findsOneWidget);
      expect(find.text('Cet article n est pas disponible'), findsOneWidget);
      // « Le 404 du contrat couvre aussi bien "depublie" que "jamais existe",
      // et un slug devine ne doit renseigner sur rien. »
      expect(find.textContaining('supprim'), findsNothing);
      expect(find.textContaining('existe pas'), findsNothing);
    });

    testWidgets('vers une offre archivee : le meme repli, vers le Hub', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        parcoursAFroid(
          adresse: '/hub/opportunites/une-offre-archivee',
          hub: DepotHubDeTest(erreurLecture: const NotFoundFailure()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Cette offre n est plus disponible'), findsOneWidget);
      expect(find.text('Revenir au Hub'), findsOneWidget);
    });

    testWidgets('vers un sujet supprime : le repli du forum', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        parcoursAFroid(
          adresse: '/forum/un-sujet-disparu',
          forum: DepotForumDeTest(erreurLecture: const NotFoundFailure()),
        ),
      );
      await tester.pumpAndSettle();

      expect(
        find.text('Cette discussion n est pas disponible'),
        findsOneWidget,
      );
    });
  });

  group('Le cycle de lecture hors ligne', () {
    testWidgets('le cache s affiche, l échec le garde, et le bandeau le date', (
      WidgetTester tester,
    ) async {
      final DepotForumDeTest depot = DepotForumDeTest(
        cachePage: PageSujets(
          sujets: const <SujetForum>[
            SujetForum(
              slug: 'un-sujet-deja-lu',
              titre: 'Un sujet déjà lu',
              corps: '<p>Vu la semaine derniere.</p>',
              auteur: Auteur(
                uuid: 'a',
                nomAffichage: 'Grace T.',
                initiales: 'GT',
              ),
            ),
          ],
          rubriques: const <RubriqueForum>[],
          recupereLe: DateTime(2026, 8, 20, 9, 30),
        ),
        erreurLecture: const NetworkFailure(),
      );

      await tester.pumpWidget(
        parcoursAFroid(adresse: VfRoutes.forum, forum: depot),
      );
      await tester.pumpAndSettle();

      // 1. Le contenu du cache est affiche...
      expect(find.text('Un sujet déjà lu'), findsOneWidget);
      // 2. ... l echec ne l a PAS remplace par une page d erreur...
      expect(find.byType(EtatEchec), findsNothing);
      // 3. ... et le bandeau dit de quand il date.
      expect(find.byType(VfStaleDataBanner), findsOneWidget);
      expect(
        find.textContaining('Données du 20/08/2026 à 09:30'),
        findsOneWidget,
      );
    });
  });
}
