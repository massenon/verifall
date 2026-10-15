import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../domain/entities/quiz_summary.dart';
import '../../../../domain/repositories/quiz_repository.dart';
import '../../../../domain/usecases/lms/machine_du_quiz.dart';
import 'course_provider.dart';
import 'lms_providers.dart';

/// `quizControllerProvider` — la machine à états du quiz.
///
/// # Trois règles qui ne se négocient pas
///
/// 1. **La correction reste serveur.** Aucune bonne réponse n'existe dans ce
///    fichier, ni dans le paquet installable. Le corrigé arrive une seule fois,
///    avec la copie.
/// 2. **Le chronomètre s'appuie sur `meta.commence_le`.** L'horloge du téléphone
///    ne sert qu'à mesurer un écart depuis cette heure-là.
/// 3. **Le résultat ne vient jamais du cache.** Il n'y a aucune table pour lui,
///    et ce contrôleur n'en lit aucune.
class QuizController extends Notifier<EtatDuQuiz> {
  @override
  EtatDuQuiz build() => const EtatDuQuiz.chargement();

  QuizRepository get _depot => ref.read(quizRepositoryProvider);

  /// Charge le quiz. **Exige le réseau** — et l'écran le dit avant de commencer.
  Future<void> charger(String coursSlug) async {
    state = const EtatDuQuiz.chargement();
    try {
      final QuizDetail quiz = await _depot.charger(coursSlug);
      if (!ref.mounted) {
        return;
      }
      state = EtatDuQuiz(
        phase: PhaseDuQuiz.question,
        quiz: quiz,
        reponses: const <String, int>{},
        indexCourant: 0,
      );
    } on Failure catch (echec) {
      if (!ref.mounted) {
        return;
      }
      state = state.copyWith(
        phase: PhaseDuQuiz.echec,
        messageDErreur: echec.serverMessage ?? _messageReseau(echec),
      );
    }
  }

  /// Enregistre un choix. **Aucune marque juste/faux n'est posée** : le client
  /// ne sait pas, et il ne doit pas savoir (réserve C.1).
  void repondre(String questionUuid, int reponseId) {
    if (state.phase != PhaseDuQuiz.question) {
      return;
    }
    state = state.copyWith(
      reponses: <String, int>{...state.reponses, questionUuid: reponseId},
    );
  }

  void questionSuivante() {
    if (state.indexCourant + 1 < state.questions.length) {
      state = state.copyWith(indexCourant: state.indexCourant + 1);
    }
  }

  void questionPrecedente() {
    if (state.indexCourant > 0) {
      state = state.copyWith(indexCourant: state.indexCourant - 1);
    }
  }

  /// Rend la copie.
  ///
  /// [tempsEcoule] marque une copie rendue par expiration du chronomètre. Le
  /// contrat accepte alors un tableau **vide** : « c'est la copie de quelqu'un
  /// dont le chronomètre est arrivé au bout », et une question sans réponse
  /// compte comme fausse.
  Future<void> rendreLaCopie({
    required String coursSlug,
    bool tempsEcoule = false,
  }) async {
    final QuizDetail? quiz = state.quiz;
    if (quiz == null || state.phase == PhaseDuQuiz.envoi) {
      return;
    }
    state = state.copyWith(
      phase: PhaseDuQuiz.envoi,
      tempsEcouleAvantEnvoi: tempsEcoule,
    );
    try {
      final IssueDeCopie issue = await _depot.rendreLaCopie(
        quizUuid: quiz.quiz.uuid,
        reponses: state.reponses,
      );
      if (!ref.mounted) {
        return;
      }
      switch (issue) {
        case CopieCorrigee(:final resultat):
          state = state.copyWith(
            phase: PhaseDuQuiz.resultat,
            resultat: resultat,
          );
          // Le certificat est emis PAR LE SERVEUR quand toutes les lecons sont
          // faites et le quiz reussi. On relit ; on ne le fabrique pas.
          ref.invalidate(courseProvider(coursSlug));
          ref.invalidate(certificatDuCoursProvider(coursSlug));
        case CopieEnFile(:final cleIdempotence):
          state = state.copyWith(
            phase: PhaseDuQuiz.enAttenteDeReseau,
            cleEnFile: cleIdempotence,
          );
      }
    } on Failure catch (echec) {
      if (!ref.mounted) {
        return;
      }
      state = state.copyWith(
        phase: PhaseDuQuiz.echec,
        messageDErreur: echec.serverMessage ?? _messageReseau(echec),
      );
    }
  }

  /// Recommencer. « Les tentatives sont volontairement multiples » (arbitrage
  /// n° 40) : le bouton **Réessayer** est un vrai parcours, pas une faveur.
  ///
  /// Recharger relance `GET /courses/{slug}/quiz`, donc **un nouveau
  /// `meta.commence_le`** : c'est bien une nouvelle épreuve chronométrée.
  Future<void> recommencer(String coursSlug) => charger(coursSlug);

  static String _messageReseau(Failure echec) => switch (echec) {
    NetworkFailure() =>
      'Le quiz a besoin d une connexion : la correction est faite par le serveur.',
    _ => 'Le quiz n a pas pu être charge.',
  };
}

/// POURQUOI `autoDispose` compte particulièrement ici : l'état d'un quiz porte
/// les réponses de quelqu'un. Le laisser survivre à l'écran le rendrait visible
/// à la personne suivante sur un téléphone partagé.
final NotifierProvider<QuizController, EtatDuQuiz> quizControllerProvider =
    NotifierProvider.autoDispose<QuizController, EtatDuQuiz>(
      QuizController.new,
    );
