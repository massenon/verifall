import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/core/router/routes_home.dart';
import 'package:verifall_app/core/router/routes_verify.dart';
import 'package:verifall_app/core/router/vf_routes.dart';
import 'package:verifall_app/core/theme/vf_theme.dart';
import 'package:verifall_app/presentation/shared/vf_display.dart';
import 'package:verifall_app/data/datasources/remote/verify/verification_remote_source.dart';
import 'package:verifall_app/data/models/api_envelope.dart';
import 'package:verifall_app/data/repositories/home/dashboard_providers.dart';
import 'package:verifall_app/data/repositories/verify/verification_providers.dart';
import 'package:verifall_app/domain/entities/home_tableau_de_bord.dart';
import 'package:verifall_app/domain/entities/verification_entite.dart';
import 'package:verifall_app/domain/entities/verification_type_contenu.dart';
import 'package:verifall_app/domain/repositories/dashboard_repository.dart';
import 'package:verifall_app/domain/repositories/verification_repository.dart';

import '../home/tableau_de_bord_test.dart' show agregatJson;
import 'support/verify_fakes.dart';

/// Un dépôt de vérifications piloté par le test.
class _DepotFictif implements VerificationRepository {
  _DepotFictif({
    this.liste = const <Verification>[],
    this.resultat,
    this.echecDeSoumission,
    this.indicateursServis,
  });

  List<Verification> liste;
  ResultatSoumission? resultat;
  Object? echecDeSoumission;
  IndicateursHistorique? indicateursServis;

  final List<String> actesRecus = <String>[];
  final List<({String? verdict, String? recherche, String? cursor})> requetes =
      <({String? verdict, String? recherche, String? cursor})>[];

  @override
  Future<ResultatSoumission> soumettre({
    required String acteUuid,
    required TypeContenu type,
    required String affirmation,
    String? contexte,
    String? cheminFichierLocal,
    String? nomFichier,
  }) async {
    actesRecus.add(acteUuid);
    final Object? echec = echecDeSoumission;
    if (echec != null) {
      throw echec;
    }
    return resultat!;
  }

  @override
  Future<Verification> lire(
    String uuid, {
    bool autoriserLeCache = false,
  }) async {
    return liste.firstWhere((Verification v) => v.uuid == uuid);
  }

  @override
  Future<CursorPage<Verification>> lister({
    String? cursor,
    String? verdict,
    String? recherche,
    int limit = PageMeta.defaultLimit,
  }) async {
    requetes.add((verdict: verdict, recherche: recherche, cursor: cursor));
    return CursorPage<Verification>(
      items: liste,
      page: const PageMeta(perPage: 20, hasMore: false),
    );
  }

  @override
  Future<List<Verification>> listerDepuisLeCache() async => liste;

  @override
  Future<DateTime?> dateDuCacheDeLaListe() async => null;

  @override
  Future<IndicateursHistorique> indicateurs() async =>
      indicateursServis ??
      const IndicateursHistorique(
        total: 0,
        intoxDemasquees: 0,
        confirmeesFiables: 0,
      );

  @override
  Future<void> contester(
    String uuid, {
    required String detail,
    String? motif,
  }) async {}
}

class _AccueilFictif implements DashboardRepository {
  _AccueilFictif({this.accueil, this.echec});

  AccueilCitoyen? accueil;
  Object? echec;

  @override
  Future<AccueilCitoyen> lire() async {
    final Object? e = echec;
    if (e != null) {
      throw e;
    }
    return accueil!;
  }

  @override
  Future<AccueilCitoyen?> lireLeCache() async => accueil;
}

/// Un routeur minimal : **mes** routes, plus des destinations muettes pour les
/// écrans des autres parcours.
///
/// POURQUOI des destinations muettes plutôt que le routeur complet : « aucun
/// import croisé entre deux dossiers `features/` ». Ce test vérifie que M2
/// **navigue vers le bon chemin**, pas que l'écran d'arrivée existe — c'est
/// exactement la frontière que la règle 4 dessine.
GoRouter _routeur({String depart = VfRoutes.dashboard}) {
  Widget muet(String nom) => Scaffold(body: Center(child: Text('ecran $nom')));
  return GoRouter(
    initialLocation: depart,
    routes: <RouteBase>[
      ...homeShellRoutes,
      ...verifyShellRoutes,
      ...homeRootRoutes,
      ...verifyRootRoutes,
      GoRoute(
        path: VfRoutes.wallet,
        builder: (BuildContext c, GoRouterState s) => muet('unites'),
      ),
      GoRoute(
        path: VfRoutes.catalogue,
        builder: (BuildContext c, GoRouterState s) => muet('catalogue'),
      ),
      GoRoute(
        path: '/cours/:slug',
        builder: (BuildContext c, GoRouterState s) => muet('cours'),
      ),
    ],
  );
}

/// POURQUOI le helper prend les depots et non une liste d'overrides : le type
/// `Override` de Riverpod n'est pas exporte publiquement. Passer les depots rend
/// d'ailleurs les tests plus lisibles — on voit ce qui est simule.
Widget _application({
  VerificationRepository? depot,
  DashboardRepository? accueil,
  String depart = VfRoutes.dashboard,
}) => ProviderScope(
  overrides: [
    if (depot != null) verificationRepositoryProvider.overrideWithValue(depot),
    if (accueil != null) dashboardRepositoryProvider.overrideWithValue(accueil),
  ],
  child: MaterialApp.router(
    theme: VfTheme.light(),
    routerConfig: _routeur(depart: depart),
  ),
);

AccueilCitoyen _accueil({int? unites = 34, bool venuDuCache = false}) =>
    AccueilCitoyen(
      tableau: TableauDeBord.fromJson(
        agregatJson(unites: unites),
        luLe: DateTime.now().toUtc(),
      ),
      repriseLue: true,
      venuDuCache: venuDuCache,
    );

void main() {
  // Une surface haute, pour que l'ecran entier soit rendu.
  //
  // POURQUOI : le banc de test rend 800 x 600 par defaut, alors que ces ecrans
  // sont concus pour un telephone qui DEFILE. Sans cela, la moitie des blocs
  // n'est jamais construite et les tests verifieraient l'absence de ce qui est
  // simplement hors du viewport — un faux negatif qui apprend a ignorer les
  // echecs.
  void surfaceHaute(WidgetTester tester) {
    tester.view.physicalSize = const Size(1000, 4000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
  }

  group('Le tableau de bord', () {
    testWidgets('LA CLOCHE EST MASQUEE, pas grisee', (
      WidgetTester tester,
    ) async {
      surfaceHaute(tester);
      await tester.pumpWidget(
        _application(
          accueil: _AccueilFictif(accueil: _accueil()),
          depot: _DepotFictif(),
        ),
      );
      await tester.pumpAndSettle();

      // « Une cloche sans pastille invite a cliquer ; une cloche qui ouvre un
      // ecran vide se lit comme un defaut. Un indicateur qu'on ne mesure pas ne
      // s'affiche pas. »
      expect(find.byTooltip('Ouvrir les notifications'), findsNothing);
      expect(find.byIcon(Icons.notifications_none_rounded), findsNothing);
    });

    testWidgets('le solde servi par le CACHE affiche un tiret, jamais 0', (
      WidgetTester tester,
    ) async {
      surfaceHaute(tester);
      await tester.pumpWidget(
        _application(
          // Un accueil servi par Drift : `unites` est ABSENT, parce que le
          // solde ne se met jamais en cache.
          accueil: _AccueilFictif(
            accueil: _accueil(unites: null, venuDuCache: true),
          ),
          depot: _DepotFictif(),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('—'), findsWidgets);
      expect(find.text('0'), findsNothing);
      // Et le bandeau dit d'ou viennent les donnees.
      expect(find.textContaining('actualisation impossible'), findsOneWidget);
    });

    testWidgets('sans cache, l ecran le DIT et propose de réessayer', (
      WidgetTester tester,
    ) async {
      surfaceHaute(tester);
      await tester.pumpWidget(
        _application(
          accueil: _AccueilFictif(echec: const NetworkFailure()),
          depot: _DepotFictif(),
        ),
      );
      await tester.pumpAndSettle();

      // « Jamais un tableau de bord vide silencieux. »
      expect(find.textContaining('indisponible'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('un laboratoire vide propose de commencer', (
      WidgetTester tester,
    ) async {
      surfaceHaute(tester);
      await tester.pumpWidget(
        _application(
          accueil: _AccueilFictif(accueil: _accueil()),
          depot: _DepotFictif(),
        ),
      );
      await tester.pumpAndSettle();

      // Le bloc est en bas de l'ecran : on y descend, comme la personne le
      // ferait. Un `ListView` ne construit pas ce qui est hors du viewport.
      expect(find.text('Votre laboratoire est encore vide'), findsOneWidget);
    });
  });

  group('L historique', () {
    testWidgets('les filtres partent AU SERVEUR, jamais en local', (
      WidgetTester tester,
    ) async {
      surfaceHaute(tester);
      final _DepotFictif depot = _DepotFictif(
        liste: <Verification>[
          Verification.fromJson(verificationJson(rapport: rapportJson())),
        ],
      );

      await tester.pumpWidget(
        _application(depot: depot, depart: VfRoutes.myVerifications),
      );
      await tester.pumpAndSettle();

      // La pastille de filtre, pas la pastille de verdict d'une tuile : les
      // deux portent le mot « FAUX ».
      await tester.tap(
        find.descendant(of: find.byType(VfChips), matching: find.text('FAUX')),
      );
      await tester.pumpAndSettle();

      // « Filtrer localement ne filtre que la page deja chargee ; a la deuxieme
      // page, l'utilisateur voit apparaitre des elements qui ne correspondent
      // pas a sa selection » (arbitrage n. 82).
      expect(depot.requetes.last.verdict, equals('false'));
    });

    testWidgets('le decompte parle de ce qui est CHARGE, pas d un total', (
      WidgetTester tester,
    ) async {
      surfaceHaute(tester);
      await tester.pumpWidget(
        _application(
          depot: _DepotFictif(
            liste: <Verification>[
              Verification.fromJson(verificationJson(rapport: rapportJson())),
            ],
          ),
          depart: VfRoutes.myVerifications,
        ),
      );
      await tester.pumpAndSettle();

      // « Il n'y a pas de total » : le libelle doit supporter cette honnetete.
      expect(find.text('1 résultat charge'), findsOneWidget);
    });

    testWidgets(
      'les indicateurs viennent de tout l historique, pas de la page',
      (WidgetTester tester) async {
        surfaceHaute(tester);
        await tester.pumpWidget(
          _application(
            depot: _DepotFictif(
              liste: <Verification>[
                Verification.fromJson(verificationJson(rapport: rapportJson())),
              ],
              indicateursServis: const IndicateursHistorique(
                total: 312,
                intoxDemasquees: 120,
                confirmeesFiables: 90,
              ),
            ),
            depart: VfRoutes.myVerifications,
          ),
        );
        await tester.pumpAndSettle();

        // Une seule verification chargee, 312 au total : les compter sur la
        // page afficherait « 1 » a quelqu'un qui en a trois cents.
        expect(find.text('312'), findsOneWidget);
        expect(find.text('120'), findsOneWidget);
      },
    );

    testWidgets('deux etats vides DISTINCTS', (WidgetTester tester) async {
      surfaceHaute(tester);
      await tester.pumpWidget(
        _application(depot: _DepotFictif(), depart: VfRoutes.myVerifications),
      );
      await tester.pumpAndSettle();

      // Sans filtre : « rien encore fait ».
      expect(find.text('Votre laboratoire est encore vide'), findsOneWidget);

      await tester.tap(
        find.descendant(of: find.byType(VfChips), matching: find.text('VRAI')),
      );
      await tester.pumpAndSettle();

      // Avec filtre : « aucun resultat », et l'action DIFFERE — proposer
      // « effacer les filtres » a quelqu'un qui n'a jamais rien fait serait
      // absurde (arbitrage n. 31).
      expect(find.text('Aucun résultat'), findsOneWidget);
      expect(find.text('Reinitialiser les filtres'), findsOneWidget);
    });
  });

  group('La soumission, de bout en bout', () {
    testWidgets(
      'hors ligne : la demande est mise en file, et AUCUNE chronologie '
      'd analyse ne s ouvre',
      (WidgetTester tester) async {
        surfaceHaute(tester);
        final _DepotFictif depot = _DepotFictif(
          resultat: const SoumissionEnAttente(
            SoumissionMiseEnFile(acteUuid: 'acte-hors-ligne'),
          ),
        );

        await tester.pumpWidget(
          _application(depot: depot, depart: VfRoutes.verify),
        );
        await tester.pumpAndSettle();

        await tester.enterText(
          find.byType(TextField).first,
          'Une rumeur assez longue pour être acceptee.',
        );
        await tester.pump();
        await tester.tap(find.text('Lancer la vérification'));
        await tester.pumpAndSettle();

        // « Ne pas simuler une attente » : on reste sur l'ecran, et on le dit.
        expect(find.textContaining('des le retour du réseau'), findsOneWidget);
        expect(find.text('Le laboratoire travaille'), findsNothing);
        expect(depot.actesRecus, hasLength(1));
      },
    );

    testWidgets('402 solde insuffisant OUVRE LA RECHARGE', (
      WidgetTester tester,
    ) async {
      surfaceHaute(tester);
      final _DepotFictif depot = _DepotFictif(
        echecDeSoumission: const InsufficientBalanceFailure(
          serverMessage: 'Vous n avez pas assez d unités.',
        ),
      );

      await tester.pumpWidget(
        _application(depot: depot, depart: VfRoutes.verify),
      );
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byType(TextField).first,
        'Une rumeur assez longue pour être acceptee.',
      );
      await tester.pump();
      await tester.tap(find.text('Lancer la vérification'));
      await tester.pumpAndSettle();

      // « C'est le seul code HTTP qui dise il manque de quoi payer, et le
      // contrat l'emploie expres pour ca : un message generique s'afficherait
      // au moment ou la personne est le plus pres d'acheter. »
      expect(find.text('ecran unites'), findsOneWidget);
    });

    testWidgets('une réponse déjà connue va DIRECTEMENT au rapport', (
      WidgetTester tester,
    ) async {
      surfaceHaute(tester);
      final Verification reprise = Verification.fromJson(
        verificationJson(estUneReprise: true, rapport: rapportJson()),
      );
      surfaceHaute(tester);
      final _DepotFictif depot = _DepotFictif(
        liste: <Verification>[reprise],
        resultat: SoumissionEnvoyee(
          SoumissionAboutie(
            verification: reprise,
            solde: 34,
            acteUuid: 'acte-reprise',
          ),
        ),
      );

      await tester.pumpWidget(
        _application(depot: depot, depart: VfRoutes.verify),
      );
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byType(TextField).first,
        'Une rumeur déjà vérifiée, assez longue.',
      );
      await tester.pump();
      await tester.tap(find.text('Lancer la vérification'));
      await tester.pumpAndSettle();

      // Arbitrage n. 25 : la reponse arrive instantanement, unites rendues.
      // « La personne a vu un cout annonce, il faut lui dire pourquoi elle n'a
      // rien paye. »
      expect(find.text('Rapport'), findsOneWidget);
      expect(find.textContaining('sans debiter d unite'), findsOneWidget);
    });
  });
}
