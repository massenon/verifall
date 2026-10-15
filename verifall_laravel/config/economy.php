<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Packs d'unités
    |--------------------------------------------------------------------------
    |
    | ARBITRÉ — les valeurs du PROTOTYPE. Le prototype (unites.html) et
    | PAIEMENT_MOBILE_MONEY.md n'annoncent pas les mêmes quantités :
    |
    |     prix       prototype        instruction
    |     500 F      10 unités        25 unités
    |   1 000 F      25 unités        55 unités
    |   2 000 F      60 unités       120 unités
    |
    | Trois raisons de retenir le prototype : c'est ce qui a été validé et
    | affiché ; à sept moteurs par vérification, 20 F l'unité ne couvrirait
    | probablement pas le coût réel des API, et vendre à perte est le moyen le
    | plus sûr de n'avoir plus de plateforme ; enfin l'accessibilité est servie
    | par la dotation mensuelle et par la gratuité des réponses déjà connues —
    | une rumeur virale ne coûte rien à revérifier.
    |
    | Décision révisable sur une ligne, sans toucher au code.
    |
    | `units` est la SEULE source du montant crédité : le client n'envoie qu'un
    | identifiant de pack, jamais un prix ni une quantité.
    |
    */

    'packs' => [
        'p10' => ['units' => 10, 'price_fcfa' => 500],
        'p25' => ['units' => 25, 'price_fcfa' => 1000, 'featured' => true],
        'p60' => ['units' => 60, 'price_fcfa' => 2000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dotation mensuelle
    |--------------------------------------------------------------------------
    |
    | « 10 unités offertes chaque 1ᵉʳ du mois » (prototype).
    |
    | La feuille de route nomme cette tâche « ResetMonthlyUnits ». Elle AJOUTE,
    | elle ne remet pas à zéro : un compte ayant acheté 210 unités les perdrait
    | au premier du mois, ce qui reviendrait à confisquer ce qui a été payé.
    |
    | Si l'intention est que la dotation gratuite ne s'accumule pas, il faut
    | deux compteurs distincts — offert et acheté — et non un seuil sur le
    | total. À trancher avec les packs.
    |
    */

    'monthly_grant' => [
        'units' => 10,
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile Money
    |--------------------------------------------------------------------------
    |
    | `driver` = fake tant qu'aucun contrat opérateur n'est signé. Le simulateur
    | reproduit le protocole complet — demande, attente, rappel signé — pour que
    | le passage au vrai fournisseur ne change QUE l'implémentation de
    | l'interface, jamais le reste.
    |
    */

    'momo' => [
        'driver' => env('MOMO_DRIVER', 'fake'),

        // Secret du rappel (webhook). Aucune valeur par défaut : un secret de
        // repli dans le dépôt permettrait à quiconque le lit de créditer des
        // unités à volonté.
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),

        // Au-delà, la demande est réputée abandonnée : l'écran cesse
        // d'attendre et propose de réessayer.
        'timeout_minutes' => 5,

        // Une demande restée en attente au-delà est réinterrogée par
        // `verifall:reconcilier-paiements`.
        'reconcile_after_minutes' => 10,
    ],

];
