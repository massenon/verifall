<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, rapport
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
    'retour_a_l_historique' => 'Retour à l\'historique',
    'picture_as_pdf_pdf' => 'PDF',
    'indice_de_fiabilite' => 'Indice de fiabilité',
    'ref' => 'Réf.',
    'contexte_indique' => 'Contexte indiqué :',
    'hate_speech_index_hsi' => 'Hate Speech Index (HSI) :',
    'analyse_de_la_rhetorique_du_contenu_soumis' => 'Analyse de la rhétorique du contenu soumis.',
    'indisponible' => 'Indisponible',
    'sources_apportees' => 'Sources apportées',
    'moteurs_ont_conclu_autrement' => ':count moteur a conclu autrement.|:count moteurs ont conclu autrement.',
    'le_verdict_retenu_est_celui_que_soutient' => 'Le verdict retenu est celui que soutient la majorité pondérée des moteurs ; les avis divergents restent affichés ci-dessus.',
    'fiabilite' => 'fiabilité',
    'voir_les_outils' => 'Voir les outils',
    'votre_contenu_soumis' => 'Votre contenu soumis',
    'la_rhetorique_de_ce_contenu_n_a' => 'La rhétorique de ce contenu n\'a pas pu être évaluée : aucun moteur sémantique n\'a répondu sur ce point.',
    'l_analyse_porte_uniquement_sur_le_contenu' => 'L\'analyse porte uniquement sur le contenu soumis, jamais sur son auteur. Elle est indépendante du verdict : une information exacte peut être formulée pour stigmatiser.',
    'pourquoi_ce_resultat' => 'Pourquoi ce résultat ?',
    'moteurs_d_analyse_mobilises' => 'Moteurs d\'analyse mobilisés',
    'moteur' => 'Moteur',
    'avis' => 'Avis',
    'confiance' => 'Confiance',
    'temps' => 'Temps',
    'sources_et_preuves_consultees' => 'Sources et preuves consultées',
    'aucune_source_n_a_pu_etre_consultee' => 'Aucune source n\'a pu être consultée pour cette analyse.',
    'i_avertissement_sur_l_usage_de_l' => 'Avertissement sur l\'usage de l\'IA',
    'ce_rapport_est_produit_par_des_moteurs' => 'Ce rapport est produit par des moteurs automatiques et peut comporter des erreurs. Les sources ci-dessus sont là pour que vous puissiez vérifier vous-même. En cas de doute, demandez une contre-expertise.',
    'diffuser_la_verite' => 'Diffuser la vérité',
    'un_dementi_ne_circule_que_si_quelqu' => 'Un démenti ne circule que si quelqu\'un le partage. Renvoyez ce rapport à qui vous a transmis la rumeur.',
    'ce_verdict_vous_semble_faux' => 'Ce verdict vous semble faux ?',
    'signalez_le_un_verificateur_humain_reprendra_le' => 'Signalez-le : un vérificateur humain reprendra le dossier.',
    'verifier_soi_meme' => 'Vérifier soi-même',
    'le_repertoire_d_outils_osint_vous_forme' => 'Le répertoire d\'outils OSINT vous forme à l\'autonomie.',

    'ce_verdict_ne_vous_semble_pas_correct' => 'Ce verdict ne vous semble pas correct ?',
    'demandez_une_contre_expertise_par_nos_journalistes' => 'Demandez une contre-expertise par nos journalistes fact-checking.',
    'demander_un_expert' => 'Demander un expert',
    'demandee' => 'Demandée',
    'contester_a_nouveau' => 'Contester à nouveau',
    'ce_rapport_vous_a_t_il_ete_utile' => 'Ce rapport vous a-t-il été utile ?',
    'oui_tout_a_fait' => 'Oui, tout à fait',
    'non_pas_vraiment' => 'Non, pas vraiment',
    'partagez_ce_rapport_pour_stopper_la_propagation' => 'Partagez ce rapport pour stopper la propagation de cette rumeur.',
    'whatsapp' => 'WhatsApp',
    'partager_le_lien' => 'Partager le lien',
    'lien_copie' => 'Lien copié',
    'academie' => 'ACADÉMIE',
    'devenez_fact_checker_certifie' => 'Devenez fact-checker certifié',
    'apprenez_a_debusquer_les_pieges_du_web' => 'Apprenez à débusquer les pièges du web avec nos formations gratuites.',
    'commencer' => 'Commencer',
    'demander_une_contre_expertise' => 'Demander une contre-expertise',
    'vous_pensez_que_le_resultat_merite_une' => 'Vous pensez que le résultat mérite une nouvelle analyse ? Expliquez brièvement pourquoi.',
    'motif' => 'Motif de la contestation',
    'expliquez_brievement_ce_qui_vous_parait_faux' => 'Ce qui vous paraît faux, et pourquoi — une source contraire, une date, un lieu…',
    'une_seule_demande_a_la_fois_par_rapport' => 'Une seule demande à la fois par rapport. Vous serez prévenu de l’issue, y compris si le verdict est maintenu.',
    'annuler' => 'Annuler',
    'envoyer_la_demande' => 'Envoyer la demande',
    'verdict' => 'Verdict',
    'synthese_de_l_analyse' => "Synthèse de l'analyse",
    'partager' => 'Partager',
    'par_courriel' => 'Par courriel',
    'copier_le_lien_public' => 'Copier le lien public',
    'lancer_une_autre_verification' => 'Lancer une autre vérification',
    'objet_de_partage' => "Fact-checking officiel Vérif'All",
    // Remplace le mot du verdict dans un message partagé, quand le rapport
    // est MIXTE (contrat V-8) : aucun mot unique ne résume alors ce qui est
    // établi.
    'verdict_mixte_partage' => 'Affirmations contradictoires',
    'certificat_d_analyse' => "Certificat d'analyse Vérif'All",
    'mention_de_certification' => "Ce rapport est produit par recoupement automatisé multi-sources. Pour en contrôler l'intégrité et consulter les mises à jour, scannez le code ci-contre.",
];
