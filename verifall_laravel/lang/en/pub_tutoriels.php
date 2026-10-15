<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Public front, "Tutorials"
|--------------------------------------------------------------------------
|
| Filter rail on the left, card grid on the right. The courses come from the
| Academy rather than from a second library maintained here.
|
| `lecons` and `unites` are PLURAL forms, served by `trans_choice()`. The view
| used to call `Str::plural('lecon', $n)`, which applies ENGLISH rules to a
| French word. It happened to be right — "lecon" + "s" — but it could never
| have produced "lesson/lessons" once the page was in English. Plurals now
| live in the language file, where each language carries its own forms.
|
*/

return [
    'filtres_avances' => 'Advanced filters',
    'categories' => 'Categories',
    'niveau' => 'Level',
    'gratuit' => 'FREE',
    'lecons' => 'lesson|lessons',
    'acces_libre' => 'Free access',
    'unites' => 'unit|units',
    's_inscrire' => 'Sign up',
    'aucun_cours_ne_correspond_a_vos_filtres' => 'No course matches your filters.',
    'l_academie_ouvre_bientot_ses_premiers_cours' => 'The Academy opens its first courses soon.',
];
