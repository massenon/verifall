<?php

declare(strict_types=1);

return [
    /*
     | Volontairement vague sur la cause. Dire « ce compte n'existe pas »
     | transforme le formulaire de connexion en outil d'énumération : on y teste
     | des adresses jusqu'à savoir lesquelles sont inscrites.
     */
    'failed' => 'Ces identifiants ne correspondent à aucun compte.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives. Réessayez dans :seconds secondes.',

    /*
     | Le carrousel du gabarit d'authentification (components/layouts/auth).
     | Quatre panneaux, un par étape de la démarche CLAIM · SOURCE · EVIDENCE ·
     | VERDICT. Les quatre mots de l'acronyme ne sont PAS traduits : ils
     | nomment la méthode, et la même personne doit les retrouver à
     | l'identique d'une langue à l'autre.
     */
    'carrousel_identifier' => 'IDENTIFIER',
    'carrousel_comprendre_avant_de_croire' => 'Comprendre avant de croire.',
    'carrousel_savoir_distinguer_le_vrai_du_faux' => '"Savoir distinguer le vrai du faux est un super-pouvoir numérique. Apprenez les réflexes essentiels pour ne plus jamais vous faire manipuler."',
    'carrousel_boussole_de_l_information' => 'Boussole de l\'information',
    'carrousel_questionner' => 'QUESTIONNER',
    'carrousel_qui_parle_et_pourquoi' => 'Qui parle, et pourquoi ?',
    'carrousel_qui_parle_une_source_fiable_cite' => '"Qui parle ? Une source fiable cite toujours ses origines. Si le site est inconnu ou l\'auteur anonyme, utilisez notre outil de vérification de source."',
    'carrousel_verifiez_l_origine' => 'Vérifiez l\'origine',
    'carrousel_ne_partagez_pas_l_inconnu' => 'Ne partagez pas l\'inconnu',
    'carrousel_verifier' => 'VÉRIFIER',
    'carrousel_l_emotion_n_est_pas_une_preuve_point' => 'L\'émotion n\'est pas une preuve.',
    'carrousel_si_une_info_provoque_une_colere_ou' => '"Si une info provoque une colère ou une joie intense, méfiez-vous. La désinformation joue sur vos émotions pour court-circuiter votre logique."',
    'carrousel_gardez_la_tete_froide' => 'Gardez la tête froide',
    'carrousel_l_emotion_n_est_pas_une_preuve' => 'L\'émotion n\'est pas une preuve',
    'carrousel_comprendre' => 'COMPRENDRE',
    'carrousel_la_forme_trahit_souvent_le_fond_point' => 'La forme trahit souvent le fond.',
    'carrousel_un_communique_officiel_truffe_de_fautes' => '"Un communiqué officiel truffé de fautes ? C\'est souvent une arnaque. La crédibilité d\'une information passe aussi par la qualité de sa rédaction."',
    'carrousel_soyez_attentifs_aux_details' => 'Soyez attentifs aux détails',
    'carrousel_la_forme_trahit_souvent_le_fond' => 'La forme trahit souvent le fond',

    'demarche_les_4_etapes_de_la_demarche' => 'Les 4 étapes de la démarche',
    'demarche_claim_identifier' => 'CLAIM : Identifier',
    'demarche_source_questionner' => 'SOURCE : Questionner',
    'demarche_evidence_verifier' => 'EVIDENCE : Vérifier',
    'demarche_verdict_comprendre' => 'VERDICT : Comprendre',
    'demarche_identifier' => 'Identifier',
    'demarche_questionner' => 'Questionner',
    'demarche_verifier' => 'Vérifier',
    'demarche_comprendre' => 'Comprendre',
];
