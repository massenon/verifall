<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, verifier
|--------------------------------------------------------------------------
|
| ENGENDRE depuis la vue, puis relu. La copie du prototype reste ainsi
| identique au caractere pres.
|
| Les valeurs qui portent du balisage en ligne sont rendues en syntaxe non
| echappee. Ce n'est PAS une entorse a la regle de securite : ces chaines sont
| ecrites par l'equipe, jamais par un visiteur.
|
*/

return [
    'verifier_une_information' => 'Vérifier une information',
    'choisissez_le_format_donnez_nous_le_contenu' => 'Choisissez le format, donnez-nous le contenu, ajoutez du contexte : notre laboratoire s\'occupe du reste.',
    'masquer' => 'Masquer',
    '1_quel_type_de_contenu_voulez_vous' => '① Quel type de contenu voulez-vous vérifier ?',
    '2_donnez_nous_le_contenu' => '② Donnez-nous le contenu',
    'collez_le_message_la_rumeur_ou_l' => 'Collez le message, la rumeur ou l\'affirmation à vérifier…',
    'collez_l_adresse_de_la_page_ou' => 'Collez l\'adresse de la page ou de la publication',
    '3_un_contexte_a_ajouter_facultatif' => '③ Un contexte à ajouter ? <span class="font-normal text-slate2">(facultatif)</span>',
    'ou_l_avez_vous_vu_depuis_quand' => 'Où l\'avez-vous vu ? Depuis quand circule-t-il ?',
    'le_saviez_vous' => '💡 Le saviez-vous ?',
    'un_contexte_precis_source_date_de_reception' => 'Un contexte précis (source, date de réception) améliore la pertinence du recoupement de sources.',
    'votre_solde' => '🪙 Votre solde',
    'verifier_soi_meme' => '🧰 Vérifier soi-même ?',
    'le_repertoire_d_outils_osint_vous_forme' => 'Le répertoire d\'outils OSINT vous forme à l\'autonomie.',

    // Restes en dur : fil des etapes, badges de cout, bandeau de solde et
    // libelles des deux etats du bouton de lancement.
    'fil_d_ariane' => 'Fil d\'Ariane',
    'verifier' => 'Vérifier',

    'etape_contenu' => 'CONTENU',
    'etape_sources' => 'SOURCES',
    'etape_preuves' => 'PREUVES',
    'etape_analyse' => 'ANALYSE',
    'etape_verdict' => 'VERDICT',

    'rapport_pret_pour' => 'Rapport prêt pour',
    'abreviation_points' => 'pt|pts',

    'choisir_un_fichier' => 'Choisir un fichier',
    'il_reste_sur_nos_serveurs_prives_et' => 'Il reste sur nos serveurs privés et n\'est jamais publié.',
    'envoi_du_fichier' => 'Envoi du fichier…',

    'cette_verification_consommera' => 'Cette vérification consommera',
    'compte_unites' => ':nombre unité|:nombre unités',
    'il_vous_restera' => 'il vous restera',
    'votre_solde_est_insuffisant' => 'votre solde est insuffisant',
    'lancer_la_verification' => 'Lancer la vérification',
    'lancement' => 'Lancement…',
    'rechargez_votre_compte' => 'Rechargez votre compte',
    'pour_lancer_cette_verification' => 'pour lancer cette vérification.',

    'unites' => 'unités',
    'gerer_mes_unites' => 'Gérer mes unités',
    'voir_les_outils' => 'Voir les outils',
];
