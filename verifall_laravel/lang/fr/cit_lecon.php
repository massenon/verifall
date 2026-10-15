<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, lecon
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
    'terminer_la_lecon' => 'Terminer la leçon',
    /*
     | Second passage : la navigation de lecon en lecon. Ces libelles portaient
     | une fleche ou une coche collee au texte, et leur balise un `wire:navigate`
     | ou une classe calculee — l'extraction les avait tous sautes.
     |
     | La fleche et la coche restent DANS la vue, pas dans la traduction : ce
     | sont des reperes visuels, ils ne changent pas d'une langue a l'autre.
     */
    'fil_d_ariane' => 'Fil d\'Ariane',
    'catalogue' => 'Catalogue',
    'lecon' => 'Leçon',
    'lecon_capitales' => 'LEÇON',
    'votre_navigateur_ne_sait_pas_lire_cette' => 'Votre navigateur ne sait pas lire cette vidéo.',
    'lecon_precedente' => 'Leçon précédente',
    'lecon_terminee' => 'Leçon terminée',
    'lecon_suivante' => 'Leçon suivante',
    'terminer_le_cours' => 'Terminer le cours',
    'temps_de_lecture_actif' => 'Temps de lecture actif',
    'le_chronometre_se_met_en_pause_si' => '— le chronomètre se met en pause si vous quittez l\'onglet.',
    'termine' => 'terminé',

    'curriculum' => 'Curriculum',
    'documents_de_la_lecon' => 'Documents de la leçon',
];
