<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, unites
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
    'mes_unites' => 'Mes unités',
    'la_monnaie_de_vos_verifications_et_de' => 'La monnaie de vos vérifications et de vos cours premium.',
    'solde_actuel' => 'Solde actuel',
    'dotation_mensuelle' => 'Dotation mensuelle',
    'recharger' => 'Recharger',
    'bolt_acheter_des_unites' => '<span class="material-symbols-rounded !text-[18px] icon-fill" aria-hidden="true">bolt</span> Acheter des unités',
    'simuler_le_paiement' => 'Simuler le paiement',
    'annuler' => 'Annuler',
    'historique_des_transactions' => 'Historique des transactions',
    'rechercher_une_transaction' => 'Rechercher une transaction',
    'download_exporter_csv' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">download</span> Exporter CSV',
    'print_imprimer' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">print</span> Imprimer',
    'ref' => 'Réf.',
    'libelle' => 'Libellé',
    'montant' => 'Montant',
    'type' => 'Type',
    'date' => 'Date',
    'acheter_des_unites' => 'Acheter des unités',
    'paiement_securise_via_mobile_money_mtn_momo' => 'Paiement sécurisé via Mobile Money (MTN MoMo, Moov Money, Celtiis Cash).',
    'numero_mobile_money_ex_97_00_00' => 'Numéro Mobile Money (ex. 97 00 00 00)',

    // Restes en dur : fil d'Ariane, accords au pluriel construits en Blade,
    // options de <select> et libelles de la modale d'achat.
    'fil_d_ariane' => 'Fil d\'Ariane',
    'compte_unites_disponibles' => 'unité disponible|unités disponibles',
    'unites_offertes_chaque_1er_du_mois' => 'unités offertes chaque 1ᵉʳ du mois.',
    'prochaine_dotation' => 'Prochaine dotation :',
    'dans_n_jours' => 'Dans :nombre jour|Dans :nombre jours',
    'packs_a_partir_de' => 'Packs à partir de',
    'via_mobile_money' => 'via Mobile Money.',

    'validez_la_demande_sur_votre_telephone' => 'Validez la demande sur votre téléphone',
    'fcfa_pour' => 'FCFA pour',
    'unites' => 'unités',

    'rechercher_une_transaction_placeholder' => 'Rechercher une transaction…',
    'filtrer_par_type' => 'Filtrer par type',
    'type_tous' => 'Type : tous',
    'credit' => 'Crédit',
    'debit' => 'Débit',
    'transactions_par_page' => 'Transactions par page',
    'par_page' => '/ page',

    'aucune_transaction_ne_correspond_a_vos_filtres' => 'Aucune transaction ne correspond à vos filtres.',
    'aucun_mouvement_pour_l_instant_votre_premiere' => 'Aucun mouvement pour l\'instant. Votre première dotation arrive le',
    'payer' => 'Payer',
];
