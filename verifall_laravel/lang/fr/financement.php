<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page financement
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
    'placeholder_votre_nom' => 'Votre nom',
    'placeholder_adresse_e_mail' => 'nom@exemple.com',
    'placeholder_organisation' => 'Si vous soutenez au nom d\'une structure',
    'placeholder_montant_ou_nature' => 'Ex. 25 000 FCFA, ou du temps de formation',
    'placeholder_facultatif' => 'Facultatif',

    // Les `value` du <select> restent inchangees : c'est le serveur qui les lit.
    'frequence_une_seule_fois' => 'Une seule fois',
    'frequence_chaque_mois' => 'Chaque mois',
    'frequence_chaque_annee' => 'Chaque année',
    'frequence_a_en_discuter' => 'À en discuter',

    // Phrase coupee par deux liens internes : un fragment par morceau.
    'les_verdicts_ne_sont_ni_sponsorises_ni' => 'Les verdicts ne sont ni sponsorisés ni négociables. Aucun soutien nommé sur cette page n\'a de droit de regard sur une vérification : ce que nous corrigeons, nous le publions,',
    'le_journal_des_corrections' => 'le journal des corrections',
    'en_fait_foi_et' => 'en fait foi, et',
    'notre_methodologie' => 'notre méthodologie',
    'decrit_comment_un_verdict_est_etabli' => 'décrit comment un verdict est établi.',

    'nous_contacter' => 'Nous contacter',

    'financement' => 'Financement',
    'd_ou_viennent_nos_ressources_et_qui' => 'D\'où viennent nos ressources, et qui soutient Vérif\'All.',
    'nous_ne_publions_que_ce_que_nous' => 'Nous ne publions que ce que nous pouvons établir. Là où nous n\'avons pas de montant à donner, cette page le dit plutôt que d\'en avancer un.',
    'dotations_recues' => 'Dotations reçues',
    'verif_all_a_ete_distinguee_deux_fois' => 'Vérif\'All a été distinguée deux fois en 2026. Une seule de ces distinctions s\'accompagne d\'un montant que nous pouvons publier.',
    'concours_objectif_developpement_durable_800_2e_prix' => '<div class="flex items-center flex-wrap gap-2 mb-2"> <h3 class="text-lg font-display font-bold text-heading mb-0"> Concours « Objectif : Développement Durable ! » </h3> <span class="ml-auto inline-flex items-center rounded-full bg-primary text-white font-bold px-4 py-1.5">800 €</span> </div> <p class="text-body/85 leading-relaxed mb-2"> 2ᵉ prix décerné par <a href="https://www.univ-amu.fr/" target="_blank" rel="noopener" class="text-primary font-bold">Aix-Marseille Université</a>, pour un projet présenté sous l\'intitulé « Vérif\'All, la boussole contre la désinformation numérique ». </p> <p class="text-sm text-body/70 mb-0"> Dotation financée par le <strong>Fonds de dotation Demain</strong>. </p>',
    'prix_wsis_2026_categorie_media_decerne_le' => '<h3 class="text-lg font-display font-bold text-heading mb-2">Prix WSIS 2026, catégorie « Media »</h3> <p class="text-body/85 leading-relaxed mb-2"> Décerné le 9 juillet 2026 à Genève par l\'<a href="https://www.itu.int/fr/Pages/default.aspx#/fr" target="_blank" rel="noopener" class="text-primary font-bold">Union internationale des télécommunications</a>, agence spécialisée des Nations unies, lors du Forum du Sommet mondial sur la société de l\'information. Vérif\'All figure parmi les 18 initiatives récompensées, sur 1 595 projets issus de 122 pays. </p> <p class="text-sm text-body/70 mb-0"> Distinction. Aucun montant n\'est associé à cette entrée dans nos sources publiées. </p>',
    'soutiens_du_projet_et_du_lancement' => 'Soutiens du projet et du lancement',
    'le_developpement_de_la_plateforme_et_son' => 'Le développement de la plateforme et son module pédagogique destiné aux jeunes ont bénéficié du soutien de la <a href="https://www.fondationdefrance.org/fr/" target="_blank" rel="noopener" class="text-primary font-bold">Fondation de France</a>.',
    'le_lancement_public_le_16_juillet_2026' => 'Le lancement public, le 16 juillet 2026 au CDIJ de la Cour d\'Appel de Cotonou, puis le 18 juillet 2026 au CEMAAC de Porto-Novo, a été rendu possible par la Fondation de France, <strong>AIESEC</strong>, <strong>U-Report Porto-Novo</strong>, le <a href="https://leolagrange.bj/cemaac-centre-de-lavenir-du-pole-jeunesse-et-solidarite-internationale-de-la-ville-de-porto-novo/" target="_blank" rel="noopener" class="text-primary font-bold">CEMAAC Bénin</a>, l\'<strong>Association Mairie des Jeunes du Bénin</strong> et le <strong>JESPD</strong>.',
    'figurent_egalement_parmi_les_partenaires_du_projet' => 'Figurent également parmi les partenaires du projet l\'<a href="https://www.ofaj.org/" target="_blank" rel="noopener" class="text-primary font-bold">Office franco-allemand pour la Jeunesse</a>, le <a href="https://www.rycowb.org/" target="_blank" rel="noopener" class="text-primary font-bold">Regional Youth Cooperation Office</a> et la <a href="https://www.evz.de/" target="_blank" rel="noopener" class="text-primary font-bold">fondation EVZ</a> (Erinnerung, Verantwortung und Zukunft).',
    'ces_soutiens_sont_nommes_sans_montant_aucun' => 'Ces soutiens sont nommés sans montant : aucun n\'est chiffré dans les sources dont nous disposons, et nous n\'en inventerons pas.',
    'soutenir_verif_all' => 'Soutenir Vérif\'All',
    'verifier_une_information_coute_des_appels_a' => 'Vérifier une information coûte : des appels à des services d\'analyse payants, du temps de relecture humaine, et de l\'hébergement. Un soutien, même modeste, finance des vérifications qui restent gratuites pour tous.',
    'ce_formulaire_n_encaisse_aucun_paiement_il' => 'Ce formulaire n\'encaisse aucun paiement. Il enregistre votre proposition, et nous vous écrivons pour convenir des modalités.',
    'ne_remplissez_pas_ce_champ' => 'Ne remplissez pas ce champ',
    'nom_complet' => 'Nom complet *',
    'adresse_e_mail' => 'Adresse e-mail *',
    'organisation' => 'Organisation',
    'montant_ou_nature_du_soutien' => 'Montant ou nature du soutien',
    'frequence' => 'Fréquence',
    'message' => 'Message',
    'proposer_mon_soutien' => 'Proposer mon soutien',
    'aucun_financeur_n_exerce_de_controle_editorial' => 'Aucun financeur n\'exerce de contrôle éditorial sur les vérifications publiées. Un soutien n\'achète ni un verdict, ni un silence.',
    'nos_recettes_propres' => 'Nos recettes propres',
    'packs_d_unites_de_verification_le_modele' => '<strong>Packs d\'unités de vérification.</strong> Le modèle économique de la plateforme repose sur la vente de packs d\'unités, qui permettent de lancer des analyses au-delà de la dotation gratuite. À ce jour, <strong>aucun encaissement n\'est en service</strong> : le paiement Mobile Money attend un contrat opérateur, et le module tourne en simulation.',
    'aucun_espace_publicitaire_n_est_vendu_les' => '<strong>Aucun espace publicitaire n\'est vendu.</strong> Les bandeaux affichés sur le site sont des annonces de Vérif\'All ; il n\'existe pas de régie ni d\'annonceur tiers.',
    'ce_que_cette_page_ne_dit_pas' => 'Ce que cette page ne dit pas encore',
    'nous_ne_publions_pas_encore_de_budget' => 'Nous ne publions pas encore de budget annuel ni de répartition de nos ressources par origine. Cette information n\'est pas cachée : elle n\'est pas établie. Elle sera ajoutée ici quand elle le sera, et cette page indiquera à quelle date.',
    'une_question_sur_nos_ressources' => 'Une question sur nos ressources ?',
    'ecrivez_nous_nous_repondons_aux_demandes_de' => 'Écrivez-nous : nous répondons aux demandes de précision sur cette page.',
];
