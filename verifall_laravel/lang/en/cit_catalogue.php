<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: course catalogue
|--------------------------------------------------------------------------
|
| Two strings are NOT translated, for two different reasons.
|
| `evidence_nbsp_...` is already in English in the French file. It is the
| four-word spine of the teaching method, used as a fixed device across the
| Academy, and it reads the same in both languages.
|
| `close` and the `tune` ligature are ICON IDENTIFIERS. Translating them would
| leave an empty square where the icon belongs.
|
*/

return [

    'filtrer_par_langue' => 'Filter by language',
    'langue_toutes' => 'All languages',
    'langue' => ['fr' => 'French', 'en' => 'English'],
    'catalogue_de_cours' => 'Course catalogue',

    'developpez_vos_competences_de_verification_et_devenez' => 'Build your checking skills and get better at telling reliable information apart.',

    'evidence_nbsp_nbsp_sources_nbsp_nbsp_context' => '✓ Evidence  ·  ✓ Sources  ·  ✓ Context  ·  ✓ Verification',

    'votre_progression' => 'Your progress',
    'continuer' => 'Continue',
    'rechercher_un_cours' => 'Search for a course',

    'tune_filtres' => '<span class="material-symbols-rounded !text-[19px]" aria-hidden="true">tune</span> Filters',

    'recommande_pour_vous' => 'Recommended for you',
    'les_mieux_notes_parmi_les_cours_que' => 'The best rated among the courses you have not started yet.',

    'tous_les_cours' => 'All courses',
    'aucun_cours_trouve' => 'No course found',
    'reinitialiser_les_filtres' => 'Clear the filters',

    'filtres' => 'Filters',
    'close' => 'close',
    'niveau' => 'Level',
    'prix' => 'Price',
    'reinitialiser' => 'Reset',
    'appliquer' => 'Apply',

    'fil_d_ariane' => 'Breadcrumb',
    'explorer_les_cours' => 'Browse the courses',

    // The French view built this plural with a ternary on the count, which no
    // translation could follow. It is a choice string now.
    'cours_termine_pluriel' => 'course completed|courses completed',
    'en_cours' => 'in progress',

    'continuer_mon_apprentissage' => 'Continue learning',

    'categories_de_cours' => 'Course categories',
    'tous' => 'All',

    'rechercher_un_cours_une_competence' => 'Search for a course or a skill…',

    'filtrer_par_niveau' => 'Filter by level',
    'niveau_tous' => 'Level: all',
    'filtrer_par_prix' => 'Filter by price',
    'prix_tous' => 'Price: all',
    'gratuit' => 'Free',
    'premium' => 'Premium',

    'essayez_une_autre_recherche_ou_reinitialisez' => 'Try another search, or clear your filters.',
    'le_catalogue_s_enrichit_revenez_bientot' => 'The catalogue is growing; come back soon.',

    'filtres_des_cours' => 'Course filters',
    'fermer' => 'Close',
];
