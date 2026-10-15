<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: live classes
|--------------------------------------------------------------------------
|
| "Live Classes" is the name the platform gives these sessions and is already
| English in the French file: it stays as it is in both.
|
| The joining dialogue shows the Zoom link rather than opening it directly.
| The button says what it does, in that order: copy, then join.
|
*/

return [
    'live_classes' => 'Live Classes',

    'vos_classes_zoom_en_direct_avec_les' => 'Your live Zoom classes with the Vérif\'All mentors: ask your questions, go deeper into the courses.',

    'a_venir' => 'Upcoming',
    'en_direct' => 'Live now',
    'terminees' => 'Finished',

    'rejoindre_la_classe' => 'Join the class',
    'vous_allez_rejoindre' => 'You are about to join:',
    'lien_zoom_de_la_session' => 'Zoom link for the session',

    'annuler' => 'Cancel',
    'copier_le_lien_et_rejoindre' => 'Copy the link and join',

    /*
     | Second pass: the breadcrumb and the three empty states, which travelled
     | as an attribute of the `x-live-class.vide` component and so escaped
     | extraction.
     |
     | The empty states promise nothing about notifications: there is no
     | notification store, and the bell in the top bar is decorative.
     */
    'fil_d_ariane' => 'Breadcrumb',
    'aucune_classe_planifiee_pour_le_moment_les' => 'No class scheduled yet. Upcoming dates are announced on this page.',
    'aucune_classe_en_direct_actuellement' => 'No class is live right now.',
    'aucune_classe_terminee' => 'No past class.',

    /*
     | Rediffusion des Live Classes (lot 1, B-06).
     */
    'rediffusion' => 'Recording',
    'rediffusion_indisponible' => 'No recording for this session',
    'rediffusion_pas_toutes_enregistrees' => 'Not every session is recorded. This one has no replay.',
    'rediffusion_voir_les_seances' => 'View sessions',
    'rediffusion_retour_au_cours' => 'Back to the course',
    'rediffusion_reservee' => 'Recording reserved for enrolled learners',
    'rediffusion_inscrivez_vous' => 'This session belongs to a course. Enrol to watch it again.',
    'rediffusion_voir_le_cours' => 'View the course',
    'rediffusion_au_programme' => 'What it covers',
];
