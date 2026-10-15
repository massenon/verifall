<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Les types
|--------------------------------------------------------------------------
|
| Clés = valeurs d'enum. Ces valeurs servent aussi de filtres d'URL et de
| clés de configuration (`factcheck.costs.image`) : c'est le LIBELLÉ qui est
| traduit ici, jamais la valeur.
|
*/

return [

    'contenu' => [
        'text' => 'Texte',
        'url' => 'Lien URL',
        'image' => 'Image',
        'video' => 'Vidéo',
        'audio' => 'Audio',
    ],

    'lecon' => [
        'text' => 'Lecture',
        'video' => 'Vidéo',
        'audio' => 'Audio',
    ],

    /*
     | CDI et CDD sont les contrats du droit du travail béninois : les sigles
     | sont ce que les annonces emploient, et les traduire les rendrait
     | méconnaissables pour qui cherche un emploi à Cotonou.
     */
    'opportunite' => [
        'cdi' => 'CDI',
        'cdd' => 'CDD',
        'stage' => 'Stage',
        'alternance' => 'Alternance',
        'volontariat' => 'Volontariat',
        'bourse' => 'Bourse',
        'financement' => 'Financement',
        'conference' => 'Conférence',
        'appel' => 'Appel à candidature',
        'autre' => 'Autre',
    ],

    'portee_opportunite' => [
        'benin' => 'Opportunités Bénin',
        'international' => 'Opportunités Internationales',
    ],

    'campagne' => [
        'popup' => 'Fenêtre',
        'banner' => 'Bandeau',
    ],

    /*
     | « QCM », « Multi-select » et « Matching » sont les mots de la banque de
     | questions du prototype (`questions.html`), repris tels quels : les
     | franciser rendrait l'écran staff méconnaissable pour qui l'utilise.
     */
    'question' => [
        'qcm' => 'QCM',
        'true_false' => 'Vrai/Faux',
        'multi_select' => 'Multi-select',
        'short_answer' => 'Réponse courte',
        'matching' => 'Matching',
    ],
];
