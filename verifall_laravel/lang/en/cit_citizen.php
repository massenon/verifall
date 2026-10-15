<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: shell (top bar and sidebar)
|--------------------------------------------------------------------------
|
| The two sign-out entries carry their icon markup. The `material-symbols`
| ligature name inside the span is an ICON IDENTIFIER, not a word: "logout"
| stays "logout" in both languages. Translating it would leave an empty square
| where the icon should be, and nothing would report it.
|
| The section headings are set in capitals in French. English keeps the same
| device, because it is how the sidebar separates its groups.
|
*/

return [
    'menu' => 'menu',

    'recherche_globale' => 'Global search',

    'citoyen_verificateur' => 'Citizen checker',

    'logout_se_deconnecter' => '<span class="material-symbols-rounded !text-[20px]" aria-hidden="true">logout</span> Sign out',

    'apprentissage' => 'LEARNING',
    'communaute' => 'COMMUNITY',

    'logout_deconnexion' => '<span class="material-symbols-rounded !text-[20px]" aria-hidden="true">logout</span><span>Sign out</span>',

    // Top bar.
    'aller_au_contenu' => 'Skip to content',
    'ouvrir_le_menu' => 'Open menu',
    'verif_all_tableau_de_bord' => 'Vérif\'All — dashboard',
    'rechercher_une_verification_un_cours_un_outil' => 'Search for a check, a course, a tool…',
    'verifier' => 'Check',
    'mes_unites_de_verification' => 'My verification units',
    'menu_utilisateur' => 'Account menu',

    // Account menu.
    'mon_compte' => 'My account',
    'mes_evaluations' => 'My Assessments',
    'live_classes' => 'Live Classes',
    'mes_statistiques' => 'My Statistics',
    'mes_unites' => 'My Units',
    'mes_recompenses' => 'My Rewards',
    'mon_profil' => 'My Profile',
    'reglages' => 'Settings',

    // Sidebar navigation.
    'navigation_principale' => 'Main navigation',
    'tableau_de_bord' => 'Dashboard',
    'verifier_un_contenu' => 'Check a content',
    'mes_verifications' => 'My Checks',
    'liste_des_outils' => 'Tool List',
    'mes_tutoriels' => 'My Tutorials',
    'catalogue_de_cours' => 'Course Catalogue',
    'certificats_badges' => 'Certificates & Badges',
    'forum' => 'Forum',
    'hub_opportunites' => 'Opportunities Hub',
    'mon_parrainage' => 'My Referrals',

    // Bottom bar, on phones.
    'navigation_mobile' => 'Mobile navigation',
    'accueil' => 'Home',
    'apprendre' => 'Learn',
    'communaute_nav' => 'Community',
    'profil' => 'Profile',

    /*
     | Témoin d'analyse en cours dans l'en-tête (lot 1, B-03).
     */
    'analyse_en_cours' => 'Analysis running',
    'analyse_voir_la_progression' => 'See your analysis progress',
    'analyse_en_cours_annonce' => 'An analysis is running. Select to see its progress.',

    'portails_officiels' => 'Official portals',
    'bientot' => 'Soon',
];
