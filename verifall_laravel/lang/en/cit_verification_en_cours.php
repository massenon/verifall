<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: a check in progress
|--------------------------------------------------------------------------
|
| This screen is shown to someone who is waiting and has started to wonder
| whether anything is happening. It therefore answers three questions in the
| order they get asked: what is going on, whether they have to stay, and what
| happens to what they paid.
|
| The refund promise ("your units will be given back to you if the analysis
| does not complete") is the reason it is safe to close the page, so it is
| kept in the same sentence rather than moved to a footnote.
|
| The retry button carries its own `wire:loading` states. The markup is
| reproduced exactly; only the visible words change.
|
*/

return [
    'analyse_en_cours' => 'Analysis in progress',
    'analyse_mise_en_attente' => 'Analysis put on hold',

    'refresh_relancer_maintenant_relance' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">refresh</span> <span wire:loading.remove wire:target="relancer">Restart now</span> <span wire:loading wire:target="relancer">Restarting…</span>',

    'l_analyse_prend_plus_de_temps_que' => 'The analysis is taking longer than expected.',

    'un_moteur_d_analyse_ne_repond_peut' => 'One of the analysis engines may not be responding. You can close this page: the report will appear under “My checks” as soon as it is ready, and your units will be given back to you if the analysis does not complete.',

    // ── THE REFUND PROMISE, KEPT WHOLE ───────────────────────────────────────
    //
    // "vos N unités vous seront rendues" is split across a <strong> and a
    // singular/plural branch, so it arrives here as fragments. They are
    // ordered so that each branch still reads as one sentence in English:
    //   your + N units + will be given back to you.
    //   your + unit   + will be given back to you.
    // This is the sentence that tells someone their money is safe when an
    // analysis stalls. A fragment translated in isolation would break the
    // promise, so the two branches are written and checked as wholes.
    'fil_d_ariane' => 'Breadcrumb',
    'verifier' => 'Check',
    'analyse' => 'Analysis',
    'ne_fermez_pas_cette_page_le_rapport' => 'Do not close this page — the report will open on its own.',
    'vous_pouvez_fermer_cette_page_sans_rien' => 'You can close this page without losing anything.',
    'reference' => 'Reference:',
    'avancement_de_l_analyse' => 'Analysis progress',

    'plutot_que_de_vous_rendre_un_verdict' => 'Rather than hand you a verdict nothing supports, we are running the analysis again',
    'des_maintenant' => 'right now.',
    'dans_n_minutes' => 'in :nombre minute.|in :nombre minutes.',

    'rien_n_est_perdu_la_reprise_est' => 'Nothing is lost: the retry is automatic, and if we still cannot reach a conclusion,',
    'vos' => 'your',
    'unites' => 'units',
    'vous_seront_rendues' => 'will be given back to you.',
    'votre' => 'your',
    'unite' => 'unit',
    'vous_sera_rendue' => 'will be given back to you.',

    'voir_mes_verifications' => 'See my checks',
    'cette_page_se_met_a_jour_toute' => 'This page updates itself. Each step lights up when it has actually been passed.',
    'nous_reprenons_l_analyse_sans_que_vous' => 'We are running the analysis again without you having to come back. The report will appear under “My checks”.',
];
