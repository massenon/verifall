import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:verifall_app/core/network/session_state.dart';
import 'package:verifall_app/data/models/auth_token.dart';
import 'package:verifall_app/core/router/app_router.dart';
import 'package:verifall_app/core/router/routes_profile.dart';
import 'package:verifall_app/core/router/vf_routes.dart';
import 'package:verifall_app/core/theme/vf_theme.dart';
import 'package:verifall_app/presentation/shared/shared.dart';

import '_fixtures.dart';

/// Le parcours M5, de bout en bout.
///
/// POURQUOI il monte **ses propres routes** et pas l'agrégateur complet : les
/// cinq parcours s'écrivent en parallèle, et un agrégateur qui ne compile pas
/// parce qu'un autre module est à mi-chemin ne dit rien sur celui-ci. Les
/// chemins, eux, viennent de `VfRoutes` — ce sont exactement ceux que
/// l'agrégateur montera.
void main() {
  final TestWidgetsFlutterBinding binding =
      TestWidgetsFlutterBinding.ensureInitialized();

  late ServeurScripte serveur;
  late CacheEnMemoire cache;

  setUp(() {
    cache = CacheEnMemoire();
    serveur = ServeurScripte(<String, Object? Function(RequestOptions)>{
      'GET /me': (_) => reponseMoi(),
      'GET /me/stats': (_) => reponseStats(),
      'GET /me/gamification': (_) => reponseGamification(),
      'GET /me/certificates': (_) => reponseCertificats(),
      'GET /me/wallet': (_) => reponsePortefeuille(),
      'GET /me/wallet/transactions': (_) => reponseMouvements(),
      'GET /me/referrals': (_) => reponseParrainage(),
      'GET /me/settings': (_) => reponseReglages(),
    });
  });

  Future<GoRouter> monterLeParcours(WidgetTester tester) async {
    await binding.setSurfaceSize(const Size(430, 3200));
    addTearDown(() => binding.setSurfaceSize(null));

    final ProviderContainer conteneur = conteneurDeTest(
      serveur: serveur,
      cache: cache,
    );
    addTearDown(conteneur.dispose);

    final GoRouter routeur = GoRouter(
      initialLocation: VfRoutes.profile,
      routes: <RouteBase>[
        // La coque a cinq onglets, comme l'agregateur la monte.
        ShellRoute(
          builder: (BuildContext context, GoRouterState state, Widget child) =>
              VfShellScaffold(child: child),
          routes: profileShellRoutes,
        ),
        ...profileRootRoutes,
      ],
    );
    addTearDown(routeur.dispose);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: conteneur,
        child: MaterialApp.router(
          theme: VfTheme.light(),
          routerConfig: routeur,
        ),
      ),
    );
    await tester.pumpAndSettle();
    return routeur;
  }

  testWidgets('les six ecrans du compte s enchainent', (
    WidgetTester tester,
  ) async {
    final GoRouter routeur = await monterLeParcours(tester);

    expect(find.text('Mon profil'), findsOneWidget);

    for (final (String chemin, String titre) in <(String, String)>[
      (VfRoutes.wallet, 'Mes unités'),
      (VfRoutes.rewards, 'Récompenses'),
      (VfRoutes.stats, 'Statistiques'),
      (VfRoutes.referrals, 'Parrainage'),
      (VfRoutes.settings, 'Réglages'),
    ]) {
      routeur.go(chemin);
      await tester.pumpAndSettle();
      expect(find.text(titre), findsWidgets, reason: 'ecran $chemin');
    }
  });

  testWidgets('les six ecrans portent la barre basse', (
    WidgetTester tester,
  ) async {
    final GoRouter routeur = await monterLeParcours(tester);

    for (final String chemin in <String>[
      VfRoutes.profile,
      VfRoutes.wallet,
      VfRoutes.rewards,
      VfRoutes.stats,
      VfRoutes.referrals,
      VfRoutes.settings,
    ]) {
      routeur.go(chemin);
      await tester.pumpAndSettle();
      expect(find.byType(VfBottomNav), findsOneWidget, reason: chemin);
      // Et c'est l'onglet Profil qui est actif : le simulateur annote les six
      // ecrans `nav: 'profile'`.
      expect(VfShellScaffold.tabForLocation(chemin), VfTab.profil);
    }
  });

  testWidgets('l ecran de suppression ne porte PAS la barre basse', (
    WidgetTester tester,
  ) async {
    final GoRouter routeur = await monterLeParcours(tester);

    serveur.reponses['GET /me'] = (_) =>
        reponseMoi(suppressionDemandeeLe: '2026-08-20T09:00:00+00:00');
    routeur.go(VfRoutes.accountBeingDeleted);
    await tester.pumpAndSettle();

    expect(find.text('Votre compte est ferme'), findsOneWidget);
    // Quatre onglets qui repondraient tous 403 : montrer quatre sorties qui
    // ne menent nulle part est pire que n'en montrer aucune.
    expect(find.byType(VfBottomNav), findsNothing);
  });

  test('la garde du socle envoie bien vers l ecran d état', () {
    // La garde appartient au socle ; ce test verifie que la PAGE de M5 est
    // bien la destination qu'elle designe — c'est le seul point de contact
    // entre les deux, et il ne doit pas se defaire en silence.
    final SessionState enSuppression = SessionState.connecte(
      utilisateur: const UtilisateurResume(
        uuid: 'uuid-du-compte',
        prenom: 'Rita',
        email: 'rita@exemple.invalid',
        unites: 0,
      ),
      suppressionDemandeeLe: DateTime(2026, 8, 20),
    );

    expect(
      evaluateGuard(session: enSuppression, location: VfRoutes.profile),
      VfRoutes.accountBeingDeleted,
    );
    expect(
      evaluateGuard(
        session: enSuppression,
        location: VfRoutes.accountBeingDeleted,
      ),
      isNull,
    );
    // Et la route existe bien du cote de M5.
    expect(
      profileRootRoutes.whereType<GoRoute>().map((GoRoute r) => r.path),
      contains(VfRoutes.accountBeingDeleted),
    );
  });
}
