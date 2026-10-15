<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shared wording of the public interface
|--------------------------------------------------------------------------
|
| Only what appears on more than one page: navigation, footer, account
| labels. Page-specific copy stays in its view until that page is translated.
|
| The product name is never translated. "Vérif'All" keeps its accent in
| English too: it is a name, not a word.
|
*/

return [
    'langue' => [
        'choisir' => 'Choose language',
    ],

    'nav' => [
        'pourquoi' => "Why Vérif'All?",
        'fonctionnalites' => 'Features',
        'comment' => 'How it works',
        'tutoriels' => 'Tutorials',
        'actualites' => 'Fact-checks',
        'ouvrir_menu' => 'Open menu',
        'aller_contenu' => 'Skip to content',
    ],

    'compte' => [
        'connexion' => 'Sign in',
        'mon_compte' => 'My Account',
        'deconnexion' => 'Sign out',
        'se_deconnecter' => 'Sign out',
        'creer_compte' => 'Create my account',
    ],

    'pied' => [
        'droits' => 'All rights reserved',
        'politique_cookies' => 'Cookie policy',
        'politique_confidentialite' => 'Privacy policy',

        'corrections' => 'Corrections',
        'nos_sources' => 'Our sources',
        'methodologie' => 'Methodology',
        'financement' => 'Funding',
        'parrainage' => 'Referrals',
        'equipe' => 'Our Team',
        'conditions' => 'Terms of Use',
        'mentions' => 'Legal notice',
        'confidentialite' => 'Privacy',
        'cookies' => 'Cookies',
        'contact' => 'Contact',
        'faq' => 'FAQ',
        'medias' => 'Media',
        'apprentissage' => 'Learning',
    ],

    'marque' => [
        'accueil_aria' => 'Vérif\'All, home',
        'logo_alt' => 'Vérif\'All - Fighting disinformation in Benin',
        'description' => 'Vérif’All is a platform against disinformation. It helps citizens check content and build a responsible digital culture.',
    ],

    'reseaux' => [
        'instagram' => 'Follow Vérif\'All on Instagram',
        'facebook' => 'Follow Vérif\'All on Facebook',
        'linkedin' => 'Follow Vérif\'All on LinkedIn',
    ],

    'pwa' => [
        'dialogue_aria' => 'Install the Vérif\'All app',
        'installer_verifall' => 'Install Vérif\'All',
        'plus_tard' => 'Later',
        'installer' => 'Install',
    ],

    'cookies_banniere' => [
        'titre' => 'Protecting your data',
        'texte' => 'We use cookies that are essential to the site, and optional audience-measurement cookies. You can accept, refuse, or customise your choice.',
        'essentiels' => 'Essential cookies',
        'toujours_actifs' => '(always on)',
        'essentiels_aria' => 'Essential cookies, always on',
        'mesure_audience' => 'Audience measurement',
        'autoriser_mesure_aria' => 'Allow audience measurement',
        'personnaliser' => 'Customise',
        'annuler' => 'Cancel',
        'accepter' => 'Accept',
    ],

    'hors_ligne' => [
        'code' => 'Offline',
        'titre' => 'No connection',
        'message' => 'This page has not been opened on this device yet, and the network is unavailable. Pages you have already opened remain readable.',
        'retour_accueil' => 'Back to home',
    ],

    // Accessible names for images that stand in for a link. The `alt` of an
    // image inside a link IS the link's name: "google-store" announced a file
    // name to anyone who cannot see the badge.
    'alt_google_play' => 'Google Play',
    'alt_app_store' => 'App Store',
    'alt_logo' => "Vérif'All",
];
