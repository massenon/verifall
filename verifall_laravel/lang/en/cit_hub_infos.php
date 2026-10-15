<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: the news and opportunities hub
|--------------------------------------------------------------------------
|
| ── "AND NEVER ANY APPLICATION FEE" ──────────────────────────────────────
|
| That clause is not a selling point, it is a fraud test. Charging a fee to
| apply is the single most reliable marker of a fake scholarship or job offer
| in the region this platform serves. It is stated in the same breath as the
| three checks (identified organisation, official channel, verified contact
| details) because a reader who remembers only one thing should remember that
| one. The English keeps it in the same position, at the end, where it lands.
|
| The criteria are written as things that were CHECKED, not as promises. That
| is the difference between "we verified the organiser" and "this offer is
| safe", and only the first is something the platform can actually stand
| behind.
|
*/

return [
    'hub_opportunites' => 'Opportunities hub',

    'l_actualite_passee_au_crible_et_les' => 'The news put through the sieve, and the opportunities whose organiser has been checked.',

    'rechercher_une_actualite_une_opportunite' => 'Search for a news item or an opportunity',

    'a_la_une' => 'Front page',
    'l_analyse_du_jour' => 'Today\'s analysis',

    'aucune_actualite_trouvee' => 'No news item found',
    'reinitialiser_les_filtres' => 'Clear the filters',

    'opportunites_verifiees' => 'Checked opportunities',
    'opportunites_benin' => 'Benin opportunities',

    'organisation_confirmee_canal_officiel_coordonnees_verifiees_' => 'Organisation confirmed, official channel, contact details verified, and never any application fee.',

    'hors_du_benin' => 'Outside Benin',
    'opportunites_internationales' => 'International opportunities',

    'bourses_conferences_financements_et_postes_hors_du' => 'Scholarships, conferences, funding and posts outside Benin, checked in the same way.',

    'une_information_vous_semble_douteuse' => 'Does something look doubtful to you?',

    'ne_la_partagez_pas_avant_de_l' => 'Do not share it before you have checked it. The Vérif\'All laboratory cross-checks the sources and documents its verdict.',

    'pourquoi_faire_confiance_a_ces_informations' => 'Why trust this information?',

    'les_contenus_presentes_dans_ce_hub_sont' => 'The content in this hub is selected, checked or referenced against the Vérif\'All trust criteria: an identifiable primary source, a confirmed official channel, and documented cross-checking.',

    /*
     | Second pass: the tabs and filter buttons, skipped by the extraction
     | because their tag carries a `wire:click` and a computed class.
     |
     | The search field has its own key: the screen-reader label ends in a full
     | stop, the typing hint in an ellipsis. Merging them would change how the
     | prototype renders.
     */
    'fil_d_ariane' => 'Breadcrumb',
    'rechercher_une_actualite_une_opportunite_indice' => 'Search for a news item or an opportunity…',
    'sections_du_hub' => 'Hub sections',
    'actualites' => 'News',
    'categories_d_actualites' => 'News categories',
    'nature_des_opportunites' => 'Kind of opportunity',
    'tout' => 'All',
    'toutes' => 'All',
    'lire_l_analyse' => 'Read the analysis',
    'lire_l_article' => 'Read the article',
    'verifier_une_information' => 'Check a claim',
    'comprendre_notre_methode' => 'Understand our method',

    /*
     | The two empty states were a French ternary inside a `{{ }}`: invisible
     | to an extraction that only reads the text between tags. The message
     | changes depending on whether a filter is active — telling someone who
     | has just filtered to "come back later" would be wrong.
     */
    'essayez_une_autre_recherche_ou_modifiez_vos' => 'Try another search, or change your filters.',
    'le_hub_s_enrichit_chaque_semaine' => 'The hub grows every week.',
];
