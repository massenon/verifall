<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front public, « Tutoriels »
|--------------------------------------------------------------------------
|
| Transposition de tutoriels.html : volet de filtres a gauche, grille de
| cartes a droite. Les cours viennent de l'Academie, pas d'une seconde
| bibliotheque tenue ici.
|
| `lecons` et `unites` sont des formes de PLURIEL, servies par
| `trans_choice()`. La vue appelait `Str::plural('lecon', $n)` : cette
| fonction applique les regles de l'ANGLAIS a un mot francais. Elle tombait
| juste ici par hasard — « lecon » + « s » —, mais elle n'aurait jamais rendu
| « lesson/lessons » une fois la page en anglais. Le pluriel passe donc par le
| fichier de langue, ou chaque langue porte ses propres formes.
|
*/

return [
    'filtres_avances' => 'Filtres avancés',
    'categories' => 'Catégories',
    'niveau' => 'Niveau',
    'gratuit' => 'GRATUIT',
    'lecons' => 'leçon|leçons',
    'acces_libre' => 'Accès Libre',
    'unites' => 'unité|unités',
    's_inscrire' => 'S\'inscrire',
    'aucun_cours_ne_correspond_a_vos_filtres' => 'Aucun cours ne correspond à vos filtres.',
    'l_academie_ouvre_bientot_ses_premiers_cours' => 'L\'Académie ouvre bientôt ses premiers cours.',
];
