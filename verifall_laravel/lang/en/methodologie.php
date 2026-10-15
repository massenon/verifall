<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page: Our methodology
|--------------------------------------------------------------------------
|
| ── THE PAGE THAT MUST NOT BE SOFTENED IN TRANSLATION ────────────────────
|
| This is where the platform states what it cannot do. Three limits are named
| in plain words: no video deepfake detection, no judgement on whether a text
| was machine-written, no first-appearance date for an image.
|
| A translation that rounded those edges would be worse than no translation.
| The whole point of the section is that a blank reads as a green light, and
| the English says so in as many words.
|
| The verdict "unverifiable" is described as a RESULT, never as a failure and
| never as "half true". That distinction is the one most often lost when this
| page is summarised, and it is preserved deliberately here.
|
| No figure is written into this file: the steps, weights and thresholds are
| read from the application configuration and rendered around these strings.
|
*/

return [
    'signal_jamais_verdict' => 'signal, never a verdict',

    // The three paragraphs below carry thresholds that come from the service:
    // they arrive as parameters, never hard-coded. The <strong> stays inside
    // the value so each language can place the emphasis where its own syntax
    // needs it — splitting the sentence around the bold would freeze French
    // word order into English.
    'en_dessous_de_n_avis_exploitables' => 'Below <strong>:nombre</strong> usable opinions, no verdict is manufactured. The report is still issued — a blank screen would be worse — but it states that it is partial, and the units spent are refunded.',
    'un_verdict_n_est_retenu_que_s_il' => 'A verdict is only accepted if it leads the next one by at least <strong>:ecart points</strong> and reaches <strong>:score out of 100</strong>. Without the first threshold we would be calling a coin toss; without the second we would be asserting on almost nothing.',
    'quand_les_moteurs_sont_satures' => 'When the engines are saturated the analysis is not lost: it is rescheduled about <strong>:minutes minutes</strong> later and the person is told. Each account is also capped at <strong>:analyses analyses per hour</strong>, so that heavy use does not drain everyone else\'s quota.',

    'consulter_le_journal_des_corrections' => 'Read the corrections log',
    'cette_page_applique_les_exigences_de_transparence' => 'This page follows the transparency requirements of the International Fact-Checking Network code of principles. Vérif\'All is not a signatory to date.',
    'nos_ressources_et_leur_origine_sont_detaillees' => 'Our resources and where they come from are set out on the',
    'financement' => 'funding page',

    'notre_methodologie' => 'Our methodology',

    'comment_une_affirmation_devient_un_verdict_les' => 'How a claim becomes a verdict: the steps, the engines consulted, the weight of each, and the cases where we decline to rule.',

    'les_chiffres_de_cette_page_sont_lus' => "The figures on this page are read from the application's configuration. They change with it.",

    'etat_actuel_des_moteurs' => 'Current state of the engines',

    'les_moteurs_d_analyse_decrits_ci_dessous' => 'The analysis engines described below currently run in <strong>simulated</strong> mode: no access key to third-party services is configured on this instance yet. The verdicts they produce must therefore not be taken as checks. Switching to the real engines changes neither the steps, nor the weights, nor the thresholds set out on this page.',

    'le_parcours_d_une_verification' => 'The path of a check',

    'six_etapes_toujours_les_memes_dans_cet' => 'Six steps, always the same, in this order. They are marked as they are passed: if the analysis stalls, the display stalls with it.',

    'le_poids_de_chaque_moteur' => 'The weight of each engine',

    'tous_les_moteurs_ne_se_valent_pas' => 'Not all engines are equal. A fact-check already published by a newsroom is <strong>evidence</strong>; a verdict from a language model is an <strong>inference</strong>. On a fact-checking platform, evidence comes first, otherwise we would be no more than one more chatbot.',

    'les_moteurs_marques_signal_sont_ecartes_du' => 'Engines marked “signal” are left out of the verdict calculation: they inform without ruling. A generated-content detector says a text looks machine-produced, not that it is false.',

    'les_scores_sont_normalises_sans_cela_une' => 'Scores are normalised: without that, an analysis querying more engines would always look more certain than another.',

    'quand_nous_repondons_non_verifiable' => 'When we answer “unverifiable”',

    'non_verifiable_n_est_pas_un_echec' => '“Unverifiable” is not a failure: it is a result, and the only honest one when the evidence is missing. Without it, a system would be pushed into ruling at random. Three situations lead to it.',

    'trop_peu_de_moteurs_ont_repondu' => 'Too few engines answered',
    'le_verdict_de_tete_ne_se_detache' => 'The leading verdict does not stand out',
    'les_moteurs_se_contredisent' => 'The engines contradict each other',

    'quand_deux_theses_opposees_tiennent_la_tete' => 'When two opposing readings share the lead, the report goes to <strong>human arbitration</strong> and shows both, with their sources. One case is treated differently: when the two readings in play are “true” and “false”, that is the signature of a partly accurate claim presented misleadingly, the verdict becomes “misleading”, and it too passes before a human.',

    'ce_qui_declenche_une_relecture_humaine' => 'What triggers a human review',

    'une_contradiction_entre_les_moteurs_dans_les' => 'A <strong>contradiction</strong> between engines, under the conditions described above.',

    'un_controle_de_coherence_qui_echoue_le' => 'A <strong>consistency check</strong> that fails: the proposed verdict is contradicted by most of the evidence gathered. A report whose own sources refute its verdict is never published as it stands.',

    'une_publication_au_nom_de_la_redaction' => 'A <strong>publication in the newsroom’s name</strong>: a verdict written by the team and served in place of an analysis requires a written analysis and at least one verifiable source.',

    'ce_que_nous_ne_savons_pas_faire' => 'What we cannot do',

    'ce_qu_on_ne_mesure_pas_ne' => 'What we do not measure, we do not display. Here are the three limits we know of, written down rather than left to be guessed at, because a blank reads as a green light.',

    'l_hypertrucage_video_n_est_pas_detecte' => '<h3 class="text-lg font-display font-bold text-heading mb-2">Video deepfakes are not detected</h3> <p class="text-body/85 leading-relaxed mb-0"> Detecting a doctored video requires frame-by-frame analysis, beyond what our hosting allows. A video is handled as an image plus a soundtrack: reverse search on thumbnails, transcription, and a reading of the metadata. That is useful, since most misleading videos are older videos put back in a new context rather than forgeries, but it is not deepfake detection. Synthetic voice detection is not covered either. </p>',

    'nous_ne_nous_prononcons_pas_sur_l' => '<h3 class="text-lg font-display font-bold text-heading mb-2">We do not rule on where a text came from</h3> <p class="text-body/85 leading-relaxed mb-0"> Detecting AI-generated text is unreliable, and it fails asymmetrically: it produces false positives on writing by non-native speakers and on student work, which is to say on part of our audience. Wrongly accusing someone of having had a machine write their text is a real harm, caused by a tool presented as help. A machine-written text may in any case be accurate, and a human-written one may be false. </p>',

    'nous_ne_datons_pas_la_premiere_apparition' => '<h3 class="text-lg font-display font-bold text-heading mb-2">We do not date the first appearance of an image</h3> <p class="text-body/85 leading-relaxed mb-0"> We can say which pages an image appears on, and which is the oldest we have found. That is not the same as a first-appearance date, and we will not write it as though it were. Yet that is precisely the question in image fact-checking: a photograph from 2018 republished as current news remains the most common form of disinformation. </p>',

    'quand_nous_nous_trompons' => 'When we get it wrong',

    'un_verdict_qui_change_ne_disparait_pas' => 'A verdict that changes does not disappear: the previous one stays published, with its date and the reason for the change. No correction can be recorded without a written reason.',
];
