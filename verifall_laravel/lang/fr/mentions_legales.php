<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page mentions-legales
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
    'derniere_mise_a_jour' => 'Dernière mise à jour : :date',

    // Bloc « edition ». Les valeurs (raison sociale, adresse, courriel) viennent
    // des reglages ; seuls les intitules sont traduits.
    'le_site_web_et_l_application_sont_edites_par' => 'Le site web verifall.com et l\'application mobile Vérif\'All (ci-après « la Plateforme ») sont édités par :',
    'organisation_enregistree_en_republique_du_benin' => 'Organisation enregistrée en République du Bénin',
    'adresse' => 'Adresse :',
    'courriel' => 'Courriel :',
    'contact' => 'Contact :',

    // Intitules passes au composant <x-mention-legale> : ils s'affichent quand
    // le reglage correspondant n'est pas encore renseigne.
    'libelle_raison_sociale' => 'Raison sociale',
    'libelle_adresse_du_siege' => 'Adresse du siège',
    'libelle_directeur_de_la_publication' => 'Directeur de la publication',
    'libelle_nom_de_l_hebergeur' => 'Nom de l\'hébergeur',

    'le_directeur_de_la_publication_est' => 'Le directeur de la publication est',
    'en_sa_qualite_de_responsable_du_contenu' => ', en sa qualité de responsable du contenu publié sur la Plateforme.',
    'la_plateforme_est_hebergee_par' => 'La Plateforme est hébergée par :',

    // Phrases coupees par un lien interne : un fragment de part et d'autre.
    'une_erreur_de_verdict_se_corrige' => 'Une erreur de verdict se corrige, elle ne s\'efface pas. Toute modification d\'un verdict publié est consignée avec sa date et sa raison dans le',
    'journal_public_des_corrections' => 'journal public des corrections',
    'consultable_sans_compte' => ', consultable sans compte. La page du fact-check concerné porte également la mention de sa correction.',
    'pour_signaler_une_erreur_dans_une_verification' => 'Pour signaler une erreur dans une vérification, écrivez à :courriel en indiquant l\'adresse de la page et l\'élément contesté. La méthode employée est décrite sur la page',
    'methodologie' => 'méthodologie',
    'tout_contenu_publie_par_un_utilisateur' => 'Tout contenu publié par un utilisateur (message du forum, réponse, note de communauté) peut être signalé à la modération depuis la Plateforme, ou par courriel à :courriel. Un signalement précise l\'adresse du contenu et le motif invoqué.',
    'le_traitement_des_donnees_personnelles_est_decrit' => 'Le traitement des données personnelles est décrit dans la',
    'politique_de_confidentialite' => 'politique de confidentialité',
    'l_usage_des_traceurs_est_decrit_dans' => '. L\'usage des traceurs est décrit dans la',
    'politique_des_cookies' => 'politique des cookies',
    'les_sources_de_financement_sont_publiees_sur' => 'Les sources de financement de la Plateforme sont publiées sur la page',
    'financement' => 'financement',
    'aucun_financeur_n_exerce_de_controle_editorial' => '. Aucun financeur n\'exerce de contrôle éditorial sur les vérifications publiées.',
    'l_utilisation_de_la_plateforme_est_soumise' => 'L\'utilisation de la Plateforme est soumise au droit béninois. En cas de différend, une solution amiable est recherchée en premier lieu : la réclamation s\'adresse à :courriel et reçoit une réponse motivée.',

    'creer_mon_compte' => 'Créer mon compte',

    'mentions_legales' => 'Mentions Légales',
    'conformement_aux_dispositions_de_la_loi_n2017' => 'Conformément aux dispositions de la loi n°2017-20 du 20 avril 2018 portant Code du Numérique en République du Bénin, il est précisé aux utilisateurs du site et de l\'application Vérif\'All l\'identité des différents intervenants dans le cadre de sa réalisation et de son suivi.',
    '1_edition_du_site_et_de_l' => '1. Édition du site et de l\'application',
    '2_directeur_de_la_publication' => '2. Directeur de la publication',
    '3_hebergement' => '3. Hébergement',
    '4_propriete_intellectuelle' => '4. Propriété intellectuelle',
    'l_ensemble_des_elements_de_la_plateforme' => 'L\'ensemble des éléments de la Plateforme (textes, logos, images, icônes, applications, scripts) sont la propriété exclusive de l\'éditeur, à l\'exception des contenus soumis par les utilisateurs et des extraits cités dans le cadre d\'une vérification. Toute reproduction, distribution, modification ou utilisation de ces éléments, même partielle, sans autorisation écrite préalable, est interdite et susceptible de constituer un délit de contrefaçon.',
    'les_rapports_de_verification_peuvent_etre_cites' => 'Les rapports de vérification peuvent être cités et partagés librement, à condition que la citation soit fidèle, qu\'elle mentionne Vérif\'All et qu\'elle renvoie à la page d\'origine. Une citation qui déforme un verdict ou en isole une partie n\'entre pas dans cette autorisation.',
    '5_nature_et_portee_des_verifications' => '5. Nature et portée des vérifications',
    'verif_all_publie_quatre_verdicts_et_quatre' => 'Vérif\'All publie quatre verdicts, et quatre seulement : VRAI, FAUX, TROMPEUR et NON VÉRIFIABLE. Le verdict NON VÉRIFIABLE signifie que les éléments disponibles ne permettent pas de trancher, et non que l\'affirmation serait à moitié vraie.',
    'les_rapports_de_verification_sont_fournis_a' => 'Les rapports de vérification sont fournis à titre informatif et éducatif. Ils rendent compte des éléments consultables à la date de publication et n\'engagent pas la responsabilité de l\'éditeur quant aux décisions prises sur leur fondement. L\'utilisateur demeure responsable de l\'usage qu\'il fait des informations publiées.',
    'la_plateforme_contient_des_liens_vers_des' => 'La Plateforme contient des liens vers des sites tiers, notamment vers les sources citées. L\'éditeur n\'exerce aucun contrôle sur ces sites et ne répond pas de leur contenu.',
    '6_politique_de_correction' => '6. Politique de correction',
    '7_signalement_d_un_contenu_illicite' => '7. Signalement d\'un contenu illicite',
    'chaque_signalement_est_examine_et_fait_l' => 'Chaque signalement est examiné et fait l\'objet d\'une décision, retenue ou rejetée. La trace de cette décision est conservée : c\'est elle qui permet de répondre, plus tard, à la question de savoir pourquoi un contenu a été retiré ou maintenu.',
    '8_donnees_personnelles' => '8. Données personnelles',
    '9_financement_et_independance' => '9. Financement et indépendance',
    '10_droit_applicable_et_reglement_des_litiges' => '10. Droit applicable et règlement des litiges',
    'a_defaut_d_accord_competence_exclusive_est' => 'À défaut d\'accord, compétence exclusive est attribuée aux tribunaux de Cotonou.',
    'rejoignez_un_espace_d_information_transparent_et' => 'Rejoignez un espace d\'information transparent et sécurisé.',
    'maintenant_que_vous_connaissez_nos_engagements_passez' => 'Maintenant que vous connaissez nos engagements, passez à l\'action.<br>Créez votre compte gratuit pour bénéficier de tous nos outils et participer activement à la lutte contre la désinformation.',
];
