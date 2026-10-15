<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| La modération du forum
|--------------------------------------------------------------------------
|
| Clés = valeurs d'enum. « Publier et signaler » n'est pas une variante douce
| de « Bloquer » : la distinction est le cœur du dispositif — les mots d'une
| rumeur sont précisément ceux qu'on vient signaler — et les deux textes
| doivent la garder lisible dans chaque langue.
|
*/

return [
    'action_mot_clef' => [
        'libelle' => [
            'block' => 'Bloquer',
            'flag' => 'Publier et signaler',
        ],

        'explication' => [
            'block' => "Le message est refusé à la publication. L'auteur en est averti.",
            'flag' => 'Le message paraît normalement et rejoint la file de modération.',
        ],
    ],
];
