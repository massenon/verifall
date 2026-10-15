<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Les verdicts
|--------------------------------------------------------------------------
|
| QUATRE, et quatre seulement. Ils sont nommés dans les mentions légales, dans
| le balisage `ClaimReview` et sur les cartes de démenti : un cinquième mot
| apparu ici serait une promesse que la plateforme ne tient nulle part
| ailleurs.
|
| Les libellés COURTS restent en capitales à l'affichage, par le style et non
| par le texte : une chaîne écrite en capitales est lue lettre par lettre par
| certains lecteurs d'écran.
|
*/

return [
    'court' => [
        'true' => 'Vrai',
        'false' => 'Faux',
        'misleading' => 'Trompeur',
        'unverifiable' => 'Non vérifiable',
    ],

    'explication' => [
        'true' => 'Les sources concordantes confirment cette information.',
        'false' => 'Les sources consultées contredisent cette information.',
        'misleading' => "L'information contient une part de vrai, présentée de façon trompeuse.",
        'unverifiable' => 'Les éléments disponibles ne permettent pas de trancher.',
    ],
    /*
    |--------------------------------------------------------------------------
    | Le Hero du rapport
    |--------------------------------------------------------------------------
    |
    | Ces deux blocs étaient écrits EN DUR dans `rapport.blade.php`, en français
    | et sans passer par `__()` : la page de rapport annonçait « INFORMATION
    | FAUSSE » à un lecteur anglophone qui avait pourtant basculé la langue.
    | Ramenés ici le 25 septembre 2026, avec le reste du thème de verdict.
    |
    | Les libellés restent en capitales À L'AFFICHAGE, par le style — même
    | raison qu'au-dessus : une chaîne écrite en capitales est lue lettre par
    | lettre par certains lecteurs d'écran.
    |
    */
    'rapport' => [
        'libelle' => [
            'true' => 'Information fiable',
            'false' => 'Information fausse',
            'misleading' => 'Contenu douteux',
            'unverifiable' => 'Non vérifiable',
        ],

        'phrase' => [
            'true' => 'Ce contenu a été confirmé comme authentique.',
            'false' => 'Ce contenu a été identifié comme une fausse information.',
            'misleading' => 'Ce contenu mélange des éléments vrais et faux. Prudence.',
            'unverifiable' => 'Les éléments disponibles ne permettent pas de trancher.',
        ],
    ],
];
