<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: the OSINT tool directory
|--------------------------------------------------------------------------
|
| "OSINT" and "fact-checking" are terms of the trade and are not translated in
| either direction: they are what practitioners actually say.
|
| The closing line is the point of the whole page. Listing tools is easy and
| almost useless on its own; knowing how to use one is the skill. The English
| keeps that two-beat structure ("you know the tool. Now learn to use it
| properly.") because the second sentence is what sends the reader to a course.
|
*/

return [
    'fil_d_ariane' => 'Breadcrumb',
    'dashboard' => 'Dashboard',
    'outils' => 'Tools',
    'outils_osint_fact_checking' => 'Tools · OSINT · Fact-checking',
    'repertoire_des_outils' => 'Tool directory',

    'les_bons_outils_pour_enqueter_verifier_et' => 'The right tools to investigate, check and understand a piece of information before sharing it.',

    'outils_references' => ':count tool listed|:count tools listed',
    'selection_verif_all' => '● Vérif\'All selection',
    'rechercher_un_outil' => 'Search for a tool',
    'rechercher_image_domaine_metadonnees_ia' => 'Search: image, domain, metadata, AI…',
    'categories_d_outils' => 'Tool categories',
    'tous' => 'All',
    'outils_recommandes' => 'Recommended tools',
    'tous_les_outils' => 'All tools',

    'aucun_outil_trouve' => 'No tool found',
    'nous_n_avons_trouve_aucun_outil_correspondant' => 'We found no tool matching your search.<br>Try another keyword, or browse a category.',
    'effacer_la_recherche' => 'Clear the search',

    'vous_connaissez_l_outil_maintenant_apprenez_a' => 'You know the tool. Now learn to use it properly.',
    'le_cours' => 'The course',
    'vous_accompagne_etape_par_etape_dans' => 'walks you through your investigations step by step.',
    'decouvrir_le_cours' => 'Discover the course',
    'outils_externes_selectionnes_a_titre_informatif' => 'External tools listed for information only. Vérif\'All does not guarantee the results these services produce. For a documented verdict,',
    'soumettez_le_contenu_au_laboratoire' => 'submit the content to the laboratory',

    // Filter pill labels. The filter VALUES live in `Outils::CATEGORIES` and
    // are never translated: they are what the query matches on.
    'categorie' => [
        'recherche' => 'Search',
        'web_domaines' => 'Web & domains',
        'archives' => 'Archives',
        'medias' => 'Metadata',
        'ia' => 'AI',
        'geolocalisation' => 'Geolocation',
        'fact_checking' => 'Fact-checking',
    ],
];
