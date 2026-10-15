import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../data/models/api_enums.dart';
import '../../../../data/repositories/community/community_data_providers.dart';
import '../../../../domain/entities/thread_entity.dart';
import '../../../../domain/repositories/forum_repository.dart';
import '../../../../domain/usecases/community/publier_un_sujet.dart';
import '../../../../domain/usecases/community/repondre_a_un_sujet.dart';
import '../../../../domain/usecases/community/signaler_un_contenu.dart';
import '../../../../domain/usecases/community/voter_sur_un_sujet.dart';

/// L'état de la liste du forum.
///
/// POURQUOI un état explicite plutôt qu'un `AsyncValue<List<SujetForum>>` : le
/// cycle de lecture hors ligne demande quatre choses à la fois — le contenu, sa
/// provenance (cache ou réseau), un chargement de **suite** distinct du
/// chargement initial, et un échec qui n'efface pas ce qui est déjà affiché
/// (05-HORS-LIGNE §5.4). `AsyncValue` n'en porte que deux.
@immutable
class EtatForum {
  const EtatForum({
    this.sujets = const <SujetForum>[],
    this.rubriques = const <RubriqueForum>[],
    this.categorieActive = RubriqueForum.slugToutes,
    this.curseurSuivant,
    this.chargementInitial = true,
    this.chargementSuite = false,
    this.echec,
    this.recupereLe,
  });

  final List<SujetForum> sujets;

  /// Alimentées par `meta.categories[]`, jamais par une liste écrite à la main.
  final List<RubriqueForum> rubriques;

  /// Le slug de la pastille sélectionnée. `toutes` est une **valeur envoyée au
  /// serveur**, pas une absence de filtre côté client (§3.6).
  final String categorieActive;

  final String? curseurSuivant;
  final bool chargementInitial;
  final bool chargementSuite;

  /// Le dernier échec. Il **n'efface pas** [sujets] : hors ligne, on garde
  /// l'affichage et l'on pose le bandeau d'horodatage.
  final Failure? echec;

  /// Non nul quand ce qui est affiché vient du cache.
  final DateTime? recupereLe;

  bool get aUneSuite => curseurSuivant != null;

  /// Les pastilles, « Tous » en tête.
  List<RubriqueForum> get pastilles => <RubriqueForum>[
    RubriqueForum.toutes,
    ...rubriques.where((RubriqueForum r) => r.slug != RubriqueForum.slugToutes),
  ];

  EtatForum copyWith({
    List<SujetForum>? sujets,
    List<RubriqueForum>? rubriques,
    String? categorieActive,
    String? curseurSuivant,
    bool effacerCurseur = false,
    bool? chargementInitial,
    bool? chargementSuite,
    Failure? echec,
    bool effacerEchec = false,
    DateTime? recupereLe,
    bool effacerRecupereLe = false,
  }) {
    return EtatForum(
      sujets: sujets ?? this.sujets,
      rubriques: rubriques ?? this.rubriques,
      categorieActive: categorieActive ?? this.categorieActive,
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

/// La liste du forum — le « `threadListProvider(category)` (PagedNotifier) » du
/// simulateur.
///
/// # Pourquoi la catégorie est dans l'état et non dans une famille
///
/// Un provider par catégorie garderait en mémoire autant de listes que de
/// pastilles touchées, et surtout : chaque changement de pastille repartirait
/// d'un état vide, effaçant le cache déjà affiché. Une seule instance qui change
/// de filtre garde l'écran vivant pendant le rechargement.
class ForumNotifier extends Notifier<EtatForum> {
  ForumRepository get _depot => ref.read(forumRepositoryProvider);

  @override
  EtatForum build() {
    // POURQUOI une microtache : un Notifier ne peut pas affecter son etat avant
    // d'avoir rendu le premier. Les tests appellent `charger()` directement et
    // n'ont donc pas a courir apres cette tache.
    unawaited(Future<void>.microtask(charger));
    return const EtatForum();
  }

  /// Le cycle de lecture en quatre temps (05-HORS-LIGNE §5.4).
  ///
  /// 1. lire le cache et **afficher immédiatement** ;
  /// 2. lancer la requête réseau ;
  /// 3. si elle réussit, remplacer et retirer le bandeau ;
  /// 4. si elle échoue, **garder l'affichage** et poser le bandeau d'horodatage.
  Future<void> charger() async {
    final String categorie = state.categorieActive;

    if (state.sujets.isEmpty) {
      final PageSujets? cache = await _depot.sujetsEnCache(
        categorie: categorie,
      );
      if (cache != null && categorie == state.categorieActive) {
        state = state.copyWith(
          sujets: cache.sujets,
          rubriques: cache.rubriques,
          chargementInitial: false,
          recupereLe: cache.recupereLe,
        );
      }
    }

    try {
      final PageSujets page = await _depot.listerSujets(categorie: categorie);
      // Une reponse arrivee apres un changement de pastille est ignoree : sinon
      // la liste « Sante » s'afficherait sous la pastille « Politique ».
      if (categorie != state.categorieActive) {
        return;
      }
      state = state.copyWith(
        sujets: page.sujets,
        rubriques: page.rubriques,
        curseurSuivant: page.curseurSuivant,
        effacerCurseur: page.curseurSuivant == null,
        chargementInitial: false,
        effacerEchec: true,
        effacerRecupereLe: true,
      );
    } on Failure catch (echec) {
      if (categorie != state.categorieActive) {
        return;
      }
      state = state.copyWith(chargementInitial: false, echec: echec);
    }
  }

  /// La page suivante.
  ///
  /// **Le filtre repart avec le curseur** — c'est le dépôt qui s'en charge, et
  /// c'est structurel : `listerSujets` ne sait pas construire une requête sans
  /// sa catégorie.
  Future<void> chargerLaSuite() async {
    final String? curseur = state.curseurSuivant;
    if (curseur == null || state.chargementSuite) {
      return;
    }
    final String categorie = state.categorieActive;
    state = state.copyWith(chargementSuite: true);
    try {
      final PageSujets page = await _depot.listerSujets(
        categorie: categorie,
        curseur: curseur,
      );
      if (categorie != state.categorieActive) {
        return;
      }
      state = state.copyWith(
        sujets: <SujetForum>[...state.sujets, ...page.sujets],
        curseurSuivant: page.curseurSuivant,
        effacerCurseur: page.curseurSuivant == null,
        chargementSuite: false,
        effacerEchec: true,
      );
    } on ValidationFailure catch (echec) {
      // « Un curseur perime ou illisible repond 422 validation. Le client doit
      // alors OUBLIER SON CURSEUR et recharger la liste depuis le debut, jamais
      // reessayer la meme requete » (§3.4).
      state = state.copyWith(
        chargementSuite: false,
        effacerCurseur: true,
        echec: echec,
      );
      await charger();
    } on Failure catch (echec) {
      state = state.copyWith(chargementSuite: false, echec: echec);
    }
  }

  /// Change de pastille. **Le filtre part au serveur**, jamais au client.
  ///
  /// « Filtrer localement ne filtre que la page déjà chargée ; à la deuxième
  /// page, l'utilisateur voit apparaître des éléments qui ne correspondent pas à
  /// sa sélection » (arbitrage n° 82).
  Future<void> choisirCategorie(String slug) async {
    if (slug == state.categorieActive) {
      return;
    }
    state = state.copyWith(
      categorieActive: slug,
      sujets: const <SujetForum>[],
      chargementInitial: true,
      effacerCurseur: true,
      effacerEchec: true,
      effacerRecupereLe: true,
    );
    await charger();
  }

  /// Publie un sujet. **Ne part jamais en file** : voir le dépôt.
  Future<SujetForumDetail> publier({
    required String titre,
    required String corps,
  }) async {
    final SujetForumDetail detail = await PublierUnSujet(_depot)(
      titre: titre,
      corps: corps,
    );
    // On recharge plutot que d'inserer en tete : l'ordre de la liste appartient
    // au serveur, et un sujet insere localement se retrouverait a la mauvaise
    // place des la page suivante.
    await charger();
    return detail;
  }
}

final NotifierProvider<ForumNotifier, EtatForum> forumProvider =
    NotifierProvider<ForumNotifier, EtatForum>(ForumNotifier.new);

/// L'état de la fiche d'un sujet.
@immutable
class EtatSujet {
  const EtatSujet({
    required this.detail,
    this.recupereLe,
    this.voteProvisoire = false,
  });

  final SujetForumDetail detail;

  /// Non nul tant que l'affichage vient du cache.
  final DateTime? recupereLe;

  /// Le vote attend son envoi. L'écran le dit — « ne jamais faire croire qu'une
  /// écriture a abouti ».
  final bool voteProvisoire;

  EtatSujet copyWith({
    SujetForumDetail? detail,
    bool effacerRecupereLe = false,
    DateTime? recupereLe,
    bool? voteProvisoire,
  }) {
    return EtatSujet(
      detail: detail ?? this.detail,
      recupereLe: effacerRecupereLe ? null : (recupereLe ?? this.recupereLe),
      voteProvisoire: voteProvisoire ?? this.voteProvisoire,
    );
  }
}

/// La fiche d'un sujet — `threadProvider(id)` et `voteProvider(id)` du
/// simulateur, réunis.
///
/// POURQUOI les deux sont réunis : un vote modifie le sujet affiché. Deux
/// providers auraient exigé de synchroniser deux états, et c'est exactement
/// l'endroit où le sondage et le sujet auraient fini par diverger.
class SujetNotifier extends AsyncNotifier<EtatSujet> {
  SujetNotifier(this.slug);

  /// **Un slug**, jamais un entier : les liens profonds partagés sur WhatsApp
  /// ne révèlent ainsi ni le volume ni l'ordre (§4 du contrat, réserve A.8).
  final String slug;

  ForumRepository get _depot => ref.read(forumRepositoryProvider);

  @override
  Future<EtatSujet> build() async {
    final SujetForumDetail? cache = await _depot.sujetEnCache(slug);
    if (cache != null) {
      // Le cache s'affiche tout de suite ; le reseau rafraichira derriere.
      unawaited(_rafraichirEnArrierePlan());
      return EtatSujet(detail: cache, recupereLe: DateTime.now());
    }
    return EtatSujet(detail: await _depot.lireSujet(slug));
  }

  Future<void> _rafraichirEnArrierePlan() async {
    try {
      final SujetForumDetail frais = await _depot.lireSujet(slug);
      state = AsyncData<EtatSujet>(EtatSujet(detail: frais));
    } on Failure {
      // On GARDE l'affichage en cache et son bandeau d'horodatage. Remplacer un
      // contenu lisible par une erreur serait une regression pure.
    }
  }

  /// Le vote, **optimiste puis réconcilié**.
  ///
  /// L'ordre des trois lignes est la règle : on projette, on appelle, on se
  /// range. Le contrat l'autorise et l'explique — « la réponse du serveur fait
  /// foi, l'unicité est en base » — et il en tire l'obligation d'accepter des
  /// pourcentages différents « sans clignoter ».
  Future<void> voter(AvisCommunaute avis) async {
    final EtatSujet? courant = state.value;
    if (courant == null) {
      return;
    }

    state = AsyncData<EtatSujet>(
      courant.copyWith(
        detail: courant.detail.copyWith(
          sujet: VoterSurUnSujet.projectionOptimiste(
            courant.detail.sujet,
            avis,
          ),
        ),
        voteProvisoire: true,
      ),
    );

    try {
      final ResultatVote resultat = await VoterSurUnSujet(_depot)(
        slug: slug,
        avis: avis,
      );
      final EtatSujet base = state.value ?? courant;
      state = AsyncData<EtatSujet>(
        base.copyWith(
          detail: base.detail.copyWith(
            sujet: VoterSurUnSujet.reconcilier(base.detail.sujet, resultat),
          ),
          voteProvisoire: resultat.provisoire,
        ),
      );
    } on Failure {
      // « Vote refuse : l'interface revient a l'etat serveur, avec le message
      // recu. » On rend donc exactement ce qui etait affiche avant le geste.
      state = AsyncData<EtatSujet>(courant);
      rethrow;
    }
  }

  /// Publier une réponse. **Ne part jamais en file.**
  Future<void> repondre(String corps) async {
    await RepondreAUnSujet(_depot)(slug: slug, corps: corps);
    // On relit le fil : le serveur decide de l'ordre et du contenu des
    // reponses, et une reponse inseree localement porterait un auteur et une
    // date fabriques par le client.
    ref.invalidateSelf();
  }

  Future<IssueEcriture> signaler({
    required MotifSignalement motif,
    String? detail,
  }) {
    return SignalerUnContenu(_depot, ref.read(hubRepositoryProvider))(
      cible: CibleSignalement.sujet,
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
final sujetProvider =
    AsyncNotifierProvider.family<SujetNotifier, EtatSujet, String>(
      SujetNotifier.new,
      isAutoDispose: true,
    );
