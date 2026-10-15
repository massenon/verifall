<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Jeu de démonstration
    |--------------------------------------------------------------------------
    |
    | Les seeders reproduisent les données du prototype pour que chaque sprint
    | dispose d'une démo navigable. Ils créent donc des comptes dont le mot de
    | passe est connu de toute l'équipe.
    |
    | Ce mot de passe n'a AUCUNE valeur par défaut : sans variable d'environnement,
    | les seeders de démonstration refusent de s'exécuter. Une valeur de repli
    | serait un identifiant en dur dans le dépôt, et il finirait un jour en
    | production — c'est le scénario que la règle « jamais de secret en dur »
    | existe pour empêcher.
    |
    */

    'demo' => [
        'password' => env('DEMO_PASSWORD'),

        /*
         | Verrou indépendant du mot de passe. Même correctement configurés, les
         | seeders de démonstration n'ont rien à faire sur une base réelle : ils
         | y créeraient des comptes administrateurs utilisables.
         */
        'enabled' => env('DEMO_SEED_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reprise de l'ancienne plateforme
    |--------------------------------------------------------------------------
    |
    | Chemin du dump SQL lu par `AnciensComptesSeeder`. Sans valeur, le seeder
    | cherche `../existingdb/massenon_verifalltest.sql`, comme la commande
    | `verifall:importer-ancienne-base`. Le dump contient des empreintes de mot
    | de passe et des adresses personnelles : il ne vit JAMAIS dans le dépôt.
    |
    */

    'ancienne_base' => [
        'dump' => env('ANCIENNE_BASE_DUMP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Villes proposées au profil
    |--------------------------------------------------------------------------
    |
    | Liste fermée reprise du prototype. En configuration et non en enum : ce
    | n'est pas un statut métier dont dépendrait une règle, mais une liste de
    | commodité, qu'un déploiement peut vouloir étendre sans toucher au code.
    |
    | « Autre » est conservé en dernier — une liste fermée sans échappatoire
    | force l'utilisateur à mentir sur sa ville.
    |
    */

    'villes' => [
        'Cotonou',
        'Porto-Novo',
        'Parakou',
        'Abomey-Calavi',
        'Bohicon',
        'Natitingou',
        'Autre',
    ],

    /*
    |--------------------------------------------------------------------------
    | Double authentification du back-office
    |--------------------------------------------------------------------------
    |
    | Quels rôles doivent présenter un second facteur pour entrer dans
    | l'administration. Une liste de rôles et non un booléen : « la 2FA est-elle
    | active ? » n'est pas la bonne question, « pour qui ? » l'est.
    |
    | CE QUE COÛTE UN RÔLE RETIRÉ DE CETTE LISTE. Son compte ne tient plus qu'au
    | mot de passe. Un mot de passe réutilisé ailleurs et retrouvé dans une fuite
    | suffit alors à ouvrir le back-office. C'est un arbitrage d'exploitation
    | légitime — encore faut-il qu'il soit pris en connaissance de cause, et
    | qu'il se voie, d'où sa place ici plutôt qu'au fond d'un service.
    |
    | Le rétablir ne demande aucun déploiement :
    |
    |     STAFF_2FA_ROLES="Admin,Mentor,Modérateur"
    |
    | Une valeur vide désactive le second facteur pour TOUT le personnel.
    |
    | Retirer un rôle n'efface aucun secret : un compte déjà enrôlé garde le
    | sien en base, inutilisé. Le remettre dans la liste le réactive tel quel,
    | sans nouveau scan.
    |
    */

    'securite' => [
        'deux_facteurs' => [
            'roles' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('STAFF_2FA_ROLES', 'Modérateur')),
            ), static fn (string $role): bool => $role !== '')),
        ],
    ],

];
