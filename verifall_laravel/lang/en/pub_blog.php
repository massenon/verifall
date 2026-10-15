<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Public front, "Our Blog"
|--------------------------------------------------------------------------
|
| Title and topic pills, the featured banner, then the three-column grid.
|
| The "All" pill had stayed French: it carries both a `wire:click` and a
| dynamic class — precisely the shape the extractor skipped.
|
| The last four keys are the TWO empty states, each in two parts (heading,
| then explanation): "nothing matches this filter" is not "the blog has not
| started yet". Merging them would make a brand-new catalogue read as a
| failed search, and would tell the reader to change a filter they never set.
|
*/

return [
    'notre_blog' => 'Our Blog',
    'tous' => 'All',
    'lire_l_article_complet' => 'Read the full article',
    'lire_la_suite' => 'Read more',
    'pagination_du_blog' => 'Blog pagination',
    'aucun_article_ne_correspond' => 'No article matches',
    'le_blog_demarre_bientot' => 'The blog starts soon',
    'essayez_une_autre_recherche_ou_une_autre' => 'Try another search, or another topic.',
    'nos_premiers_decryptages_arrivent_en_attendant_le' => 'Our first pieces are on their way. In the meantime, the laboratory is open.',
];
