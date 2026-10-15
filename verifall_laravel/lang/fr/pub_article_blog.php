<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front public, un article du blog
|--------------------------------------------------------------------------
|
| Transposition de blog-details.html : chapeau, visuel, corps de texte, puis
| le carrousel « Articles similaires ».
|
| Les trois destinations de partage (Facebook, Whatsapp, Twitter) sont des
| noms de services : elles ne sont pas traduites et ne figurent donc pas ici.
| Les deux fleches du carrousel n'ont que leur `aria-label` a traduire ; le
| chevron lui-meme reste un caractere.
|
*/

return [
    'partager' => 'Partager',
    'min_de_lecture' => 'min de lecture',
    'articles_similaires' => 'Articles similaires',
    'articles_precedents' => 'Articles précédents',
    'articles_suivants' => 'Articles suivants',
    'lire' => 'Lire',
];
