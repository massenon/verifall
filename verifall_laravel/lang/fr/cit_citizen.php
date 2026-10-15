<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, citizen
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
    'menu' => 'menu',
    'recherche_globale' => 'Recherche globale',
    'citoyen_verificateur' => 'Citoyen vérificateur',
    'logout_se_deconnecter' => '<span class="material-symbols-rounded !text-[20px]" aria-hidden="true">logout</span> Se déconnecter',
    'apprentissage' => 'APPRENTISSAGE',
    'communaute' => 'COMMUNAUTÉ',
    'logout_deconnexion' => '<span class="material-symbols-rounded !text-[20px]" aria-hidden="true">logout</span><span>Déconnexion</span>',

    // Barre du haut.
    'aller_au_contenu' => 'Aller au contenu',
    'ouvrir_le_menu' => 'Ouvrir le menu',
    'verif_all_tableau_de_bord' => 'Vérif\'All — tableau de bord',
    'rechercher_une_verification_un_cours_un_outil' => 'Rechercher une vérification, un cours, un outil…',
    'verifier' => 'Vérifier',
    'mes_unites_de_verification' => 'Mes unités de vérification',
    'menu_utilisateur' => 'Menu utilisateur',

    // Menu du compte.
    'mon_compte' => 'Mon compte',
    'mes_evaluations' => 'Mes Évaluations',
    'live_classes' => 'Live Classes',
    'mes_statistiques' => 'Mes Statistiques',
    'mes_unites' => 'Mes Unités',
    'mes_recompenses' => 'Mes Récompenses',
    'mon_profil' => 'Mon Profil',
    'reglages' => 'Réglages',

    // Colonne de navigation.
    'navigation_principale' => 'Navigation principale',
    'tableau_de_bord' => 'Tableau de bord',
    'verifier_un_contenu' => 'Vérifier un contenu',
    'mes_verifications' => 'Mes Vérifications',
    'liste_des_outils' => 'Liste des Outils',
    'mes_tutoriels' => 'Mes Tutoriels',
    'catalogue_de_cours' => 'Catalogue de Cours',
    'certificats_badges' => 'Certificats & Badges',
    'forum' => 'Forum',
    'hub_opportunites' => 'Hub opportunités',
    'mon_parrainage' => 'Mon Parrainage',

    // Barre du bas, sur téléphone.
    'navigation_mobile' => 'Navigation mobile',
    'accueil' => 'Accueil',
    'apprendre' => 'Apprendre',
    'communaute_nav' => 'Communauté',
    'profil' => 'Profil',

    /*
     | Témoin d'analyse en cours dans l'en-tête (lot 1, B-03).
     */
    'analyse_en_cours' => 'Analyse en cours',
    'analyse_voir_la_progression' => 'Voir la progression de votre analyse',
    'analyse_en_cours_annonce' => 'Une analyse est en cours. Cliquez pour voir sa progression.',

    'portails_officiels' => 'Portails officiels',
    'bientot' => 'Bientôt',
];
