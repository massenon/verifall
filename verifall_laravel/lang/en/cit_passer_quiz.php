<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: taking a quiz
|--------------------------------------------------------------------------
|
| "Not yet" rather than "Failed". The course FAQ promises that a quiz can be
| retaken as often as needed and that the point is to learn; a screen that
| answered "Failed" would contradict that promise at the only moment it
| actually matters.
|
| The timer rule is stated BEFORE the time runs out, never after: unanswered
| questions count as wrong. A rule discovered at the moment it costs you is
| not a rule, it is a trap.
|
| An empty quiz asks to be reported, and says why: it should not have been
| published. The reader is not left thinking they broke something.
|
*/

return [
    'quiz_deja_reussi' => 'Quiz already passed',
    'vous_avez_valide_une_reussite_n_est' => 'You have passed « :titre ». A pass is recorded only once — retaking the quiz earns no extra XP.',
    'retour_au_cours' => 'Back to the course',
    'refaire_pour_reviser' => 'Retake it to revise',

    'quiz_reussi' => 'Quiz passed!',
    'pas_encore' => 'Not yet',
    'seuil_de_reussite' => 'pass mark',
    'voir_mon_certificat' => 'View my certificate',
    'reessayer' => 'Try again',
    'correction' => 'Answers',
    'sans_reponse' => 'No answer',
    'bonne_reponse' => 'Correct answer:',
    'question' => 'Question',
    'reponses_proposees' => 'Available answers',
    'precedent' => 'Previous',
    'soumettre_le_quiz' => 'Submit the quiz',
    'question_suivante' => 'Next question',

    'a_la_fin_du_temps_le_quiz' => 'When the time is up, the quiz is marked as it stands: unanswered questions count as wrong.',

    'ce_quiz_ne_contient_aucune_question' => 'This quiz has no questions',
    'signalez_le_un_quiz_vide_ne_devrait' => 'Please report it: an empty quiz should not have been published.',

    /*
     | Écran de blocage du quiz (lot 1, B-05). Il remplace deux `abort(403)`
     | nus : la règle qu'ils appliquaient est juste, mais elle ne se disait pas.
     */
    'fil_d_ariane' => 'Breadcrumb',
    'quiz_pas_encore_ouvert' => 'This quiz is not open yet',
    'quiz_lecons_a_terminer' => 'It checks what the course teaches: it opens once every lesson has been read.',
    'quiz_prochaine_lecon' => 'Still to read:',
    'quiz_reprendre_la_lecon' => 'Resume this lesson',
    'quiz_reserve_aux_inscrits' => 'This quiz belongs to a course',
    'quiz_inscrivez_vous_au_cours' => 'Enrol in the course to follow its lessons and take its quizzes.',
    'quiz_voir_le_cours' => 'View the course',
];
