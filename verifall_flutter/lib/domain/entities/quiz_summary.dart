import 'package:flutter/foundation.dart';

/// Une réponse proposée — `data.questions[].reponses[]`.
///
/// [id] est un **entier**, et c'est **la seule exception** du contrat à la règle
/// « aucun entier auto-incrémenté » (§4) : « la table n'a pas d'uuid, et il
/// n'énumère rien d'utile puisqu'une réponse n'existe que dans la question qui
/// la porte, servie seulement à qui est inscrit au cours ».
@immutable
class QuizAnswer {
  const QuizAnswer({
    required this.id,
    required this.position,
    required this.libelle,
  });

  final int id;
  final int position;
  final String libelle;
}

/// Une question — **sans corrigé**.
///
/// Le contrat range `is_correct` et l'explication parmi ce qui n'est jamais
/// exposé avant la copie rendue (§9, arbitrage n° 74) : « un quiz réussi avec
/// un intercepteur de trafic dévalue tous les certificats ». Ce type n'a donc
/// **aucun champ** pour la bonne réponse — pas même optionnel : un champ
/// optionnel finit par être rempli.
@immutable
class QuizQuestion {
  const QuizQuestion({
    required this.uuid,
    required this.position,
    required this.intitule,
    required this.reponses,
  });

  /// `data.questions[].id` porte l'**uuid** de la question — c'est par lui que
  /// les réponses sont indexées à l'envoi : « une position se décale, un uuid
  /// non ».
  final String uuid;

  final int position;
  final String intitule;
  final List<QuizAnswer> reponses;
}

/// Le quiz d'un cours, tel que la fiche du cours le liste (sans questions).
@immutable
class QuizSummary {
  const QuizSummary({
    required this.uuid,
    required this.titre,
    required this.minutes,
    required this.scoreRequis,
    required this.xp,
  });

  final String uuid;
  final String titre;
  final int minutes;

  /// `score_requis` — le seuil de réussite, en pourcentage.
  final int scoreRequis;

  /// `xp` — l'expérience créditée à la réussite. **Un fait serveur** : le
  /// client l'affiche, il ne l'additionne pas.
  final int xp;
}

/// Le quiz **tel qu'on le passe** — `GET /courses/{slug}/quiz`.
@immutable
class QuizDetail {
  const QuizDetail({
    required this.quiz,
    required this.questions,
    required this.commenceLe,
  });

  final QuizSummary quiz;
  final List<QuizQuestion> questions;

  /// `meta.commence_le` — « l'heure qui fait foi, pas celle du téléphone ».
  ///
  /// POURQUOI elle ne peut pas être `null` en pratique et l'est en type : le
  /// contrat la pose systématiquement, mais un client qui plante sur son
  /// absence serait un client qu'on ne peut plus faire évoluer. Sans elle, le
  /// chronomètre ne s'affiche pas — il ne se replie pas sur l'horloge locale.
  final DateTime? commenceLe;

  /// L'instant où la copie doit être rendue, calculé depuis l'heure du
  /// **serveur** et la durée du quiz.
  DateTime? get echeance => commenceLe?.add(Duration(minutes: quiz.minutes));
}
