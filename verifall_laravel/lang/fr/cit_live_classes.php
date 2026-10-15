<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, live-classes
|--------------------------------------------------------------------------
|
| ENGENDRE depuis la vue, puis relu. La copie du prototype reste ainsi
| identique au caractere pres.
|
| Les valeurs qui portent du balisage en ligne sont rendues en syntaxe non
| echappee. Ce n'est PAS une entorse a la regle de securite : ces chaines sont
| ecrites par l'equipe, jamais par un visiteur.
|
*/

return [
    'live_classes' => 'Live Classes',
    'vos_classes_zoom_en_direct_avec_les' => 'Vos classes Zoom en direct avec les mentors Vérif\'All : posez vos questions, approfondissez les cours.',
    'a_venir' => 'À venir',
    'en_direct' => 'En direct',
    'terminees' => 'Terminées',
    'rejoindre_la_classe' => 'Rejoindre la classe',
    'vous_allez_rejoindre' => 'Vous allez rejoindre :',
    'lien_zoom_de_la_session' => 'Lien Zoom de la session',
    'annuler' => 'Annuler',
    'copier_le_lien_et_rejoindre' => 'Copier le lien et rejoindre',

    /*
     | Second passage : le fil d'Ariane et les trois etats vides, qui etaient
     | passes en attribut du composant `x-live-class.vide` et echappaient donc
     | a l'extraction.
     */
    'fil_d_ariane' => 'Fil d\'Ariane',
    'aucune_classe_planifiee_pour_le_moment_les' => 'Aucune classe planifiée pour le moment. Les prochaines dates s\'annoncent sur cette page.',
    'aucune_classe_en_direct_actuellement' => 'Aucune classe en direct actuellement.',
    'aucune_classe_terminee' => 'Aucune classe terminée.',

    /*
     | Rediffusion des Live Classes (lot 1, B-06).
     */
    'rediffusion' => 'Rediffusion',
    'rediffusion_indisponible' => 'Pas de rediffusion pour cette séance',
    'rediffusion_pas_toutes_enregistrees' => 'Toutes les séances ne sont pas enregistrées. Celle-ci n\'a pas de rediffusion.',
    'rediffusion_voir_les_seances' => 'Voir les séances',
    'rediffusion_retour_au_cours' => 'Retour au cours',
    'rediffusion_reservee' => 'Rediffusion réservée aux inscrits',
    'rediffusion_inscrivez_vous' => 'Cette séance fait partie d\'un cours. Inscrivez-vous pour la revoir.',
    'rediffusion_voir_le_cours' => 'Voir le cours',
    'rediffusion_au_programme' => 'Au programme',
];
