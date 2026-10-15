<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page notre-equipe
|--------------------------------------------------------------------------
|
| ENGENDRE depuis la vue, puis relu. La copie du prototype reste ainsi
| identique au caractere pres.
|
| Les valeurs qui portent du balisage en ligne sont rendues avec la syntaxe non
| echappee. Ce n'est PAS une entorse a la regle de securite : ces chaines sont
| ecrites par l'equipe, jamais par un visiteur. La regle vise les donnees
| d'utilisateur, et elle reste entiere.
|
*/

return [
    'fil_d_ariane' => 'Fil d\'Ariane',
    'accueil' => 'Accueil',

    // Un seul libelle pour les onze portraits : le nom vient en parametre,
    // depuis sa propre cle, et reste donc intact dans les deux langues.
    'photo_a_venir' => 'Photo de :nom, photo à venir',

    // Role affiche sous chaque portrait. « fondateur » et « formation »
    // existent deja plus bas comme intitules de pole : ils sont reutilises.
    'technique' => 'Technique',
    'communication' => 'Communication',
    'design' => 'Design',
    'juridique' => 'Juridique',
    'international' => 'International',
    'conseil_appui' => 'Conseil & Appui',

    'citation_du_fondateur' => '« Vérif\'All est né d\'un constat simple : au moment où une rumeur atteint un citoyen, il est déjà trop tard pour un démenti classique. Il fallait mettre l\'outil de vérification directement dans les mains de chacun. Le WSIS Prize 2026 récompense cette conviction, mais notre vraie récompense, c\'est chaque partage évité. »',
    'portrait_du_fondateur' => 'Portrait de René Fadonougbo, fondateur de Vérif\'All',
    'nous_contacter' => 'Nous contacter',

    'notre_equipe' => 'Notre Équipe',
    'journalistes_fact_checkeurs_ingenieurs_et_pedagogues_les' => 'Journalistes, fact-checkeurs, ingénieurs et pédagogues : les visages derrière la boussole.',
    'fondateur' => 'Fondateur',
    'rene_fadonougbo' => 'René Fadonougbo',
    'technique_communication_design' => 'Technique · Communication · Design',
    'rhodes_massenon' => 'Rhodes Massenon',
    'charles_o_dague' => 'Charles O. Dague',
    'sergino_obossou' => 'Sergino Obossou',
    'kevin_sobadji' => 'Kévin Sobadji',
    'formation' => 'Formation',
    'atman_bouba' => 'Atman Bouba',
    'gloria_tchidime' => 'Gloria Tchidime',
    'emmanuel_codjo' => 'Emmanuel Codjo',
    'juridique_international_conseil_appui' => 'Juridique · International · Conseil & Appui',
    'romaric_zinsou' => 'Romaric Zinsou',
    'souad_adechoubou' => 'Souad Adechoubou',
    'herbert_ebo' => 'Herbert Ebo',
    'le_mot_du_fondateur' => 'Le mot du fondateur',
    'rene_fadonougbo_fondateur_directeur_de_la_publication' => 'René Fadonougbo <span class="font-normal text-sm text-body/70">Fondateur, Directeur de la publication</span>',
    'envie_de_rejoindre_la_mission' => 'Envie de rejoindre la mission ?',
    'nous_recherchons_regulierement_des_mentors_fact_checkeurs' => 'Nous recherchons régulièrement des mentors fact-checkeurs et des relais communautaires dans les départements.',
];
