import 'package:flutter/foundation.dart';

import 'course_enrollment.dart';
import 'course_summary.dart';
import 'lesson_summary.dart';
import 'quiz_summary.dart';

/// La fiche d'un cours — `GET /courses/{slug}`.
///
/// Elle porte les leçons **avec leur état de déverrouillage** et l'inscription
/// de la personne connectée, posée dans `meta.inscription`. Cette dernière vaut
/// `null` et non un objet aux champs à zéro : « je ne suis pas inscrit » est un
/// état, pas une inscription vide.
@immutable
class CourseDetail {
  const CourseDetail({
    required this.cours,
    required this.lecons,
    required this.quiz,
    this.inscription,
  });

  final CourseSummary cours;
  final List<LessonSummary> lecons;

  /// Les quiz du cours. Le simulateur n'en montre qu'un, le contrat en autorise
  /// plusieurs ; l'écran affiche ce qui vient.
  final List<QuizSummary> quiz;

  final CourseEnrollment? inscription;

  bool get estInscrit => inscription != null;

  /// La première leçon non acquise et ouverte — celle sur laquelle le bouton
  /// « Reprendre » doit atterrir.
  ///
  /// POURQUOI ce n'est pas un recalcul de déverrouillage : on ne décide rien,
  /// on **cherche** dans ce que le serveur a déjà tranché. La différence est
  /// exactement celle entre lire `ouverte` et le déduire.
  LessonSummary? get prochaineLecon {
    for (final LessonSummary lecon in lecons) {
      if (!lecon.estAcquise && lecon.estOuverte) {
        return lecon;
      }
    }
    return null;
  }

  /// Les leçons dont le contenu peut être demandé au serveur **aujourd'hui**.
  ///
  /// C'est la clé de la réserve C.3 : `GET /courses/{slug}/lessons/{uuid}`
  /// répond `403 droit_manquant` sur une leçon verrouillée. Un « téléchargement
  /// du cours entier » est donc impossible par construction — le serveur ne
  /// sert que ce qui est ouvert.
  List<LessonSummary> get leconsTelechargeables =>
      lecons.where((LessonSummary l) => l.estOuverte).toList(growable: false);
}
