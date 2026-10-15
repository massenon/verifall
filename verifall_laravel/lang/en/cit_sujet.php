<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: a forum thread
|--------------------------------------------------------------------------
|
| ── THE TWO SENTENCES THAT KEEP A FORUM AND A FACT-CHECKER APART ─────────
|
| "The official verdict commits Vérif'All. It appears above the community's
| opinion, and is not derived from it." Position on the page is not enough on
| its own: the sentence says the verdict does not FOLLOW from the vote, which
| is the thing a reader would otherwise assume from the layout.
|
| "This is a poll, not a check: it says what the community thinks, not what is
| true." Placed on the vote itself, where the confusion would happen.
|
| Escalation is free and allowed ONCE PER THREAD. Both facts are stated up
| front, because a limit discovered on the second attempt reads as a failure.
|
| Reporting removes nothing automatically: a human decides. Same wording as
| the community notes, on purpose. Two different sentences for one rule would
| suggest two different rules.
|
| A closed thread stays READABLE. Moderation ends the discussion; it does not
| erase what was said.
|
| The report button carries its own `wire:click` markup and is reproduced
| exactly: only the visible words change.
|
*/

return [
    'moderation' => 'Moderation',

    'verdict_officiel' => 'Official verdict',
    'publier_le_verdict' => 'Publish the verdict',

    'le_verdict_officiel_engage_verif_all_il' => 'The official verdict commits Vérif\'All. It appears above the community\'s opinion, and is not derived from it.',

    'votre_avis_sur_cette_information' => 'Your view on this information',

    'c_est_un_sondage_pas_une_verification' => 'This is a poll, not a check: it says what the community thinks, not what is true.',

    'escalade_en_fact_check' => 'Escalated to fact-check',
    'ce_signalement_a_ete_confie_a_nos' => 'This report has been handed to our analysis engines. The official verdict will appear on this thread as soon as it is delivered.',

    'faire_verifier_cette_information' => 'Have this information checked',
    'nos_moteurs_d_analyse_s_en_saisissent' => 'Our analysis engines take it up, and the official verdict comes back here. Free, and once per thread only.',

    'science_escalader_en_fact_check' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">science</span> Escalate to fact-check',

    'flag_signaler_ce_sujet' => '<button type="button" wire:click="ouvrirSignalement" class="inline-flex items-center gap-1 text-xs font-semibold text-slate2 hover:text-vfalse"> <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">flag</span> Report this thread </button>',

    'personne_n_a_encore_repondu_votre_message' => 'Nobody has replied yet. Your message will open the discussion.',

    'ce_fil_est_clos' => 'This thread is closed',
    'la_moderation_a_mis_fin_a_la' => 'Moderation has ended the discussion. The content remains readable.',

    'repondre_au_sujet_plutot' => '← Reply to the thread instead',
    'votre_reponse' => 'Your reply',
    'envoyer' => 'Send',

    'un_moderateur_examinera_ce_contenu_le_signalement' => 'A moderator will look at this content. Reporting removes nothing automatically: a human decides.',

    'motif' => 'Reason',
    'precisions_facultatif' => 'Details (optional)',
    'annuler' => 'Cancel',
    'envoyer_le_signalement' => 'Send the report',

    // ── THE REPLY COUNT WAS UNTRANSLATABLE BEFORE ────────────────────────────
    //
    // The heading was built as "réponse" + a conditional "s". That trick works
    // in French and breaks in English, where the plural of "reply" is
    // "replies", not "replys". It is a trans_choice key now, so each language
    // declares both forms itself.
    //
    // "Signaler ce sujet / cette réponse" was likewise assembled from a verb
    // and a French noun phrase chosen by a ternary. Split into three keys, the
    // English reads naturally in both branches.
    'fil_d_ariane' => 'Breadcrumb',
    'rouvrir_le_fil' => 'Reopen the thread',
    'clore_le_fil' => 'Close the thread',
    'desepingler' => 'Unpin',
    'epingler' => 'Pin',
    'choisir' => 'Choose…',
    'votre_avis' => 'Your view',
    'vous_avez_repondu' => 'You answered',
    'recliquez_pour_retirer_votre_avis' => 'Click again to withdraw your answer.',

    'escalade_par' => 'Escalated by',
    'le' => 'On',
    'verification' => 'Check',

    'compte_reponses' => ':nombre reply|:nombre replies',
    'compte_supprime' => 'Deleted account',
    'reponse_officielle' => 'Official reply',
    'repondre' => 'Reply',
    'signaler_cette_reponse' => 'Report this reply',

    'votre_reponse_a_ce_message' => 'Your reply to this message',
    'repondre_au_sujet' => 'Reply to the thread',
    'apportez_un_element_une_source_une_nuance' => 'Add a fact, a source, a nuance…',

    'signaler' => 'Report',
    'ce_sujet' => 'this thread',
    'cette_reponse' => 'this reply',
    'choisir_un_motif' => 'Choose a reason…',
    'ce_qui_vous_semble_problematique' => 'What strikes you as a problem',
];
