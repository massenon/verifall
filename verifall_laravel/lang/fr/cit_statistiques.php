<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, statistiques
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
    'mes_statistiques' => 'Mes Statistiques',
    'apercu_de_votre_pratique_expand_more' => '<span class="text-sm font-display font-bold text-ink">Aperçu de votre pratique</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',
    'verifications_totales' => 'Vérifications totales',
    'intox_detectees' => 'Intox détectées',
    'partages_potentiellement_evites' => 'Partages potentiellement évités',
    'lecons_terminees' => 'Leçons terminées',
    'serie_actuelle' => 'Série actuelle',
    'activite' => 'Activité',
    'verifications_lecons_et_quiz_cumules' => 'Vérifications, leçons et quiz cumulés.',
    'pas_encore_de_donnees_sur_cette_periode' => 'Pas encore de données sur cette période',
    'effectuez_une_verification_pour_alimenter_votre_courbe' => 'Effectuez une vérification pour alimenter votre courbe d\'activité.',
    'verdicts' => 'Verdicts',
    'repartition_de_mes_verdicts' => 'Répartition de mes verdicts',
    'sur_l_ensemble_de_mes_verifications' => 'Sur l\'ensemble de mes vérifications.',
    'contenus' => 'Contenus',
    'types_de_contenus_verifies' => 'Types de contenus vérifiés',
    'ce_que_vous_soumettez_le_plus' => 'Ce que vous soumettez le plus.',
    'aucun_contenu_verifie_pour_le_moment' => 'Aucun contenu vérifié pour le moment.',
    'habitudes' => 'Habitudes',
    'ma_semaine_de_serie' => 'Ma semaine de série',
    'connectez_vous_chaque_jour_pour_maintenir_votre' => 'Connectez-vous chaque jour pour maintenir votre série. Le badge « Série de 30 jours » vous attend à 30 jours consécutifs.',
    'votre_insight' => '✦ Votre insight',

    // Chaines restees en dur : balises a attribut dynamique, options de <select>,
    // et libelles fabriques cote PHP par Str::plural() sur un mot francais.
    'fil_d_ariane' => 'Fil d\'Ariane',
    'statistiques' => 'Statistiques',
    'votre_activite_de_verification_votre_progression' => 'Votre activité de vérification, votre progression et votre impact au fil du temps.',
    'derniere_mise_a_jour' => 'Dernière mise à jour :',
    'format_derniere_mise_a_jour' => '\a\u\j\o\u\r\d\'\h\u\i \à H:i',

    'vs_30_jours_precedents' => 'vs 30 jours précédents',
    'stable_sur_30_jours' => 'Stable sur 30 jours',
    'cette_semaine' => 'cette semaine',
    'aucune_cette_semaine' => 'Aucune cette semaine',
    'abreviation_jour' => 'j',
    'record_personnel' => 'Record personnel :',
    'compte_jours' => ':nombre jour|:nombre jours',

    'periode_du_graphique' => 'Période du graphique',
    '7_derniers_jours' => '7 derniers jours',
    '30_derniers_jours' => '30 derniers jours',
    '3_derniers_mois' => '3 derniers mois',
    'cette_annee' => 'Cette année',
    'courbe_d_activite' => 'Courbe d\'activité',
    'verifier_un_contenu' => 'Vérifier un contenu',

    'repartition_des_verdicts' => 'Répartition des verdicts',
    'verifications' => 'vérifications',
    'pas_encore_de_donnees_effectuez_votre_premiere' => 'Pas encore de données — effectuez votre première vérification.',
    'verifie_vrai' => 'Vérifié vrai',
    'douteux' => 'Douteux',
    'verifie_faux' => 'Vérifié faux',
    'types_de_contenus_verifies_aria' => 'Types de contenus vérifiés',

    'jour_a_venir' => 'jour à venir',
    'aujourd_hui_actif' => 'aujourd\'hui, actif',
    'jour_actif' => 'jour actif',
    'jour_sans_activite' => 'jour sans activité',
    'jours_consecutifs' => ':nombre jour consécutif|:nombre jours consécutifs',

    'vous_avez_effectue_verifications' => 'Vous avez effectué :nombre vérification|Vous avez effectué :nombre vérifications',
    'votre_activite_est_en_hausse' => '— votre activité est en hausse',
    'continuez_votre_serie_de_jours' => 'Continuez votre série de :nombre jour pour débloquer votre prochain bonus d\'unités.|Continuez votre série de :nombre jours pour débloquer votre prochain bonus d\'unités.',
    'continuer_a_verifier' => 'Continuer à vérifier',
];
