<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: my units
|--------------------------------------------------------------------------
|
| The three Mobile Money operators (MTN MoMo, Moov Money, Celtiis Cash) are
| PRODUCT NAMES and stay exactly as they are: a reader looking for the logo on
| their own phone needs the name their operator actually uses.
|
| The example phone number keeps its Beninese format. A generic "555" example
| would be no help to the person filling the field in.
|
| "Simulate the payment" says what the button does. On an instance with no
| payment provider configured, calling it "Pay" would be a lie told at the
| exact moment money is expected to change hands.
|
| The `bolt`, `download` and `print` ligatures are ICON IDENTIFIERS.
|
*/

return [
    'mes_unites' => 'My units',
    'la_monnaie_de_vos_verifications_et_de' => 'The currency for your checks and your premium courses.',

    'solde_actuel' => 'Current balance',
    'dotation_mensuelle' => 'Monthly allowance',
    'recharger' => 'Top up',

    'bolt_acheter_des_unites' => '<span class="material-symbols-rounded !text-[18px] icon-fill" aria-hidden="true">bolt</span> Buy units',

    'simuler_le_paiement' => 'Simulate the payment',
    'annuler' => 'Cancel',

    'historique_des_transactions' => 'Transaction history',
    'rechercher_une_transaction' => 'Search for a transaction',

    'download_exporter_csv' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">download</span> Export CSV',
    'print_imprimer' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">print</span> Print',

    'ref' => 'Ref.',
    'libelle' => 'Description',
    'montant' => 'Amount',
    'type' => 'Type',
    'date' => 'Date',

    'acheter_des_unites' => 'Buy units',
    'paiement_securise_via_mobile_money_mtn_momo' => 'Secure payment by Mobile Money (MTN MoMo, Moov Money, Celtiis Cash).',
    'numero_mobile_money_ex_97_00_00' => 'Mobile Money number (e.g. 97 00 00 00)',

    // ── AGREEMENTS THAT WERE SPELLED OUT IN BLADE ────────────────────────────
    //
    // "unité{s} disponible{s}" and "jour{s}" were assembled with inline
    // ternaries adding an "s" to two words at once — French agreement written
    // in a template. English needs one plural, not two, so these are
    // trans_choice keys and each language states its own forms.
    //
    // FCFA stays FCFA: it is the currency of Benin, not a word to translate.
    // The Mobile Money operator names are likewise left alone.
    'fil_d_ariane' => 'Breadcrumb',
    'compte_unites_disponibles' => 'unit available|units available',
    'unites_offertes_chaque_1er_du_mois' => 'units granted on the 1st of every month.',
    'prochaine_dotation' => 'Next grant:',
    'dans_n_jours' => 'In :nombre day|In :nombre days',
    'packs_a_partir_de' => 'Packs from',
    'via_mobile_money' => 'by Mobile Money.',

    'validez_la_demande_sur_votre_telephone' => 'Approve the request on your phone',
    'fcfa_pour' => 'FCFA for',
    'unites' => 'units',

    'rechercher_une_transaction_placeholder' => 'Search for a transaction…',
    'filtrer_par_type' => 'Filter by type',
    'type_tous' => 'Type: all',
    'credit' => 'Credit',
    'debit' => 'Debit',
    'transactions_par_page' => 'Transactions per page',
    'par_page' => '/ page',

    'aucune_transaction_ne_correspond_a_vos_filtres' => 'No transaction matches your filters.',
    'aucun_mouvement_pour_l_instant_votre_premiere' => 'Nothing has moved yet. Your first grant arrives on',
    'payer' => 'Pay',
];
