<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Version du contrat
    |--------------------------------------------------------------------------
    |
    | Le numéro majeur est dans l'URL (`/api/v1`) : c'est lui qui change quand
    | le contrat rompt. Ce numéro-ci est le RÉVISION du serveur à l'intérieur
    | de v1 — il n'est là que pour être lu dans un rapport de bogue, jamais
    | pour être comparé par le client.
    |
    */

    'version' => env('API_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Version minimale de l'application mobile
    |--------------------------------------------------------------------------
    |
    | Une application installée ne se met pas à jour parce qu'on le souhaite.
    | Quand une version ancienne devient dangereuse — protocole de paiement
    | changé, faille corrigée côté client — il faut pouvoir l'arrêter SANS
    | déployer de code : d'où une variable d'environnement, et non une
    | constante.
    |
    | Vide = aucun plancher, toutes les versions sont servies. C'est la valeur
    | par défaut, et ce doit rester l'exception de la couper : refuser une
    | application installée est une mesure grave.
    |
    | Le client annonce sa version dans l'en-tête `X-Client-Version`. Une
    | application qui ne l'envoie pas est servie : on ne punit pas un client
    | tiers légitime pour un en-tête que le contrat dit facultatif.
    |
    */

    'minimum_client' => env('API_MINIMUM_CLIENT'),

    /*
    |--------------------------------------------------------------------------
    | Durée de vie des jetons personnels
    |--------------------------------------------------------------------------
    |
    | En MINUTES, comme Sanctum les compte. 30 jours : assez long pour qu'un
    | usage normal ne redemande jamais le mot de passe, assez court pour qu'un
    | téléphone perdu et jamais signalé finisse par se refermer tout seul.
    |
    | Le renouvellement se fait à l'usage (`POST /auth/token/refresh` n'existe
    | pas — voir le contrat) : la date d'expiration est glissante tant que
    | l'application s'en sert.
    |
    */

    'token_ttl_minutes' => (int) env('API_TOKEN_TTL_MINUTES', 60 * 24 * 30),

    /*
    |--------------------------------------------------------------------------
    | Limitation de débit
    |--------------------------------------------------------------------------
    |
    | Trois plafonds, parce qu'il y a trois risques distincts :
    |
    |   lecture  — la charge d'un client qui boucle. Généreux : un écran qui
    |              se rafraîchit ne doit jamais être éconduit.
    |   ecriture — l'abus applicatif (spam du forum, signalements en masse).
    |   identite — le forçage de mot de passe et l'envoi massif de courriels.
    |              Compté PAR ADRESSE IP, puisqu'il n'y a pas encore de jeton.
    |
    */

    'debit' => [
        'lecture' => (int) env('API_RATE_READ', 120),
        'ecriture' => (int) env('API_RATE_WRITE', 30),
        'identite' => (int) env('API_RATE_IDENTITY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotence
    |--------------------------------------------------------------------------
    |
    | Durée de mémorisation d'une clé d'idempotence, en heures. 24 h couvre
    | très largement le cas visé : un téléphone hors réseau qui rejoue sa file
    | d'opérations en attente à la reconnexion.
    |
    */

    'idempotence_heures' => (int) env('API_IDEMPOTENCE_HOURS', 24),

];
