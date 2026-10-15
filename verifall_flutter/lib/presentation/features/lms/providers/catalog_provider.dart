import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../domain/entities/course_enrollment.dart';
import '../../../../domain/entities/course_level.dart';
import '../../../../domain/entities/course_summary.dart';
import '../../../../domain/repositories/course_repository.dart';
import 'lms_providers.dart';

/// L'état du catalogue.
@immutable
class EtatDuCatalogue {
  const EtatDuCatalogue({
    required this.filtre,
    required this.cours,
    required this.chargement,
    required this.chargementDeSuite,
    required this.curseurSuivant,
    required this.aPlus,
    required this.vientDuCache,
    this.lueLe,
    this.messageDErreur,
  });

  const EtatDuCatalogue.initial()
    : this(
        filtre: FiltreCatalogue.aucun,
        cours: const <CourseSummary>[],
        chargement: true,
        chargementDeSuite: false,
        curseurSuivant: null,
        aPlus: false,
        vientDuCache: false,
      );

  final FiltreCatalogue filtre;
  final List<CourseSummary> cours;
  final bool chargement;
  final bool chargementDeSuite;
  final String? curseurSuivant;
  final bool aPlus;

  /// Vrai quand le réseau n'a pas répondu : l'écran pose alors le bandeau
  /// « Données du `<date>`, actualisation impossible ».
  final bool vientDuCache;

  final DateTime? lueLe;
  final String? messageDErreur;

  bool get estVide => !chargement && cours.isEmpty && messageDErreur == null;

  EtatDuCatalogue copyWith({
    FiltreCatalogue? filtre,
    List<CourseSummary>? cours,
    bool? chargement,
    bool? chargementDeSuite,
    String? curseurSuivant,
    bool effacerCurseur = false,
    bool? aPlus,
    bool? vientDuCache,
    DateTime? lueLe,
    String? messageDErreur,
    bool effacerErreur = false,
  }) {
    return EtatDuCatalogue(
      filtre: filtre ?? this.filtre,
      cours: cours ?? this.cours,
      chargement: chargement ?? this.chargement,
      chargementDeSuite: chargementDeSuite ?? this.chargementDeSuite,
      curseurSuivant: effacerCurseur
          ? null
          : (curseurSuivant ?? this.curseurSuivant),
      aPlus: aPlus ?? this.aPlus,
      vientDuCache: vientDuCache ?? this.vientDuCache,
      lueLe: lueLe ?? this.lueLe,
      messageDErreur: effacerErreur
          ? null
          : (messageDErreur ?? this.messageDErreur),
    );
  }
}

/// Le catalogue — `catalogProvider` du simulateur.
///
/// # La règle qui gouverne ce contrôleur
///
/// **Le filtre part au serveur, toujours.** « On ne filtre jamais côté client
/// une liste paginée : filtrer localement ne filtre que la page déjà chargée ;
/// à la deuxième page, l'utilisateur voit apparaître des éléments qui ne
/// correspondent pas à sa sélection » (arbitrage n° 82).
///
/// Et son corollaire, plus coûteux encore : **tous les paramètres repartent
/// avec le curseur**. L'appel fautif — curseur seul — ne produit aucune erreur ;
/// il rend la page suivante du catalogue **non filtré**. C'est pour cela que le
/// filtre est porté par l'état et repassé à chaque page, plutôt que capturé
/// dans une fermeture au premier appel.
class CatalogController extends Notifier<EtatDuCatalogue> {
  @override
  EtatDuCatalogue build() {
    // Le premier chargement part au prochain tour de boucle : `build` doit
    // rendre un etat synchrone, et l'ecran affiche ses squelettes en attendant.
    Future<void>.microtask(charger);
    return const EtatDuCatalogue.initial();
  }

  CourseRepository get _depot => ref.read(courseRepositoryProvider);

  Future<void> charger() async {
    state = state.copyWith(
      chargement: true,
      effacerErreur: true,
      effacerCurseur: true,
    );
    try {
      final CatalogueLu lu = await _depot.catalogue(state.filtre);
      // Garde-fou : ce provider est `autoDispose`, et l'ecran peut avoir ete
      // quitte pendant l'aller-retour reseau.
      if (!ref.mounted) {
        return;
      }
      state = state.copyWith(
        cours: lu.page.cours,
        chargement: false,
        curseurSuivant: lu.page.curseurSuivant,
        effacerCurseur: lu.page.curseurSuivant == null,
        aPlus: lu.page.aPlus,
        vientDuCache: lu.vientDuCache,
        lueLe: lu.lueLe,
      );
    } on Object catch (erreur) {
      if (!ref.mounted) {
        return;
      }
      state = state.copyWith(
        chargement: false,
        cours: const <CourseSummary>[],
        messageDErreur: _message(erreur),
      );
    }
  }

  /// Change le niveau et **recharge depuis le serveur**.
  ///
  /// Le curseur est jeté : il désigne une position dans l'ordre de l'ancienne
  /// liste, et le réutiliser rendrait une page qui n'a plus de rapport.
  Future<void> choisirNiveau(NiveauCours? niveau) async {
    state = state.copyWith(
      filtre: FiltreCatalogue(
        niveau: niveau,
        recherche: state.filtre.recherche,
      ),
    );
    await charger();
  }

  Future<void> chercher(String texte) async {
    state = state.copyWith(
      filtre: FiltreCatalogue(niveau: state.filtre.niveau, recherche: texte),
    );
    await charger();
  }

  /// La page suivante. **Elle exige le réseau** : hors ligne, il n'y a pas de
  /// page suivante, et l'état le dit en n'annonçant plus de curseur.
  Future<void> pageSuivante() async {
    final String? curseur = state.curseurSuivant;
    if (curseur == null || state.chargementDeSuite || !state.aPlus) {
      return;
    }
    state = state.copyWith(chargementDeSuite: true);
    try {
      final PageDeCatalogue page = await _depot.pageSuivante(
        state.filtre,
        curseur,
      );
      if (!ref.mounted) {
        return;
      }
      state = state.copyWith(
        cours: <CourseSummary>[...state.cours, ...page.cours],
        chargementDeSuite: false,
        curseurSuivant: page.curseurSuivant,
        effacerCurseur: page.curseurSuivant == null,
        aPlus: page.aPlus,
      );
    } on Object {
      // Une page suivante qui echoue n'efface pas ce qui est déjà a l'ecran :
      // on arrete simplement de proposer la suite.
      if (ref.mounted) {
        state = state.copyWith(chargementDeSuite: false, aPlus: false);
      }
    }
  }

  static String _message(Object erreur) {
    final String texte = '$erreur';
    return texte.isEmpty ? 'Le catalogue n a pas pu être charge.' : texte;
  }
}

/// POURQUOI `autoDispose` est **explicite** : Riverpod 3 garde les providers en
/// vie par défaut, alors que la règle du chantier est l'inverse — « un
/// `autoDispose` par défaut, une exception motivée » (§1.3). « Un provider qui
/// survit à son écran garde en mémoire des données de compte ; sur un téléphone
/// partagé — le cas ordinaire au Bénin — c'est une fuite. »
final NotifierProvider<CatalogController, EtatDuCatalogue> catalogProvider =
    NotifierProvider.autoDispose<CatalogController, EtatDuCatalogue>(
      CatalogController.new,
    );

/// Mes inscriptions — `enrollmentProvider` du simulateur.
///
/// Sert la carte « Votre progression » : « `<n>` terminé(s) · `<m>` en cours ».
/// Ces deux nombres sont **comptés** sur ce que le serveur rend, pas estimés.
final FutureProvider<List<CourseEnrollment>> enrollmentProvider =
    FutureProvider.autoDispose<List<CourseEnrollment>>(
      (Ref ref) => ref.watch(courseRepositoryProvider).mesInscriptions(),
    );

/// « Reprendre où j'en étais ».
///
/// **Rend `null` quand il n'y a rien à reprendre**, et ce n'est pas une erreur :
/// c'est l'état vide. Un client qui traiterait `null` comme un échec afficherait
/// une erreur sur un compte neuf.
final FutureProvider<CourseResume?> resumeProvider =
    FutureProvider.autoDispose<CourseResume?>(
      (Ref ref) => ref.watch(courseRepositoryProvider).reprendre(),
    );
