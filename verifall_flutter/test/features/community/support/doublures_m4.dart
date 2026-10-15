import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/core/network/auth_controller.dart';
import 'package:verifall_app/core/network/session_state.dart';
import 'package:verifall_app/core/network/token_store.dart';
import 'package:verifall_app/data/models/api_enums.dart';
import 'package:verifall_app/data/models/auth_token.dart';
import 'package:verifall_app/data/repositories/community/community_data_providers.dart';
import 'package:verifall_app/domain/entities/article_entity.dart';
import 'package:verifall_app/domain/entities/opportunity_entity.dart';
import 'package:verifall_app/domain/entities/thread_entity.dart';
import 'package:verifall_app/domain/repositories/forum_repository.dart';
import 'package:verifall_app/domain/repositories/hub_repository.dart';

import '../../../support/fakes.dart';

/// Les doublures du parcours M4.
///
/// POURQUOI aucune bibliothèque de simulacres : la règle du chantier — « jamais
/// les données du simulateur en dur dans un test » (06-TESTS §6.8) — va avec des
/// fabriques explicites. Une doublure écrite à la main dit ce qu'elle fait ; un
/// simulacre configuré à distance ne le dit pas.

/// Un adaptateur qui répond ce qu'on lui dit **et garde ce qu'on lui a
/// demandé**.
///
/// La seconde moitié est la plus importante ici : ce qui est vérifié dans ce
/// parcours, ce sont les **paramètres de requête** — le filtre qui doit repartir
/// avec le curseur, `scope=benin` et non `bj`, l'absence de `page`.
class AdaptateurScripte implements HttpClientAdapter {
  AdaptateurScripte(this.repondre);

  final ResponseBody Function(RequestOptions options) repondre;

  /// Les requêtes reçues, dans l'ordre.
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

ResponseBody reponseJson(Object corps, {int statut = 200}) {
  return ResponseBody.fromString(
    jsonEncode(corps),
    statut,
    headers: <String, List<String>>{
      Headers.contentTypeHeader: <String>[Headers.jsonContentType],
    },
  );
}

ApiClient clientDeTest(AdaptateurScripte adaptateur) {
  return ApiClient.create(
    baseUrl: 'https://exemple.invalid',
    tokenStore: InMemoryTokenStore(token: 'jeton'),
    logger: AppLogger(config: testConfig(), sink: RecordingLogSink()),
    clientVersion: '1.0.0',
    onUnauthenticated: () async {},
    onClientObsolete: () {},
    onAccountInactive: () {},
    onAccountBeingDeleted: () {},
    adapter: adaptateur,
  );
}

/// Un dépôt de forum en mémoire.
///
/// POURQUOI il enregistre ses appels : plusieurs points de la définition de fini
/// portent sur **ce qui est appelé** — le filtre qui repart, le vote réconcilié,
/// le signalement mis en file — et non sur ce qui s'affiche.
class DepotForumDeTest implements ForumRepository {
  DepotForumDeTest({
    this.page = const PageSujets(
      sujets: <SujetForum>[],
      rubriques: <RubriqueForum>[],
    ),
    this.detail,
    this.cachePage,
    this.cacheDetail,
    this.resultatVote,
    this.issueSignalement = IssueEcriture.envoyee,
    this.erreurLecture,
    this.erreurEcriture,
  });

  PageSujets page;
  SujetForumDetail? detail;
  PageSujets? cachePage;
  SujetForumDetail? cacheDetail;
  ResultatVote? resultatVote;
  IssueEcriture issueSignalement;
  Object? erreurLecture;
  Object? erreurEcriture;

  final List<({String? categorie, String? curseur})> listages =
      <({String? categorie, String? curseur})>[];
  final List<AvisCommunaute> votes = <AvisCommunaute>[];
  final List<MotifSignalement> signalements = <MotifSignalement>[];
  final List<String> reponses = <String>[];

  @override
  Future<PageSujets> listerSujets({String? categorie, String? curseur}) async {
    listages.add((categorie: categorie, curseur: curseur));
    if (erreurLecture != null) {
      throw erreurLecture!;
    }
    return page;
  }

  @override
  Future<PageSujets?> sujetsEnCache({String? categorie}) async => cachePage;

  @override
  Future<SujetForumDetail> lireSujet(String slug) async {
    if (erreurLecture != null) {
      throw erreurLecture!;
    }
    return detail!;
  }

  @override
  Future<SujetForumDetail?> sujetEnCache(String slug) async => cacheDetail;

  @override
  Future<SujetForumDetail> publierSujet({
    required String titre,
    required String corps,
    String? categorie,
    String? lienSignale,
  }) async {
    if (erreurEcriture != null) {
      throw erreurEcriture!;
    }
    return detail!;
  }

  @override
  Future<ResultatVote> voter(String slug, AvisCommunaute avis) async {
    votes.add(avis);
    if (erreurEcriture != null) {
      throw erreurEcriture!;
    }
    return resultatVote ?? ResultatVote(votes: null, monVote: avis);
  }

  @override
  Future<void> repondre(String slug, String corps) async {
    reponses.add(corps);
    if (erreurEcriture != null) {
      throw erreurEcriture!;
    }
  }

  @override
  Future<IssueEcriture> signalerSujet({
    required String slug,
    required MotifSignalement motif,
    String? detail,
  }) async {
    signalements.add(motif);
    return issueSignalement;
  }
}

/// Un dépôt de Hub en mémoire.
class DepotHubDeTest implements HubRepository {
  DepotHubDeTest({
    this.pageArticles = const PageArticles(articles: <ArticleHub>[]),
    this.pageOpportunites = const PageOpportunites(
      opportunites: <Opportunite>[],
    ),
    this.article,
    this.fiche,
    this.cacheOpportunites,
    this.issueSignalement = IssueEcriture.envoyee,
    this.erreurLecture,
  });

  PageArticles pageArticles;
  PageOpportunites pageOpportunites;
  ArticleHub? article;
  FicheOpportunite? fiche;
  PageOpportunites? cacheOpportunites;
  IssueEcriture issueSignalement;
  Object? erreurLecture;

  final List<
    ({PorteeOpportunite? portee, TypeOpportunite? type, String? curseur})
  >
  listages =
      <({PorteeOpportunite? portee, TypeOpportunite? type, String? curseur})>[];
  final List<MotifSignalement> signalements = <MotifSignalement>[];

  @override
  Future<PageArticles> listerArticles({
    String? categorie,
    String? curseur,
  }) async {
    if (erreurLecture != null) {
      throw erreurLecture!;
    }
    return pageArticles;
  }

  @override
  Future<PageArticles?> articlesEnCache({String? categorie}) async => null;

  @override
  Future<ArticleHub> lireArticle(String slug) async {
    if (erreurLecture != null) {
      throw erreurLecture!;
    }
    return article!;
  }

  @override
  Future<ArticleHub?> articleEnCache(String slug) async => null;

  @override
  Future<PageOpportunites> listerOpportunites({
    PorteeOpportunite? portee,
    TypeOpportunite? type,
    String? curseur,
  }) async {
    listages.add((portee: portee, type: type, curseur: curseur));
    if (erreurLecture != null) {
      throw erreurLecture!;
    }
    return pageOpportunites;
  }

  @override
  Future<PageOpportunites?> opportunitesEnCache({
    PorteeOpportunite? portee,
    TypeOpportunite? type,
  }) async => cacheOpportunites;

  @override
  Future<FicheOpportunite> lireOpportunite(String slug) async {
    if (erreurLecture != null) {
      throw erreurLecture!;
    }
    return fiche!;
  }

  @override
  Future<FicheOpportunite?> opportuniteEnCache(String slug) async => null;

  @override
  Future<IssueEcriture> signalerOpportunite({
    required String slug,
    required MotifSignalement motif,
    String? detail,
  }) async {
    signalements.add(motif);
    return issueSignalement;
  }
}

/// Une session ouverte, pour les écrans qui lisent le compte connecté.
///
/// C'est la surcharge de session de recette du socle, réduite au strict
/// nécessaire : « M2 à M5 n'attendent pas M1 » (§8.0).
class SessionDeTest extends AuthController {
  @override
  SessionState build() => const SessionState.connecte(
    utilisateur: UtilisateurResume(
      uuid: 'u-1',
      prenom: 'Rita',
      email: 'rita@exemple.invalid',
      unites: 12,
    ),
  );
}

/// Monte un écran du parcours dans un routeur minimal.
///
/// POURQUOI un vrai `GoRouter` : les écrans se renvoient les uns aux autres par
/// la navigation, et `HubPage` interroge `context.canPop()` dès sa
/// construction. Un `MaterialApp` simple ne le fournit pas.
///
/// POURQUOI la liste de surcharges n'est pas typée à la main : le type
/// `Override` de Riverpod 3 n'est pas exporté par `flutter_riverpod`. L'inférence
/// depuis le paramètre `overrides` donne le bon type ; l'écrire ne compilerait
/// pas.
Widget monterEcran({
  required Widget ecran,
  DepotForumDeTest? forum,
  DepotHubDeTest? hub,
}) {
  final GoRouter routeur = GoRouter(
    routes: <RouteBase>[
      GoRoute(
        path: '/',
        // POURQUOI un `Scaffold` autour de l'ecran : en production, le forum et
        // le Hub sont les enfants de `VfShellScaffold`, qui en pose un. Les
        // composants du design system s'appuient sur ce `Material` ancetre.
        builder: (_, _) => Scaffold(body: ecran),
      ),
    ],
  );
  return ProviderScope(
    overrides: [
      // La session de recette : « M2 a M5 n'attendent pas M1 » (§8.0).
      authControllerProvider.overrideWith(SessionDeTest.new),
      if (forum != null) forumRepositoryProvider.overrideWithValue(forum),
      if (hub != null) hubRepositoryProvider.overrideWithValue(hub),
    ],
    child: MaterialApp.router(routerConfig: routeur),
  );
}
