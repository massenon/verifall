<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: submit a check
|--------------------------------------------------------------------------
|
| The circled numerals ① ② ③ are kept. They are the visual spine of the form,
| and replacing them with "1." would change the layout of a screen the
| prototype fixes.
|
| The word "laboratory" is the term the platform uses for the analysis chain
| throughout, in both languages. It is not decoration: the glossary uses it in
| the moderation screens and in the forum escalation button, and a second word
| here would make them look like two different things.
|
*/

return [
    'verifier_une_information' => 'Check a piece of information',

    'choisissez_le_format_donnez_nous_le_contenu' => 'Choose the format, give us the content, add some context: our laboratory takes care of the rest.',

    'masquer' => 'Hide',

    '1_quel_type_de_contenu_voulez_vous' => '① What kind of content do you want to check?',

    '2_donnez_nous_le_contenu' => '② Give us the content',

    'collez_le_message_la_rumeur_ou_l' => 'Paste the message, the rumour or the claim to be checked…',

    'collez_l_adresse_de_la_page_ou' => 'Paste the address of the page or the post',

    '3_un_contexte_a_ajouter_facultatif' => '③ Anything to add for context? <span class="font-normal text-slate2">(optional)</span>',

    'ou_l_avez_vous_vu_depuis_quand' => 'Where did you see it? How long has it been going round?',

    'le_saviez_vous' => '💡 Did you know?',

    'un_contexte_precis_source_date_de_reception' => 'Precise context (source, date received) makes cross-checking against sources more relevant.',

    'votre_solde' => '🪙 Your balance',

    'verifier_soi_meme' => '🧰 Check it yourself?',

    'le_repertoire_d_outils_osint_vous_forme' => 'The OSINT tool directory teaches you to work on your own.',

    // ── THE COST LINE HAD A BUG THE TRANSLATION FIXES ────────────────────────
    //
    // "il vous restera N unités" was hardcoded plural: a balance of 1 read
    // "1 unités". Both halves of the line now go through the same
    // trans_choice key, so the singular is right in French and in English.
    //
    // The five step labels sit inside an aria-hidden container but are read on
    // screen, so they are translated. "pt|pts" is an abbreviation that happens
    // to be identical in both languages; it stays a plural key so the singular
    // badge is not written "1 pts".
    'fil_d_ariane' => 'Breadcrumb',
    'verifier' => 'Check',

    'etape_contenu' => 'CONTENT',
    'etape_sources' => 'SOURCES',
    'etape_preuves' => 'EVIDENCE',
    'etape_analyse' => 'ANALYSIS',
    'etape_verdict' => 'VERDICT',

    'rapport_pret_pour' => 'Report ready for',
    'abreviation_points' => 'pt|pts',

    'choisir_un_fichier' => 'Choose a file',
    'il_reste_sur_nos_serveurs_prives_et' => 'It stays on our private servers and is never published.',
    'envoi_du_fichier' => 'Uploading the file…',

    'cette_verification_consommera' => 'This check will use',
    'compte_unites' => ':nombre unit|:nombre units',
    'il_vous_restera' => 'you will have',
    'votre_solde_est_insuffisant' => 'your balance is too low',
    'lancer_la_verification' => 'Start the check',
    'lancement' => 'Starting…',
    'rechargez_votre_compte' => 'Top up your account',
    'pour_lancer_cette_verification' => 'to start this check.',

    'unites' => 'units',
    'gerer_mes_unites' => 'Manage my units',
    'voir_les_outils' => 'See the tools',
];
