<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page: Our Team
|--------------------------------------------------------------------------
|
| ── PEOPLE'S NAMES ARE NEVER TRANSLATED ──────────────────────────────────
|
| They appear in this file because the extraction picked up every text-bearing
| element, and they are kept IDENTICAL to the French. That is deliberate and
| it is written down here so that nobody ever "translates" a person's name,
| adapts its spelling, or drops an accent: a name is not a word.
|
| The same applies to the middle dot separating the roles. It is not a dash
| and it needs no adaptation.
|
*/

return [
    'fil_d_ariane' => 'Breadcrumb',
    'accueil' => 'Home',

    // One label for all eleven portraits: the name arrives as a parameter from
    // its own key, so it stays untouched in either language.
    'photo_a_venir' => 'Photograph of :nom, coming soon',

    // Role shown under each portrait. "fondateur" and "formation" already exist
    // further down as section headings and are reused rather than duplicated.
    'technique' => 'Engineering',
    'communication' => 'Communications',
    'design' => 'Design',
    'juridique' => 'Legal',
    'international' => 'International',
    'conseil_appui' => 'Advice & Support',

    'citation_du_fondateur' => '“Vérif\'All was born from a simple observation: by the time a rumour reaches a citizen, it is already too late for a conventional denial. The checking tool had to be placed directly in everyone\'s hands. The WSIS Prize 2026 rewards that conviction, but our real reward is every share that never happens.”',
    'portrait_du_fondateur' => 'Portrait of René Fadonougbo, founder of Vérif\'All',
    'nous_contacter' => 'Contact us',

    'notre_equipe' => 'Our Team',

    'journalistes_fact_checkeurs_ingenieurs_et_pedagogues_les' => 'Journalists, fact-checkers, engineers and teachers: the faces behind the compass.',

    'fondateur' => 'Founder',

    // Noms propres : identiques au français, par principe.
    'rene_fadonougbo' => 'René Fadonougbo',

    'technique_communication_design' => 'Engineering · Communications · Design',

    'rhodes_massenon' => 'Rhodes Massenon',
    'charles_o_dague' => 'Charles O. Dague',
    'sergino_obossou' => 'Sergino Obossou',
    'kevin_sobadji' => 'Kévin Sobadji',

    'formation' => 'Training',

    'atman_bouba' => 'Atman Bouba',
    'gloria_tchidime' => 'Gloria Tchidime',
    'emmanuel_codjo' => 'Emmanuel Codjo',

    // Rendue par {{ }}, donc deja echappee a l'affichage : une entite ecrite
    // ici ressortait telle quelle a l'ecran (« Advice &amp; Support »).
    // Le FR portait deja un « & » nu ; l'EN s'aligne.
    'juridique_international_conseil_appui' => 'Legal · International · Advice & Support',

    'romaric_zinsou' => 'Romaric Zinsou',
    'souad_adechoubou' => 'Souad Adechoubou',
    'herbert_ebo' => 'Herbert Ebo',

    'le_mot_du_fondateur' => 'A word from the founder',

    'rene_fadonougbo_fondateur_directeur_de_la_publication' => 'René Fadonougbo <span class="font-normal text-sm text-body/70">Founder, Director of publication</span>',

    'envie_de_rejoindre_la_mission' => 'Would you like to join the mission?',

    'nous_recherchons_regulierement_des_mentors_fact_checkeurs' => 'We are regularly looking for fact-checking mentors and community relays across the departments.',
];
