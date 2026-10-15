<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: my statistics
|--------------------------------------------------------------------------
|
| ── ONE HEDGE THAT MUST NOT BE DROPPED ───────────────────────────────────
|
| "Shares POTENTIALLY avoided" is an estimate, and the qualifier is the whole
| difference between a figure and a claim. Nobody can measure a share that did
| not happen. Translating it as "shares avoided" would turn a plausible
| indicator into a fabricated statistic, on a platform whose entire argument
| is that unsupported figures should be doubted.
|
| The empty states point at the action that fills the chart, not at the
| absence of data, because a new account has no history through no fault of
| its own.
|
*/

return [
    'mes_statistiques' => 'My statistics',

    'apercu_de_votre_pratique_expand_more' => '<span class="text-sm font-display font-bold text-ink">Your practice at a glance</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',

    'verifications_totales' => 'Total checks',
    'intox_detectees' => 'Hoaxes detected',
    'partages_potentiellement_evites' => 'Shares potentially avoided',
    'lecons_terminees' => 'Lessons finished',
    'serie_actuelle' => 'Current streak',

    'activite' => 'Activity',
    'verifications_lecons_et_quiz_cumules' => 'Checks, lessons and quizzes combined.',
    'pas_encore_de_donnees_sur_cette_periode' => 'No data for this period yet',
    'effectuez_une_verification_pour_alimenter_votre_courbe' => 'Run a check to start filling in your activity curve.',

    'verdicts' => 'Verdicts',
    'repartition_de_mes_verdicts' => 'How my verdicts break down',
    'sur_l_ensemble_de_mes_verifications' => 'Across all of my checks.',

    'contenus' => 'Content',
    'types_de_contenus_verifies' => 'Types of content checked',
    'ce_que_vous_soumettez_le_plus' => 'What you submit most.',
    'aucun_contenu_verifie_pour_le_moment' => 'No content checked yet.',

    'habitudes' => 'Habits',
    'ma_semaine_de_serie' => 'My streak this week',
    'connectez_vous_chaque_jour_pour_maintenir_votre' => 'Sign in every day to keep your streak going. The “30-day streak” badge is waiting for you at 30 days in a row.',

    'votre_insight' => '✦ Your insight',

    // ── PLURALS THAT USED TO BE FAKED ────────────────────────────────────────
    //
    // Four of these labels were built in the view by Str::plural() applied to a
    // FRENCH word — an English inflector guessing at "jour" and "consécutif".
    // It happened to produce the right French, and there was no way for the
    // English to differ, because the plural was computed, not translated.
    // They are trans_choice keys now, so each language states its own forms and
    // its own word order: French puts "consécutifs" after the noun, English
    // puts "consecutive" in front of it.
    'fil_d_ariane' => 'Breadcrumb',
    'statistiques' => 'Statistics',
    'votre_activite_de_verification_votre_progression' => 'Your checking activity, your progress and your impact over time.',
    'derniere_mise_a_jour' => 'Last updated:',
    // Escaped literals for translatedFormat(): every letter of "today at" is
    // backslash-escaped so Carbon prints it instead of reading it as a token.
    'format_derniere_mise_a_jour' => '\t\o\d\a\y \a\t H:i',

    'vs_30_jours_precedents' => 'vs the previous 30 days',
    'stable_sur_30_jours' => 'Steady over 30 days',
    'cette_semaine' => 'this week',
    'aucune_cette_semaine' => 'None this week',
    'abreviation_jour' => 'd',
    'record_personnel' => 'Personal best:',
    'compte_jours' => ':nombre day|:nombre days',

    'periode_du_graphique' => 'Chart period',
    '7_derniers_jours' => 'Last 7 days',
    '30_derniers_jours' => 'Last 30 days',
    '3_derniers_mois' => 'Last 3 months',
    'cette_annee' => 'This year',
    'courbe_d_activite' => 'Activity curve',
    'verifier_un_contenu' => 'Check a piece of content',

    'repartition_des_verdicts' => 'How the verdicts break down',
    'verifications' => 'checks',
    'pas_encore_de_donnees_effectuez_votre_premiere' => 'No data yet — run your first check.',
    'verifie_vrai' => 'Checked true',
    'douteux' => 'Doubtful',
    'verifie_faux' => 'Checked false',
    'types_de_contenus_verifies_aria' => 'Types of content checked',

    'jour_a_venir' => 'day still to come',
    'aujourd_hui_actif' => 'today, active',
    'jour_actif' => 'active day',
    'jour_sans_activite' => 'day with no activity',
    'jours_consecutifs' => ':nombre consecutive day|:nombre consecutive days',

    'vous_avez_effectue_verifications' => 'You have run :nombre check|You have run :nombre checks',
    'votre_activite_est_en_hausse' => '— your activity is rising',
    'continuez_votre_serie_de_jours' => 'Keep your :nombre-day streak going to unlock your next unit bonus.|Keep your :nombre-day streak going to unlock your next unit bonus.',
    'continuer_a_verifier' => 'Keep checking',
];
