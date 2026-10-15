<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, sondage-du-sujet
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
    'sondage' => 'Sondage',
    'les_resultats_s_afficheront_une_fois_votre' => 'Les résultats s\'afficheront une fois votre réponse enregistrée : les voir avant influencerait votre choix.',
    'connectez_vous_pour_repondre_a_ce_sondage' => 'Connectez-vous pour répondre à ce sondage et en voir les résultats.',
    'poser_un_sondage' => 'Poser un sondage',
    'une_question_deux_a_six_reponses_un' => 'Une question, deux à six réponses. Un vote par personne.',
    'ajouter_un_sondage' => 'Ajouter un sondage',
    'votre_sondage' => 'Votre sondage',
    'question' => 'Question',
    'reponses_possibles' => 'Réponses possibles',
    'annuler' => 'Annuler',
    'publier_le_sondage' => 'Publier le sondage',

    // Resultats : libelles restes en dur dans les balises a attribut dynamique.
    'votre_reponse' => 'votre réponse',
    'pour_cent' => 'pour cent',
    'participant' => 'participant|participants',
    'ce_sondage_est_clos' => 'Ce sondage est clos.',
    'reponse' => 'Réponse',
];
