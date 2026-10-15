<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front public, « Notre Blog »
|--------------------------------------------------------------------------
|
| Transposition de blog.html : titre et pastilles de rubriques, banniere de
| l'article a la une, puis la grille en trois colonnes.
|
| La pastille « Tous » etait restee en francais : elle porte un `wire:click`
| ET une classe dynamique, exactement le gabarit que l'extracteur sautait.
|
| Les quatre dernieres cles forment les DEUX etats du vide, en deux temps
| chacun (titre puis explication) : « aucun resultat pour ce filtre » n'est
| pas « le blog n'a pas encore commence ». Les fondre ferait passer un
| catalogue neuf pour une recherche ratee.
|
*/

return [
    'notre_blog' => 'Notre Blog',
    'tous' => 'Tous',
    'lire_l_article_complet' => 'Lire l\'article complet',
    'lire_la_suite' => 'Lire la suite',
    'pagination_du_blog' => 'Pagination du blog',
    'aucun_article_ne_correspond' => 'Aucun article ne correspond',
    'le_blog_demarre_bientot' => 'Le blog démarre bientôt',
    'essayez_une_autre_recherche_ou_une_autre' => 'Essayez une autre recherche ou une autre rubrique.',
    'nos_premiers_decryptages_arrivent_en_attendant_le' => 'Nos premiers décryptages arrivent. En attendant, le laboratoire, lui, est ouvert.',
];
