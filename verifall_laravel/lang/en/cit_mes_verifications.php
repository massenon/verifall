<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: my checks
|--------------------------------------------------------------------------
|
| "Your laboratory is still empty" is the empty state, and it is deliberately
| not "no results". The screen belongs to the person reading it: what they
| build here is their own history, and the wording says so.
|
| The two verdict tiles are counts of what was FOUND, not of what was
| submitted: "hoaxes detected" and "reliable information". They keep the
| platform's four verdicts behind them and never introduce a fifth.
|
| "Confidence" is a column of the report, not a judgement of the reader.
|
*/

return [
    'mes_verifications' => 'My checks',

    'votre_activite_de_verification_expand_more' => '<span class="text-sm font-display font-bold text-ink">Your checking activity</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',

    'contenus_soumis' => 'Items submitted',
    'en_cours' => 'In progress',
    'intox_detectees' => 'Hoaxes detected',
    'infos_fiables' => 'Reliable information',

    'rechercher' => 'Search',
    'contenu' => 'Content',
    'type' => 'Type',
    'verdict' => 'Verdict',
    'confiance' => 'Confidence',
    'date' => 'Date',
    'action' => 'Action',

    'aucun_resultat' => 'No results',
    'aucune_verification_ne_correspond_a_vos_criteres' => 'No check matches your current filters.',
    'reinitialiser_les_filtres' => 'Clear the filters',

    'votre_laboratoire_est_encore_vide' => 'Your laboratory is still empty',
    /*
     | Second pass: the filters and the action links. Their tags carried a
     | `wire:click` and a computed class, so the extraction skipped them.
     |
     | The counters used to inflect by hand, with one ternary per plural mark
     | (`contenu{{ ... ? 's' : '' }}`). That trick does not survive a change of
     | language; `trans_choice` replaces it.
     |
     | "Suivre" becomes "Track", not "Follow": the link opens the progress
     | screen of an analysis in flight, it does not subscribe to anything.
     */
    'fil_d_ariane' => 'Breadcrumb',
    'votre_laboratoire_personnel_de_verification' => 'Your personal fact-checking lab —',
    'n_contenus_analyses' => ':count item analysed|:count items analysed',
    'derniere_verification' => 'Last check',
    'verifier' => 'Check',
    'verifier_un_contenu' => 'Check a piece of content',
    'rechercher_dans_mes_verifications' => 'Search my checks…',
    'filtrer_par_type' => 'Filter by type',
    'filtrer_par_verdict' => 'Filter by verdict',
    'tous_les_types' => 'All types',
    'tous_les_verdicts' => 'All verdicts',
    'tous' => 'All',
    'vrai' => 'True',
    'faux' => 'False',
    'trompeur' => 'Misleading',
    'n_verifications' => ':count check|:count checks',
    'voir_le_rapport' => 'View the report',
    'suivre' => 'Track',
    'indisponible' => 'Unavailable',
    'de_fiabilite' => 'confidence',

    'commencez_votre_premiere_verification_pour_construire_votre' => 'Start your first check to begin building your history.',
];
