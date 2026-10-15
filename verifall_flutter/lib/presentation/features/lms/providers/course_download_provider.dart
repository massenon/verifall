import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
// Les types de FAMILLE ne sont pas exportes par l'entree principale de
// Riverpod 3 ; ils vivent dans `misc.dart`. On les importe plutot que de
// laisser l'inference decider : un type ecrit est un type qu'on peut relire.
import 'package:flutter_riverpod/misc.dart';

import '../../../../domain/repositories/course_repository.dart';
import '../../../../domain/usecases/lms/telecharger_cours.dart';
import 'course_provider.dart';
import 'lms_providers.dart';

/// L'état de téléchargement d'un cours, tel que la fiche l'affiche.
@immutable
class EtatDeTelechargementDuCours {
  const EtatDeTelechargementDuCours({
    required this.enCours,
    required this.progression,
    required this.octetsStockes,
    this.telechargeLe,
  });

  const EtatDeTelechargementDuCours.repos()
    : this(enCours: false, progression: null, octetsStockes: 0);

  final bool enCours;
  final EtatTelechargement? progression;

  /// Le poids **mesuré** de ce qui est stocké. Zéro tant que rien ne l'est.
  final int octetsStockes;

  final DateTime? telechargeLe;

  bool get estTelecharge => telechargeLe != null;

  EtatDeTelechargementDuCours copyWith({
    bool? enCours,
    EtatTelechargement? progression,
    int? octetsStockes,
    DateTime? telechargeLe,
    bool effacerTelechargement = false,
  }) {
    return EtatDeTelechargementDuCours(
      enCours: enCours ?? this.enCours,
      progression: progression ?? this.progression,
      octetsStockes: octetsStockes ?? this.octetsStockes,
      telechargeLe: effacerTelechargement
          ? null
          : (telechargeLe ?? this.telechargeLe),
    );
  }
}

/// Le téléchargement d'un cours pour la lecture hors ligne.
///
/// # Ce que l'écran promet, et ce qu'il ne promet pas
///
/// Il promet : **ce qui va être téléchargé** (les leçons que le serveur a
/// ouvertes, en texte), un compteur pendant, un total mesuré après, et
/// **l'annulation à tout moment**.
///
/// Il ne promet **pas** une taille annoncée d'avance : l'API ne donne le poids
/// d'aucune leçon, et annoncer « environ 2 Mo » serait exactement le chiffre
/// inventé que la règle §2 du chantier interdit. La levée de cette réserve est
/// un **ajout compatible v1** — un `meta.taille_octets` sur la liste des leçons.
///
/// Il ne télécharge **pas les vidéos** : le contrat ne rend qu'une `video_url`,
/// sans poids ni durée de validité. Rapatrier une vidéo sur un forfait facturé
/// au mégaoctet sans pouvoir en annoncer la taille est exactement ce que §5.11
/// appelle « une facture ».
class TelechargementController extends Notifier<EtatDeTelechargementDuCours> {
  TelechargementController(this.slug);

  /// POURQUOI l'argument est un champ du contrôleur : dans Riverpod 3, une
  /// famille de `Notifier` passe son argument à la **fabrique**, pas à `build`.
  final String slug;

  bool _annulationDemandee = false;

  CourseRepository get _depot => ref.read(courseRepositoryProvider);

  @override
  EtatDeTelechargementDuCours build() {
    Future<void>.microtask(rafraichir);
    return const EtatDeTelechargementDuCours.repos();
  }

  /// Relit ce qui est **réellement** stocké.
  Future<void> rafraichir() async {
    final int octets = await _depot.octetsStockes(slug);
    DateTime? quand;
    try {
      quand = (await _depot.fiche(slug)).telechargeLe;
    } on Object {
      // Ni reseau ni cache : le cours n'est pas telecharge, point. On n'invente
      // pas une date.
      quand = null;
    }
    if (!ref.mounted) {
      return;
    }
    state = state.copyWith(octetsStockes: octets, telechargeLe: quand);
  }

  Future<void> telecharger() async {
    if (state.enCours) {
      return;
    }
    _annulationDemandee = false;
    state = state.copyWith(enCours: true);

    final EtatTelechargement fin = await ref
        .read(telechargerCoursProvider)
        .executer(
          slug: slug,
          onProgres: (EtatTelechargement etat) {
            if (ref.mounted) {
              state = state.copyWith(enCours: true, progression: etat);
            }
          },
          estAnnule: () => _annulationDemandee,
        );

    if (fin.termine) {
      await _depot.marquerTelecharge(slug);
    }

    if (!ref.mounted) {
      return;
    }
    state = EtatDeTelechargementDuCours(
      enCours: false,
      progression: fin,
      // La mesure REELLE remplace le compteur affiche pendant : c'est celle-la
      // qui est un fait.
      octetsStockes: await _depot.octetsStockes(slug),
      telechargeLe: fin.termine ? DateTime.now() : state.telechargeLe,
    );
    ref.invalidate(courseProvider(slug));
  }

  /// « Le téléchargement est visible, chiffré en taille, et **annulable**. »
  void annuler() => _annulationDemandee = true;

  /// « Un cours téléchargé doit pouvoir se supprimer. »
  Future<void> supprimer() async {
    await _depot.supprimerTelechargement(slug);
    if (!ref.mounted) {
      return;
    }
    state = const EtatDeTelechargementDuCours.repos();
    ref.invalidate(courseProvider(slug));
  }
}

final NotifierProviderFamily<
  TelechargementController,
  EtatDeTelechargementDuCours,
  String
>
telechargementProvider = NotifierProvider.autoDispose
    .family<TelechargementController, EtatDeTelechargementDuCours, String>(
      TelechargementController.new,
    );
