import 'package:flutter/foundation.dart';

import 'course_summary.dart';
import 'lesson_summary.dart';

/// Le statut d'une inscription — `StatutInscription` côté serveur.
enum StatutInscription {
  inscrit('enrolled'),
  enCours('in_progress'),
  termine('completed'),
  inconnu('');

  const StatutInscription(this.valeurTechnique);

  final String valeurTechnique;

  static StatutInscription parse(String? raw) => switch (raw) {
    'enrolled' => inscrit,
    'in_progress' => enCours,
    'completed' => termine,
    _ => inconnu,
  };
}

/// Mon inscription à un cours — `InscriptionResource`.
@immutable
class CourseEnrollment {
  const CourseEnrollment({
    required this.uuid,
    required this.statut,
    required this.statutLibelle,
    required this.avancement,
    required this.unitesPayees,
    this.coursSlug,
    this.coursTitre,
    this.commenceeLe,
    this.derniereActiviteLe,
    this.acheveeLe,
  });

  final String uuid;
  final StatutInscription statut;

  /// Le libellé français **rédigé par le serveur**. Le client ne le fabrique
  /// pas : c'est la règle du couple valeur technique / libellé.
  final String statutLibelle;

  /// `avancement` — recalculé par le serveur après chaque événement.
  ///
  /// « Deux formules de calcul de progression finiraient par afficher deux
  /// pourcentages différents pour le même cours sur deux écrans voisins »
  /// (`InscriptionResource`). Le client ne recompte jamais.
  final int avancement;

  /// `unites_payees` — **figé à l'inscription** : le prix peut changer, ce qui
  /// a été déboursé non (arbitrage n° 40).
  final int unitesPayees;

  final String? coursSlug;
  final String? coursTitre;
  final DateTime? commenceeLe;
  final DateTime? derniereActiviteLe;
  final DateTime? acheveeLe;

  bool get estTerminee => statut == StatutInscription.termine;
}

/// « Reprendre où j'en étais » — `GET /me/courses/resume`.
///
/// L'endpoint rend **`data: null`** quand il n'y a rien à reprendre : « ce
/// n'est pas une erreur, c'est un état vide ». C'est pourquoi le dépôt rend un
/// `CourseResume?` et non une exception.
@immutable
class CourseResume {
  const CourseResume({
    required this.inscription,
    required this.cours,
    this.prochaineLecon,
  });

  final CourseEnrollment inscription;
  final CourseSummary cours;

  /// `null` quand toutes les leçons sont faites : il ne reste que le quiz.
  final LessonSummary? prochaineLecon;
}

/// Mon avancement sur un cours — `GET /me/courses/{slug}/progress`.
@immutable
class CourseProgress {
  const CourseProgress({
    required this.inscription,
    required this.avancement,
    required this.acheve,
    this.prochaineLecon,
  });

  final CourseEnrollment inscription;
  final int avancement;
  final bool acheve;
  final LessonSummary? prochaineLecon;
}
