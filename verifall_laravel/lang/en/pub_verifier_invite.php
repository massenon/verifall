<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Public front, "Check before you share" (visitor with no account)
|--------------------------------------------------------------------------
|
| The form STAYS on screen and the result appears below it.
|
| ── THE PLURALS ──────────────────────────────────────────────────────────
| Three keys go through `trans_choice()`: `verifications_gratuites_restantes`,
| `il_vous_reste_verifications_gratuites` and `dans_minutes`. The view used to
| agree by hand, repeating `{{ $n > 1 ? 's' : '' }}` on EVERY word of the
| phrase — "verification/s gratuite/s restante/s". That trick does not survive
| translation: English does not inflect the adjective, and "remaining" never
| takes an "s". Each language therefore carries its own two complete forms.
|
| ── WHAT IS SAID, AND WHY ────────────────────────────────────────────────
| `analyse_mise_en_attente` announces a WAIT, never a failure. Telling
| somebody who is discovering the product that the verdict is "unavailable"
| would say the platform is broken, when it is only waiting for the engines to
| free up. "Queued", not "failed".
|
| The five bullets under `le_dossier_complet_vous_attend` name PRECISELY what
| sits behind signing up. We are not hiding the ANSWER, only the file that
| supports it: a vague promise would suggest we were withholding the verdict,
| which would be both untrue and unfair.
|
| `sans_compte_lien_ou_texte_uniquement` is stated PLAINLY rather than left to
| be discovered: nobody should hunt for a file-upload button that does not
| exist on this page.
|
*/

return [
    'verifiez_avant_de' => 'Check before you',
    'partager' => 'share',
    'collez_un_lien_ou_un_texte_pour' => 'Paste a link or a text for an instant analysis:',
    'faux_deces_faux_coups_d_etat_faux' => 'fake deaths, fake coups, fake job offers, health scams…',
    'type_de_contenu_a_verifier' => 'Type of content to check',
    'lien_url' => 'URL link',
    'texte_message' => 'Text / Message',
    'collez_un_url_douteux' => 'Paste a suspicious url *',
    'collez_le_message_douteux' => 'Paste the suspicious message *',
    'copiez_ici_le_message_whatsapp_la_publication' => 'Paste the WhatsApp message, the post or the rumour here…',
    'lancer_la_verification_gratuite' => 'Start the free check',
    'analyse_lancee' => 'Analysis started…',
    'verifications_gratuites_restantes' => ':count free check remaining, no sign-up needed.|:count free checks remaining, no sign-up needed.',
    'vous_avez_utilise_vos_verifications_gratuites' => 'You have used up your free checks.',
    'creez_un_compte' => 'Create an account',
    'pour_continuer' => 'to carry on.',
    'sans_compte_lien_ou_texte_uniquement' => 'Without an account: link or text only.',
    'l_analyse_d_images_de_videos_et_d' => 'Analysing images, video and audio requires',
    'un_compte' => 'an account',
    'reception_du_contenu' => 'Receiving the content',
    'votre_soumission_est_enregistree' => 'Your submission has been recorded.',
    'analyse_mise_en_attente' => 'Analysis queued',
    'plutot_que_de_vous_rendre_un_verdict' => 'Rather than hand you a verdict nothing supports, we are re-running the analysis',
    'des_maintenant' => 'right now.',
    'dans_minutes' => 'in :count minute.|in :count minutes.',
    'revenez_sur_cette_page_dans_une_dizaine' => 'Come back to this page in about ten minutes: your request will be waiting for you.',
    'relancer_maintenant' => 'Re-run now',
    'relance' => 'Re-running…',
    'l_analyse_prend_plus_de_temps_que' => 'The analysis is taking longer than expected.',
    'un_moteur_ne_repond_peut_etre_pas' => 'One of the engines may not be responding.',
    'reessayer' => 'Try again',
    'ne_fermez_pas_cette_page_sans_compte' => 'Do not close this page: without an account, your report will not be kept.',
    'indice_de_fiabilite' => 'Reliability score',
    'le_dossier_complet_vous_attend' => 'The full file is waiting for you',
    'les_moteurs_mobilises_et_l_avis_de' => 'the :nombre engines brought in, and what each of them concluded',
    'les_sources_consultees_avec_leur_position_et' => 'the :nombre sources consulted, with their stance and their reliability',
    'l_indice_de_discours_haineux_du_contenu' => 'the hate-speech index of the content submitted',
    'l_analyse_des_images_videos_et_fichiers_audio' => 'the analysis of images, video and audio files',
    'l_historique_de_vos_verifications_et_le_rapport' => 'the history of your checks, and the report as a PDF',
    'creer_un_compte_gratuit' => 'Create a free account',
    'il_vous_reste_verifications_gratuites' => 'You have :count free check left:|You have :count free checks left:',
    'le_formulaire_est_reste_en_haut_de' => 'the form is still at the top of the page.',
    'besoin_d_analyse_complete' => 'Need a full analysis?',
    'en_creant_un_compte_vous_debloquez_l' => 'By creating an account, you unlock the analysis of images, video and audio files, and access to the fact-checking tools.',
    'creer_un_compte' => 'Create an account',
];
