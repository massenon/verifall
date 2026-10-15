<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: my assessments
|--------------------------------------------------------------------------
|
| The four summary tiles are prefixed with typographic marks (✓ ◉ ↻ □) rather
| than icons. They are part of the layout the prototype fixes and are kept
| exactly, including the space that follows them.
|
| `tentatives` and `tentatives_2` are the same French word in two places: a
| summary tile and a table column. They are kept as separate keys because a
| column heading and a counter label do not have to stay identical in English.
|
*/

return [
    'mes_evaluations' => 'My assessments',

    'tous_vos_quiz_et_exercices_scores_reels' => 'All your quizzes and exercises: real scores, attempts and marking status, taken from your own progress.',

    'apercu_de_vos_evaluations_expand_more' => '<span class="text-sm font-display font-bold text-ink">Your assessments at a glance</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',

    'quiz_reussis' => '✓ Quizzes passed',
    'score_moyen' => '◉ Average score',
    'tentatives' => '↻ Attempts',
    'exercices_soumis' => '□ Exercises submitted',

    'quiz' => 'Quizzes',
    'exercices' => 'Exercises',
    'cours' => 'Course',
    'score' => 'Score',
    'tentatives_2' => 'Attempts',
    'statut' => 'Status',
    'action' => 'Action',

    'vous_n_avez_encore_passe_aucun_quiz' => 'You have not taken any quiz yet.',
    'aucun_exercice_soumis_pour_l_instant' => 'No exercise submitted yet.',

    'fil_d_ariane' => 'Breadcrumb',

    // "/ min 70 %" sits next to the score: the pass mark, not a duration.
    'slash_min' => '/ min',
    'tentative_plurielle' => 'attempt|attempts',
    'minimum' => 'minimum',

    'decouvrir_l_academie' => 'Explore the Academy',

    'disponible_apres_inscription_au_cours' => 'Available once you enrol on the course',
    'verrouille' => 'LOCKED',
    's_inscrire_au_cours' => 'Enrol on the course',
    'a_rendre' => 'TO SUBMIT',
    'rendre_l_exercice' => 'Submit the exercise',
    'voir_mes_exercices' => 'View my exercises',
];
