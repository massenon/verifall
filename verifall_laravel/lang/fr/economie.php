<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Unités et niveaux de cours
|--------------------------------------------------------------------------
|
| Les motifs sont écrits dans un journal comptable que l'on relira pour
| expliquer un solde à quelqu'un qui le conteste. Ce qui identifie la ligne
| est la VALEUR (`unit_logs.reason`) ; ces libellés ne servent qu'à la lire.
|
*/

return [

    'motif' => [
        'welcome' => 'Bonus de bienvenue',
        'recharge' => 'Recharge Mobile Money',
        'monthly_grant' => 'Dotation mensuelle',
        'verification' => 'Vérification de contenu',
        'premium_course' => 'Cours premium',
        'referral' => 'Parrainage',
        'admin_adjustment' => 'Ajustement administrateur',
        'refund' => 'Remboursement',
    ],

    /*
     | Stocké en anglais, affiché dans la langue de la requête : le filtre du
     | catalogue passe par la valeur (`?niveau=beginner`), jamais par le mot.
     */
    'niveau_cours' => [
        'beginner' => 'Débutant',
        'intermediate' => 'Intermédiaire',
        'advanced' => 'Avancé',
    ],
];
