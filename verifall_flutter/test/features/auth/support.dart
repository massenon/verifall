import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:verifall_app/core/config/config_providers.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/logging/logging_providers.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/core/network/auth_controller.dart';
import 'package:verifall_app/core/network/local_data_purger.dart';
import 'package:verifall_app/core/network/network_providers.dart';
import 'package:verifall_app/core/network/token_store.dart';
import 'package:verifall_app/core/router/app_router.dart';
import 'package:verifall_app/core/router/routes_auth.dart';
import 'package:verifall_app/core/router/vf_routes.dart';
import 'package:verifall_app/core/theme/vf_theme.dart';
import 'package:verifall_app/data/datasources/remote/auth/nom_appareil.dart';
import 'package:verifall_app/presentation/features/auth/providers/auth_providers.dart';

import '../../support/fakes.dart';

/// Le banc d'essai de M1.
///
/// POURQUOI il monte un vrai `ApiClient` derrière un adaptateur scripté plutôt
/// qu'un faux dépôt : la moitié de ce que M1 doit prouver se joue **dans les
/// intercepteurs** — le jeton posé en en-tête, l'absence de seconde requête sur
/// `401`, le jeton absent des journaux. Un faux dépôt ne traverserait rien de
/// tout cela et rendrait ces tests vides de sens.
class HarnaisAuth {
  HarnaisAuth({
    required ResponseBody Function(RequestOptions) repondre,
    this.appareil = 'Appareil de test',
  }) : adaptateur = AdaptateurScripte(repondre);

  /// Le nom envoyé dans le champ `appareil` des trois appels qui l'acceptent.
  final String appareil;
  final AdaptateurScripte adaptateur;

  final InMemoryTokenStore jetons = InMemoryTokenStore();
  final RecordingLocalDataPurger purge = RecordingLocalDataPurger();
  final RecordingLogSink journal = RecordingLogSink();

  ProviderContainer? _conteneur;

  /// Les requêtes réellement parties. C'est ce qui permet de vérifier qu'il n'y
  /// en a **pas** eu de seconde.
  List<RequestOptions> get requetes => adaptateur.recues;

  /// Toutes les lignes de journal, aplaties. Sert au test « le jeton n'est
  /// jamais journalisé ».
  String get journalComplet =>
      journal.entries.map((LogEntry e) => e.toString()).join('\n');

  /// Le conteneur de providers, créé une fois et fermé en fin de test.
  ProviderContainer get conteneur {
    final ProviderContainer? existant = _conteneur;
    if (existant != null) {
      return existant;
    }
    final ProviderContainer cree = ProviderContainer(
      overrides: [
        appConfigProvider.overrideWithValue(testConfig()),
        clientVersionProvider.overrideWithValue('1.0.0'),
        tokenStoreProvider.overrideWithValue(jetons),
        localDataPurgerProvider.overrideWithValue(purge),
        appLoggerProvider.overrideWithValue(
          AppLogger(config: testConfig(), sink: journal),
        ),
        nomAppareilProvider.overrideWithValue(NomAppareilFixe(appareil)),
        apiClientProvider.overrideWith(
          // Le meme cablage que le socle : sur 401, purge et deconnexion.
          // AUCUN renouvellement — il n'y a rien a renouveler.
          (Ref ref) => ApiClient.create(
            baseUrl: 'https://exemple.invalid',
            tokenStore: jetons,
            logger: ref.watch(appLoggerProvider),
            clientVersion: '1.0.0',
            onUnauthenticated: () => ref
                .read(authControllerProvider.notifier)
                .signOut(revoked: true),
            onClientObsolete: () =>
                ref.read(obsoleteClientProvider.notifier).markObsolete(),
            onAccountInactive: () =>
                ref.read(authControllerProvider.notifier).markAccountInactive(),
            onAccountBeingDeleted: () => ref
                .read(authControllerProvider.notifier)
                .markAccountBeingDeleted(),
            adapter: adaptateur,
          ),
        ),
      ],
    );
    addTearDown(cree.dispose);
    _conteneur = cree;
    return cree;
  }

  /// Monte l'application de test : les six routes de M1, plus une destination
  /// factice pour le tableau de bord.
  ///
  /// POURQUOI un vrai `GoRouter` : « les gardes de route redirigent dans les
  /// deux sens » est un point de la définition de fini, et un
  /// `MaterialApp(home:)` ne redirige rien du tout.
  ///
  /// POURQUOI le tableau de bord est factice : il appartient à M2, et aucun
  /// import croisé entre deux dossiers `features/` n'est permis. Ce que M1 doit
  /// prouver, c'est **qu'il y arrive**.
  Widget application({String depart = VfRoutes.login}) {
    final GoRouter routeur = GoRouter(
      initialLocation: depart,
      routes: <RouteBase>[
        ...authRootRoutes,
        GoRoute(
          path: VfRoutes.dashboard,
          builder: (BuildContext context, GoRouterState state) =>
              const Scaffold(body: Center(child: Text(texteTableauDeBord))),
        ),
      ],
    );
    return UncontrolledProviderScope(
      container: conteneur,
      child: MaterialApp.router(
        theme: VfTheme.light(),
        routerConfig: routeur,
        debugShowCheckedModeBanner: false,
      ),
    );
  }

  /// Le repère du tableau de bord factice.
  static const String texteTableauDeBord = 'TABLEAU DE BORD';
}

/// L'adaptateur scripté : il répond ce qu'on lui dit et **garde ce qu'on lui a
/// demandé**.
class AdaptateurScripte implements HttpClientAdapter {
  AdaptateurScripte(this.repondre);

  final ResponseBody Function(RequestOptions requete) repondre;
  final List<RequestOptions> recues = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    recues.add(options);
    return repondre(options);
  }

  @override
  void close({bool force = false}) {}
}

/// Une réponse JSON, avec son statut.
ResponseBody reponseJson(
  Object corps, {
  int statut = 200,
  Map<String, List<String>> entetes = const <String, List<String>>{},
}) {
  return ResponseBody.fromString(
    jsonEncode(corps),
    statut,
    headers: <String, List<String>>{
      Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      ...entetes,
    },
  );
}

/// L'enveloppe d'émission de jeton, **identique sur les trois points d'entrée**.
///
/// POURQUOI une seule fabrique : les trois appels rendent exactement la même
/// forme, et un test qui écrirait trois fois la même carte laisserait croire
/// qu'elles peuvent diverger.
Map<String, Object?> corpsJeton({
  String token = jetonDeTest,
  String prenom = 'Rita',
  String email = 'rita@exemple.invalid',
  int unites = 10,
}) {
  return <String, Object?>{
    'data': <String, Object?>{
      'token': token,
      'type': 'Bearer',
      'expire_le': '2026-09-24T10:12:00+00:00',
      'utilisateur': <String, Object?>{
        'uuid': 'uuid-de-test',
        'prenom': prenom,
        'email': email,
        'unites': unites,
      },
    },
  };
}

/// Le jeton des tests.
///
/// POURQUOI une valeur aussi reconnaissable : le test « il n'est jamais
/// journalisé » cherche cette chaîne dans tout le journal. Une valeur banale
/// pourrait s'y trouver par hasard et rendre le test faussement vert.
const String jetonDeTest = 'jeton-de-test-17-ZZQvXk9SECRET';

/// L'enveloppe d'erreur du contrat.
Map<String, Object?> corpsErreur(
  String code,
  String message, {
  Map<String, List<String>>? details,
}) {
  return <String, Object?>{
    'error': <String, Object?>{
      'code': code,
      'message': message,
      // `details` n'est present QUE sur `validation` (contrat 2.2).
      'details': ?details,
    },
  };
}

/// Le chemin demandé, sans le préfixe de version — plus lisible dans une
/// assertion.
String cheminDe(RequestOptions requete) =>
    requete.path.replaceFirst('/api/v1', '');

/// Le corps envoyé, en carte.
Map<String, Object?> corpsDe(RequestOptions requete) {
  final Object? data = requete.data;
  if (data is Map) {
    return data.map(
      (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
    );
  }
  return const <String, Object?>{};
}

/// Fait défiler jusqu'à la cible, puis touche.
///
/// POURQUOI ce détour : les écrans 03 et 05 sont plus hauts que la surface de
/// test, et une `ListView` ne **construit pas** ses enfants hors du viewport.
/// La carte des consentements et le bouton d'envoi n'existent donc pas encore
/// dans l'arbre — `tap` échoue sur « 0 widgets », ce qui ne dit rien sur le
/// produit. On défile d'abord, comme le ferait quelqu'un.
Future<void> toucher(WidgetTester tester, Finder cible) async {
  if (cible.evaluate().isEmpty) {
    await tester.scrollUntilVisible(
      cible,
      120,
      scrollable: find.byType(Scrollable).first,
    );
  }
  await tester.ensureVisible(cible);
  await tester.pump();
  await tester.tap(cible);
}
