<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, passer-quiz
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
    'quiz_deja_reussi' => 'Quiz déjà réussi',
    'vous_avez_valide_une_reussite_n_est' => 'Vous avez validé « :titre ». Une réussite n\'est enregistrée qu\'une fois — repasser le quiz ne rapporte pas d\'XP supplémentaire.',
    'retour_au_cours' => 'Retour au cours',
    'refaire_pour_reviser' => 'Refaire pour réviser',
    'quiz_reussi' => 'Quiz réussi !',
    'pas_encore' => 'Pas encore',
    'seuil_de_reussite' => 'seuil de réussite',
    'voir_mon_certificat' => 'Voir mon certificat',
    'reessayer' => 'Réessayer',
    'correction' => 'Correction',
    'sans_reponse' => 'Sans réponse',
    'bonne_reponse' => 'Bonne réponse :',
    'question' => 'Question',
    'reponses_proposees' => 'Réponses proposées',
    'precedent' => 'Précédent',
    'soumettre_le_quiz' => 'Soumettre le quiz',
    'question_suivante' => 'Question suivante',
    'a_la_fin_du_temps_le_quiz' => 'À la fin du temps, le quiz est corrigé tel quel : les questions sans réponse comptent comme fausses.',
    'ce_quiz_ne_contient_aucune_question' => 'Ce quiz ne contient aucune question',
    'signalez_le_un_quiz_vide_ne_devrait' => 'Signalez-le : un quiz vide ne devrait pas être publié.',

    /*
     | Écran de blocage du quiz (lot 1, B-05). Il remplace deux `abort(403)`
     | nus : la règle qu'ils appliquaient est juste, mais elle ne se disait pas.
     */
    'fil_d_ariane' => 'Fil d\'Ariane',
    'quiz_pas_encore_ouvert' => 'Ce quiz n\'est pas encore ouvert',
    'quiz_lecons_a_terminer' => 'Il vérifie ce que le cours enseigne : il s\'ouvre une fois toutes les leçons lues.',
    'quiz_prochaine_lecon' => 'Il vous reste à lire :',
    'quiz_reprendre_la_lecon' => 'Reprendre cette leçon',
    'quiz_reserve_aux_inscrits' => 'Ce quiz fait partie d\'un cours',
    'quiz_inscrivez_vous_au_cours' => 'Inscrivez-vous au cours pour suivre ses leçons et passer ses quiz.',
    'quiz_voir_le_cours' => 'Voir le cours',
];
