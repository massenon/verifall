<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Le vocabulaire partagé de l'interface publique
|--------------------------------------------------------------------------
|
| Ce fichier ne contient QUE ce qui apparaît sur plusieurs pages : la
| navigation, le pied de page, les libellés de compte. Le texte propre à une
| page reste dans sa vue tant qu'elle n'est pas traduite — regrouper trois
| cents phrases ici avant de les employer produirait un fichier que personne
| ne relit, et des clés orphelines qu'on n'ose plus supprimer.
|
*/

return [
    'langue' => [
        'choisir' => 'Choisir la langue',
    ],

    'nav' => [
        'pourquoi' => "Pourquoi Vérif'All ?",
        'fonctionnalites' => 'Fonctionnalités',
        'comment' => 'Comment ça marche ?',
        'tutoriels' => 'Tutoriels',
        'actualites' => 'Actualités',
        'ouvrir_menu' => 'Ouvrir le menu',
        'aller_contenu' => 'Aller au contenu',
    ],

    'compte' => [
        'connexion' => 'Connexion',
        'mon_compte' => 'Compte',
        'deconnexion' => 'Déconnexion',
        'se_deconnecter' => 'Se déconnecter',
        'creer_compte' => 'Créer mon compte',
    ],

    'pied' => [
        'droits' => 'Tous droits réservés',
        'politique_cookies' => 'Politique des cookies',
        'politique_confidentialite' => 'Politique de confidentialité',

        'corrections' => 'Corrections',
        'nos_sources' => 'Nos sources',
        'methodologie' => 'Méthodologie',
        'financement' => 'Financement',
        'parrainage' => 'Parrainage',
        'equipe' => 'Notre Équipe',
        'conditions' => 'Conditions générales',
        'mentions' => 'Mentions légales',
        'confidentialite' => 'Confidentialité',
        'cookies' => 'Cookies',
        'contact' => 'Contact',
        'faq' => 'FAQ',
        'medias' => 'Médias',
        'apprentissage' => 'Apprentissage',
    ],

    'marque' => [
        'accueil_aria' => 'Vérif\'All, accueil',
        'logo_alt' => 'Vérif\'All - Application de lutte contre la désinformation au Bénin',
        'description' => 'Vérif’All est une plateforme de lutte contre la désinformation. Elle aide les citoyens à vérifier les contenus et développer une culture numérique responsable.',
    ],

    'reseaux' => [
        'instagram' => 'Suivre Vérif\'All sur Instagram',
        'facebook' => 'Suivre Vérif\'All sur Facebook',
        'linkedin' => 'Suivre Vérif\'All sur LinkedIn',
    ],

    'pwa' => [
        'dialogue_aria' => 'Installer l\'application Vérif\'All',
        'installer_verifall' => 'Installer Vérif\'All',
        'plus_tard' => 'Plus tard',
        'installer' => 'Installer',
    ],

    'cookies_banniere' => [
        'titre' => 'Protection de vos données',
        'texte' => 'Nous utilisons des cookies essentiels au fonctionnement du site, et des cookies de mesure d\'audience optionnels. Vous pouvez accepter, refuser, ou personnaliser votre choix.',
        'essentiels' => 'Cookies essentiels',
        'toujours_actifs' => '(toujours actifs)',
        'essentiels_aria' => 'Cookies essentiels, toujours actifs',
        'mesure_audience' => 'Mesure d\'audience',
        'autoriser_mesure_aria' => 'Autoriser la mesure d\'audience',
        'personnaliser' => 'Personnaliser',
        'annuler' => 'Annuler',
        'accepter' => 'Accepter',
    ],

    'hors_ligne' => [
        'code' => 'Hors ligne',
        'titre' => 'Pas de connexion',
        'message' => 'Cette page n’a pas encore été consultée sur cet appareil, et le réseau est indisponible. Les pages déjà ouvertes restent lisibles.',
        'retour_accueil' => 'Retour à l’accueil',
    ],

    // Noms accessibles des images qui tiennent lieu de lien. L'`alt` d'une
    // image placée dans un lien EST le nom du lien : « google-store » y
    // annoncait un nom de fichier a qui ne voit pas l'image.
    'alt_google_play' => 'Google Play',
    'alt_app_store' => 'App Store',
    'alt_logo' => "Vérif'All",
];
