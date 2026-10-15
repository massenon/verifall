<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Préférences et onglets de la cloche
|--------------------------------------------------------------------------
|
| Les clés sont les valeurs d'enum, celles-là mêmes qui sont écrites en base
| et lues par les notificateurs. Traduire le libellé ne les touche pas : une
| préférence désactivée le reste, quelle que soit la langue de l'écran.
|
| La lettre d'information n'est pas ici : c'est un consentement marketing,
| tracé dans `consents`.
|
*/

return [

    'preference' => [
        'libelle' => [
            'verification_results' => 'Résultats de vérification',
            'learning_reminders' => "Rappels d'apprentissage",
            'forum_activity' => 'Activité du forum',
            'login_alerts' => 'Alertes de nouvelle connexion',
        ],

        'description' => [
            'verification_results' => "Être averti dès qu'un rapport est prêt ou mis à jour.",
            'learning_reminders' => 'Live Class à venir, série en danger, cours à reprendre.',
            'forum_activity' => 'Réponses à vos discussions et mentions.',
            'login_alerts' => "E-mail envoyé lorsqu'un nouvel appareil se connecte.",
        ],
    ],

    /*
     | Les onglets de la cloche. « Tout » n'en est pas un : c'est l'absence de
     | filtre, et il n'a donc pas de clé ici.
     */
    'categorie' => [
        'verif' => 'Vérifications',
        'lms' => 'Apprentissage',
        'community' => 'Communauté',
    ],
];
