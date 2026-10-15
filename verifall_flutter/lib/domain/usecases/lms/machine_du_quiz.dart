import 'package:flutter/foundation.dart';

import '../../entities/quiz_attempt.dart';
import '../../entities/quiz_summary.dart';

/// La phase du quiz.
///
/// POURQUOI une machine à états explicite : « les machines à états — quiz,
/// formulaire de vérification, contrôleur d'authentification — méritent
/// vraiment un test unitaire, parce que l'erreur y est silencieuse. Une
/// transition manquante ne se voit pas, elle bloque » (06-TESTS §6.3).
enum PhaseDuQuiz {
  /// Le quiz se charge. **Exige le réseau.**
  chargement,

  /// Une question à la fois.
  question,

  /// La copie part.
  envoi,

  /// Le serveur a corrigé : score, seuil, et le corrigé déroulé.
  resultat,

  /// La copie est **en file** : le résultat n'arrivera qu'au retour du réseau.
  enAttenteDeReseau,

  /// Le chargement a échoué.
  echec,
}

/// L'état complet d'un quiz en cours.
///
/// # La forme retenue par la réserve C.1
///
/// Le simulateur marque juste/faux et affiche la carte XAI **après chaque
/// réponse**. Le contrat ne le permet pas : les questions arrivent **sans
/// corrigé**, et `is_correct` comme l'explication ne franchissent le réseau
/// qu'une fois, dans `data.corrige[]` de la copie rendue — « un quiz réussi
/// avec un intercepteur de trafic dévalue tous les certificats ».
///
/// **Forme retenue** : une question à la fois, **sans marque ni XAI pendant le
/// parcours**, et le corrigé **déroulé sur l'écran de résultat**, question par
/// question, avec chaque explication. C'est l'option 1 de la réserve : elle
/// conserve l'écran de question et l'écran de résultat, et ne déplace que le
/// moment de l'explication. La pédagogie est préservée ; le moment change.
@immutable
class EtatDuQuiz {
  const EtatDuQuiz({
    required this.phase,
    required this.reponses,
    required this.indexCourant,
    this.quiz,
    this.resultat,
    this.messageDErreur,
    this.cleEnFile,
    this.tempsEcouleAvantEnvoi = false,
  });

  const EtatDuQuiz.chargement()
    : this(
        phase: PhaseDuQuiz.chargement,
        reponses: const <String, int>{},
        indexCourant: 0,
      );

  final PhaseDuQuiz phase;
  final QuizDetail? quiz;

  /// Les choix, **indexés par uuid de question** — « une position se décale, un
  /// uuid non ».
  final Map<String, int> reponses;

  final int indexCourant;
  final QuizAttemptResult? resultat;

  /// Le message **du serveur**, affiché tel quel.
  final String? messageDErreur;

  /// La clé d'idempotence de la copie mise en file.
  final String? cleEnFile;

  /// La copie est partie parce que le chronomètre est arrivé au bout.
  ///
  /// « Le contrat accepte explicitement un tableau de réponses vide : c'est la
  /// copie de quelqu'un dont le chronomètre est arrivé au bout. Une question
  /// sans réponse compte comme fausse. Le client n'invente aucun échec, il
  /// envoie ce qu'il a. »
  final bool tempsEcouleAvantEnvoi;

  List<QuizQuestion> get questions => quiz?.questions ?? const <QuizQuestion>[];

  QuizQuestion? get questionCourante =>
      indexCourant >= 0 && indexCourant < questions.length
      ? questions[indexCourant]
      : null;

  bool get estDerniereQuestion => indexCourant == questions.length - 1;

  int? get choixCourant {
    final QuizQuestion? q = questionCourante;
    return q == null ? null : reponses[q.uuid];
  }

  bool get aRepondu => choixCourant != null;

  EtatDuQuiz copyWith({
    PhaseDuQuiz? phase,
    QuizDetail? quiz,
    Map<String, int>? reponses,
    int? indexCourant,
    QuizAttemptResult? resultat,
    String? messageDErreur,
    String? cleEnFile,
    bool? tempsEcouleAvantEnvoi,
  }) {
    return EtatDuQuiz(
      phase: phase ?? this.phase,
      quiz: quiz ?? this.quiz,
      reponses: reponses ?? this.reponses,
      indexCourant: indexCourant ?? this.indexCourant,
      resultat: resultat ?? this.resultat,
      messageDErreur: messageDErreur ?? this.messageDErreur,
      cleEnFile: cleEnFile ?? this.cleEnFile,
      tempsEcouleAvantEnvoi:
          tempsEcouleAvantEnvoi ?? this.tempsEcouleAvantEnvoi,
    );
  }

  /// Le temps restant, calculé depuis **`meta.commence_le`** — l'heure du
  /// serveur, jamais celle du téléphone.
  ///
  /// L'horloge locale n'intervient que pour mesurer un **écart** ; le point de
  /// départ, lui, vient du serveur. Rendre `null` quand l'heure de départ
  /// manque est délibéré : le chronomètre ne s'affiche alors pas, plutôt que de
  /// se replier sur une horloge de téléphone qu'on sait fausse.
  Duration? tempsRestant(DateTime maintenant) {
    final DateTime? echeance = quiz?.echeance;
    if (echeance == null) {
      return null;
    }
    final Duration reste = echeance.difference(maintenant);
    return reste.isNegative ? Duration.zero : reste;
  }
}
