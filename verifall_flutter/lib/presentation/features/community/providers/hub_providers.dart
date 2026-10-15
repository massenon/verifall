import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../data/models/api_enums.dart';
import '../../../../data/repositories/community/community_data_providers.dart';
import '../../../../domain/entities/article_entity.dart';
import '../../../../domain/entities/opportunity_entity.dart';
import '../../../../domain/entities/thread_entity.dart' show IssueEcriture;
import '../../../../domain/repositories/hub_repository.dart';
import '../../../../domain/usecases/community/signaler_un_contenu.dart';

/// Les **trois** rubriques du Hub, et il n'y en a pas de quatrième.
///
/// Les portails officiels sont retirés du parcours citoyen (arbitrage n° 54) :
/// « les données restent en base, l'écran d'administration reste utile — c'est
/// une mise en retrait décidée, pas un abandon ». Cette énumération est ce qui
/// rend leur réintroduction accidentelle impossible.
enum RubriqueHub {
  actualites('Actualités'),
  benin('Opportunités Benin'),
  international('Opportunités Internationales');

  const RubriqueHub(this.libelle);

  final String libelle;

  /// La portée à envoyer dans `?scope=`, ou `null` pour les actualités.
  ///
  /// **`benin` et `international`, jamais `bj` ni `intl`** : `scope` est une
  /// énumération fermée et une valeur inconnue répond `422` (§3.5, §3.7).
  PorteeOpportunite? get portee => switch (this) {
    RubriqueHub.actualites => null,
    RubriqueHub.benin => PorteeOpportunite.benin,
    RubriqueHub.international => PorteeOpportunite.international,
  };
}

/// La rubrique sélectionnée. Un état minuscule, mais partagé par l'en-tête, les
/// pastilles et les deux listes.
class RubriqueHubNotifier extends Notifier<RubriqueHub> {
  @override
  RubriqueHub build() => RubriqueHub.actualites;

  void choisir(RubriqueHub rubrique) => state = rubrique;
}

final NotifierProvider<RubriqueHubNotifier, RubriqueHub> rubriqueHubProvider =
    NotifierProvider<RubriqueHubNotifier, RubriqueHub>(RubriqueHubNotifier.new);

/// L'état de la liste des brèves.
@immutable
class EtatArticles {
  const EtatArticles({
    this.articles = const <ArticleHub>[],
    this.curseurSuivant,
    this.chargementInitial = true,
    this.chargementSuite = false,
    this.echec,
    this.recupereLe,
  });

  final List<ArticleHub> articles;
  final String? curseurSuivant;
  final bool chargementInitial;
  final bool chargementSuite;
  final Failure? echec;

  /// Non nul quand l'affichage vient du cache. Le Hub y vit **15 minutes** — la
  /// durée la plus courte du contrat — et le bandeau d'horodatage y est
  /// indispensable : « `jours_restants` vieux de trois jours induit en erreur,
  /// et le client n'a pas le droit de le recalculer ».
  final DateTime? recupereLe;

  bool get aUneSuite => curseurSuivant != null;

  /// L'article à la une : **le premier de la liste**.
  ///
  /// POURQUOI le premier et non un choix local : l'ordre vient du serveur. Le
  /// simulateur prend `NEWS[1]` parce qu'il compose sa propre démonstration ;
  /// désigner « la une » côté client reviendrait à faire de l'édito.
  ArticleHub? get aLaUne => articles.isEmpty ? null : articles.first;

  /// Les brèves sous la une.
  List<ArticleHub> get suite =>
      articles.isEmpty ? const <ArticleHub>[] : articles.sublist(1);

  EtatArticles copyWith({
    List<ArticleHub>? articles,
    String? curseurSuivant,
    bool effacerCurseur = false,
    bool? chargementInitial,
    bool? chargementSuite,
    Failure? echec,
    bool effacerEchec = false,
    DateTime? recupereLe,
    bool effacerRecupereLe = false,
  }) {
    return EtatArticles(
      articles: articles ?? this.articles,
      curseurSuivant: effacerCurseur
          ? null
          : (curseurSuivant ?? this.curseurSuivant),
      chargementInitial: chargementInitial ?? this.chargementInitial,
      chargementSuite: chargementSuite ?? this.chargementSuite,
      echec: effacerEchec ? null : (echec ?? this.echec),
      recupereLe: effacerRecupereLe ? null : (recupereLe ?? this.recupereLe),
    );
  }
}

class HubArticlesNotifier extends Notifier<EtatArticles> {
  HubRepository get _depot => ref.read(hubRepositoryProvider);

  @override
  EtatArticles build() {
    unawaited(Future<void>.microtask(charger));
    return const EtatArticles();
  }

  Future<void> charger() async {
    if (state.articles.isEmpty) {
      final PageArticles? cache = await _depot.articlesEnCache();
      if (cache != null && cache.articles.isNotEmpty) {
        state = state.copyWith(
          articles: cache.articles,
          chargementInitial: false,
          recupereLe: cache.recupereLe,
        );
      }
    }
    try {
      final PageArticles page = await _depot.listerArticles();
      state = state.copyWith(
        articles: page.articles,
        curseurSuivant: page.curseurSuivant,
        effacerCurseur: page.curseurSuivant == null,
        chargementInitial: false,
        effacerEchec: true,
        effacerRecupereLe: true,
      );
    } on Failure catch (echec) {
      state = state.copyWith(chargementInitial: false, echec: echec);
    }
  }

  Future<void> chargerLaSuite() async {
    final String? curseur = state.curseurSuivant;
    if (curseur == null || state.chargementSuite) {
      return;
    }
    state = state.copyWith(chargementSuite: true);
    try {
      final PageArticles page = await _depot.listerArticles(curseur: curseur);
      state = state.copyWith(
        articles: <ArticleHub>[...state.articles, ...page.articles],
        curseurSuivant: page.curseurSuivant,
        effacerCurseur: page.curseurSuivant == null,
        chargementSuite: false,
        effacerEchec: true,
      );
    } on ValidationFailure {
      // Curseur perime : on l'oublie et on recharge depuis le debut (§3.4).
      state = state.copyWith(chargementSuite: false, effacerCurseur: true);
      await charger();
    } on Failure catch (echec) {
      state = state.copyWith(chargementSuite: false, echec: echec);
    }
  }
}

final NotifierProvider<HubArticlesNotifier, EtatArticles> hubArticlesProvider =
    NotifierProvider<HubArticlesNotifier, EtatArticles>(
      HubArticlesNotifier.new,
    );

/// L'état d'une liste d'opportunités.
@immutable
class EtatOpportunites {
  const EtatOpportunites({
    this.opportunites = const <Opportunite>[],
    this.typesVus = const <TypeVu>[],
    this.portee,
    this.typeActif,
    this.curseurSuivant,
    this.chargementInitial = true,
    this.chargementSuite = false,
    this.echec,
    this.recupereLe,
  });

  /// **Dans l'ordre du serveur** : ouvertes d'abord, échéance la plus proche en
  /// tête, clôturées ensuite. Jamais retriées ici.
  final List<Opportunite> opportunites;

  /// Les types **réellement rencontrés**, pour les pastilles de filtre.
  ///
  /// POURQUOI ils s'accumulent au lieu d'être recalculés à chaque page : quand
  /// un filtre de type est actif, la liste ne contient plus qu'un type — les
  /// pastilles disparaîtraient, et avec elles le moyen de revenir. On garde donc
  /// ce qui a été vu **sans filtre**.
  ///
  /// L'arbitrage n° 55 en fait une règle : « proposer un type sans offre mène à
  /// une liste vide, et une liste vide derrière un filtre passe pour un bug ».
  final List<TypeVu> typesVus;

  final PorteeOpportunite? portee;
  final TypeOpportunite? typeActif;
  final String? curseurSuivant;
  final bool chargementInitial;
  final bool chargementSuite;
  final Failure? echec;
  final DateTime? recupereLe;

  bool get aUneSuite => curseurSuivant != null;

  /// Le décompte des offres **ouvertes chargées**.
  ///
  /// POURQUOI ce n'est pas un total : l'API n'en donne pas — « il n'y a pas de
  /// total et on ne peut pas sauter à la page 7 » (§3.1). Le libellé de l'écran
  /// le dit honnêtement quand une suite existe, plutôt que de promettre un
  /// chiffre que personne n'a mesuré.
  int get nbOuvertesChargees =>
      opportunites.where((Opportunite o) => !o.expiree).length;

  EtatOpportunites copyWith({
    List<Opportunite>? opportunites,
    List<TypeVu>? typesVus,
    PorteeOpportunite? portee,
    TypeOpportunite? typeActif,
    bool effacerType = false,
    String? curseurSuivant,
    bool effacerCurseur = false,
    bool? chargementInitial,
    bool? chargementSuite,
    Failure? echec,
    bool effacerEchec = false,
    DateTime? recupereLe,
    bool effacerRecupereLe = false,
  }) {
    return EtatOpportunites(
      opportunites: opportunites ?? this.opportunites,
      typesVus: typesVus ?? this.typesVus,
      portee: portee ?? this.portee,
      typeActif: effacerType ? null : (typeActif ?? this.typeActif),
      curseurSuivant: effacerCurseur
          ? null
          : (curseurSuivant ?? this.curseurSuivant),
      chargementInitial: chargementInitial ?? this.chargementInitial,
      chargementSuite: chargementSuite ?? this.chargementSuite,
      echec: effacerEchec ? null : (echec ?? this.echec),
      recupereLe: effacerRecupereLe ? null : (recupereLe ?? this.recupereLe),
    );
  }
}

/// La liste des opportunités d'une portée — `hubFeedProvider(rubrique, type)`.
class HubOpportunitesNotifier extends Notifier<EtatOpportunites> {
  HubOpportunitesNotifier(this.portee);

  final PorteeOpportunite portee;

  HubRepository get _depot => ref.read(hubRepositoryProvider);

  @override
  EtatOpportunites build() {
    unawaited(Future<void>.microtask(charger));
    return EtatOpportunites(portee: portee);
  }

  Future<void> charger() async {
    final TypeOpportunite? type = state.typeActif;

    if (state.opportunites.isEmpty) {
      final PageOpportunites? cache = await _depot.opportunitesEnCache(
        portee: portee,
        type: type,
      );
      if (cache != null && cache.opportunites.isNotEmpty) {
        state = state.copyWith(
          opportunites: cache.opportunites,
          typesVus: type == null
              ? _typesDe(cache.opportunites)
              : state.typesVus,
          chargementInitial: false,
          recupereLe: cache.recupereLe,
        );
      }
    }

    try {
      final PageOpportunites page = await _depot.listerOpportunites(
        portee: portee,
        type: type,
      );
      if (type != state.typeActif) {
        return;
      }
      state = state.copyWith(
        opportunites: page.opportunites,
        // Les pastilles ne se recalculent QUE sur une liste non filtree.
        typesVus: type == null ? _typesDe(page.opportunites) : state.typesVus,
        curseurSuivant: page.curseurSuivant,
        effacerCurseur: page.curseurSuivant == null,
        chargementInitial: false,
        effacerEchec: true,
        effacerRecupereLe: true,
      );
    } on Failure catch (echec) {
      if (type != state.typeActif) {
        return;
      }
      state = state.copyWith(chargementInitial: false, echec: echec);
    }
  }

  Future<void> chargerLaSuite() async {
    final String? curseur = state.curseurSuivant;
    if (curseur == null || state.chargementSuite) {
      return;
    }
    final TypeOpportunite? type = state.typeActif;
    state = state.copyWith(chargementSuite: true);
    try {
      // LE FILTRE REPART AVEC LE CURSEUR. Sans lui, la page suivante serait
      // celle de la liste NON filtree, sans la moindre erreur pour le signaler.
      final PageOpportunites page = await _depot.listerOpportunites(
        portee: portee,
        type: type,
        curseur: curseur,
      );
      if (type != state.typeActif) {
        return;
      }
      state = state.copyWith(
        opportunites: <Opportunite>[
          ...state.opportunites,
          ...page.opportunites,
        ],
        typesVus: type == null
            ? _typesDe(<Opportunite>[
                ...state.opportunites,
                ...page.opportunites,
              ])
            : state.typesVus,
        curseurSuivant: page.curseurSuivant,
        effacerCurseur: page.curseurSuivant == null,
        chargementSuite: false,
        effacerEchec: true,
      );
    } on ValidationFailure {
      state = state.copyWith(chargementSuite: false, effacerCurseur: true);
      await charger();
    } on Failure catch (echec) {
      state = state.copyWith(chargementSuite: false, echec: echec);
    }
  }

  /// Choisit un type. `null` = la pastille « Tous ».
  Future<void> choisirType(TypeOpportunite? type) async {
    if (type == state.typeActif) {
      return;
    }
    state = state.copyWith(
      typeActif: type,
      effacerType: type == null,
      opportunites: const <Opportunite>[],
      chargementInitial: true,
      effacerCurseur: true,
      effacerEchec: true,
      effacerRecupereLe: true,
    );
    await charger();
  }

  /// Les types présents, dans l'ordre où ils apparaissent.
  ///
  /// Une offre dont le serveur n'envoie pas de libellé n'entre pas dans les
  /// pastilles : une pastille sans texte n'est pas un filtre.
  static List<TypeVu> _typesDe(List<Opportunite> offres) {
    final List<TypeVu> vus = <TypeVu>[];
    for (final Opportunite offre in offres) {
      final TypeOpportunite? type = offre.type;
      final String? libelle = offre.typeLibelle;
      if (type == null || libelle == null) {
        continue;
      }
      if (!vus.any((TypeVu v) => v.type == type)) {
        vus.add(TypeVu(type: type, libelle: libelle));
      }
    }
    return vus;
  }
}

/// POURQUOI ce provider ne porte pas de type explicite : le type de famille de
/// Riverpod 3 n'est pas exporte publiquement. L'inference donne le bon type ;
/// l'ecrire a la main ne compilerait pas.
// ignore: always_specify_types
final hubOpportunitesProvider =
    NotifierProvider.family<
      HubOpportunitesNotifier,
      EtatOpportunites,
      PorteeOpportunite
    >(HubOpportunitesNotifier.new);

/// L'état de la fiche d'une offre.
@immutable
class EtatOpportunite {
  const EtatOpportunite({required this.fiche, this.recupereLe});

  final FicheOpportunite fiche;
  final DateTime? recupereLe;
}

class OpportuniteNotifier extends AsyncNotifier<EtatOpportunite> {
  OpportuniteNotifier(this.slug);

  final String slug;

  HubRepository get _depot => ref.read(hubRepositoryProvider);

  @override
  Future<EtatOpportunite> build() async {
    final FicheOpportunite? cache = await _depot.opportuniteEnCache(slug);
    if (cache != null) {
      unawaited(_rafraichirEnArrierePlan());
      return EtatOpportunite(fiche: cache, recupereLe: DateTime.now());
    }
    return EtatOpportunite(fiche: await _depot.lireOpportunite(slug));
  }

  Future<void> _rafraichirEnArrierePlan() async {
    try {
      state = AsyncData<EtatOpportunite>(
        EtatOpportunite(fiche: await _depot.lireOpportunite(slug)),
      );
    } on Failure {
      // On garde la fiche en cache ET son bandeau d'horodatage : c'est ce qui
      // rend honnete un compte a rebours qu'on n'a pas le droit de recalculer.
    }
  }

  Future<IssueEcriture> signaler({
    required MotifSignalement motif,
    String? detail,
  }) {
    return SignalerUnContenu(ref.read(forumRepositoryProvider), _depot)(
      cible: CibleSignalement.opportunite,
      slug: slug,
      motif: motif,
      detail: detail,
    );
  }
}

/// POURQUOI ce provider ne porte pas de type explicite : le type de famille de
/// Riverpod 3 n'est pas exporte publiquement. L'inference donne le bon type ;
/// l'ecrire a la main ne compilerait pas.
// ignore: always_specify_types
final opportuniteProvider =
    AsyncNotifierProvider.family<OpportuniteNotifier, EtatOpportunite, String>(
      OpportuniteNotifier.new,
      isAutoDispose: true,
    );

/// L'état de la fiche d'une brève.
@immutable
class EtatArticle {
  const EtatArticle({required this.article, this.recupereLe});

  final ArticleHub article;
  final DateTime? recupereLe;
}

class ArticleNotifier extends AsyncNotifier<EtatArticle> {
  ArticleNotifier(this.slug);

  final String slug;

  HubRepository get _depot => ref.read(hubRepositoryProvider);

  @override
  Future<EtatArticle> build() async {
    final ArticleHub? cache = await _depot.articleEnCache(slug);
    if (cache != null) {
      unawaited(_rafraichirEnArrierePlan());
      return EtatArticle(article: cache, recupereLe: DateTime.now());
    }
    // POURQUOI le client n'incremente aucun compteur de lecture : « le compteur
    // de lectures est incremente par le serveur, une fois par ouverture ; un
    // rendu rejoue gonflerait les statistiques sans que personne s'en apercoive »
    // (03-ECRANS-05 §3.24).
    return EtatArticle(article: await _depot.lireArticle(slug));
  }

  Future<void> _rafraichirEnArrierePlan() async {
    try {
      state = AsyncData<EtatArticle>(
        EtatArticle(article: await _depot.lireArticle(slug)),
      );
    } on Failure {
      // Le cache reste affiche : « c'est un contenu qu'on rouvre pour le
      // montrer » (05-HORS-LIGNE, ecran 24).
    }
  }
}

/// POURQUOI ce provider ne porte pas de type explicite : le type de famille de
/// Riverpod 3 n'est pas exporte publiquement. L'inference donne le bon type ;
/// l'ecrire a la main ne compilerait pas.
// ignore: always_specify_types
final articleProvider =
    AsyncNotifierProvider.family<ArticleNotifier, EtatArticle, String>(
      ArticleNotifier.new,
      isAutoDispose: true,
    );
