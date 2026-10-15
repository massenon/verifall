<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shared components, citizen and public
|--------------------------------------------------------------------------
|
| Seventeen components share this namespace, so every key carries its
| component prefix. That is not tidiness for its own sake: neighbouring
| components reuse the same word for different acts. "Join" on a Live Class
| opens a video room; the same verb elsewhere would mean something else
| entirely, and a shared key would have silently merged them.
|
| ── PLURALS ──────────────────────────────────────────────────────────────
|
| The French views build plurals by appending "s" to the end of the group
| ("3 avis exprimes"). English cannot: the "s" lands on the noun, not at the
| end. Each plural therefore has two keys and the view picks one. Anything
| else produces "3 opinion expresseds".
|
| ── WORD ORDER ───────────────────────────────────────────────────────────
|
| "Plus que {n} jours" becomes "Only {n} days left": the word that closes the
| French phrase opens the English one. `offre_jour` / `offre_jours` therefore
| carry "day left" / "days left", not a bare noun. The countdown reads
| correctly in both languages without touching the template's structure.
|
| Material Symbols ligatures are absent by design — they are icon
| identifiers, and translating one renders an empty square.
|
*/

return [
    // components/auth/boutons-sociaux.blade.php
    'sociaux_ou_continuer_avec' => 'or continue with',
    'sociaux_en_continuant_vous_acceptez_nos' => 'By continuing, you accept our',
    'sociaux_conditions_d_utilisation' => 'terms of use',
    'sociaux_et_notre' => 'and our',
    'sociaux_politique_de_confidentialite' => 'privacy policy',

    // components/data-table.blade.php
    'table_resultats' => 'result(s)',
    'table_tout_selectionner' => 'Select all',

    // components/delete-modal.blade.php
    'modale_supprimer_avant' => 'Delete “',
    'modale_supprimer_apres' => '”?',
    'modale_cette_action_est_definitive' => 'This action is permanent.',
    'modale_annuler' => 'Cancel',
    'modale_supprimer_definitivement' => 'Delete permanently',

    // components/evaluation/action-quiz.blade.php
    'quiz_voir_le_cours' => 'View the course',
    'quiz_reessayer' => 'Try again',
    'quiz_passer_le_quiz' => 'Take the quiz',
    'quiz_s_inscrire_au_cours' => 'Enrol in the course',

    // components/evaluation/badge-quiz.blade.php
    'badge_reussi' => 'PASSED',
    'badge_a_refaire' => 'RETAKE',
    'badge_a_passer' => 'TO TAKE',
    'badge_disponible_apres_inscription_au_cours' => 'Available once you enrol in the course',
    'badge_verrouille' => 'LOCKED',

    // components/forum/carte-sujet.blade.php
    'sujet_compte_supprime' => 'Deleted account',
    'sujet_sujet_epingle' => 'Pinned thread',
    'sujet_epingle' => 'Pinned',
    'sujet_verif_all_confirme' => '✓ VÉRIF\'ALL CONFIRMS —',
    'sujet_avis_de_la_communaute' => 'Community opinion',
    'sujet_avis_exprime' => 'opinion expressed',
    'sujet_avis_exprimes' => 'opinions expressed',
    'sujet_source_signalee' => 'Reported source:',
    'sujet_fil_clos' => 'Thread closed',

    // components/hub/aucune-opportunite.blade.php
    'hub_aucune_opportunite_ne_correspond' => 'No opportunity matches',
    'hub_essayez_une_autre_recherche_ou_une_autre' => 'Try another search, or another kind.',
    'hub_reinitialiser_les_filtres' => 'Reset the filters',
    'hub_nous_ne_publions_que_des_opportunites' => 'We only publish opportunities whose organisation and application channel have been checked. None is right now — come back soon.',

    // components/hub/carte-opportunite.blade.php
    'offre_verifiee' => 'Verified',
    'offre_sans_date_limite_annoncee' => 'No closing date announced.',
    'offre_date_limite_depassee' => 'Closing date passed — this listing will be withdrawn shortly.',
    'offre_dernier_jour_pour_candidater' => 'Last day to apply.',
    'offre_plus_que' => 'Only',
    'offre_jour' => 'day left',
    'offre_jours' => 'days left',
    'offre_jusqu_au' => '— until',
    'offre_aucun_frais_de_dossier_jamais' => 'No application fee, ever.',
    'offre_voir_l_opportunite' => 'View the opportunity',

    // components/infolettre-abonnement.blade.php
    'infolettre_titre' => 'The Monday newsletter',
    'infolettre_chapo' => 'Every Monday, a round-up of the worst hoaxes detected in Benin during the week, each with its full fact-check and sources.',
    'infolettre_verifiez_votre_boite' => 'Thank you. If this address can receive the newsletter, a confirmation message has just been sent to it; one click will be enough.',
    'infolettre_confirmee' => 'Subscription confirmed. Your first round-up will arrive on Monday.',
    'infolettre_desabonnee' => 'You are unsubscribed. You will no longer receive the newsletter, and nothing else is required of you.',
    'infolettre_lien_invalide' => 'This link is no longer valid. Your mail client may have cut it short: try again by copying it in full.',
    'infolettre_adresse_email' => 'Email address *',
    'infolettre_placeholder_email' => 'name@example.com',
    'infolettre_je_m_abonne' => 'Subscribe',
    'infolettre_mentions' => 'One email a week, never more. A one-click unsubscribe link appears in every message, with no password and no sign-in. Your address serves that purpose alone and is passed to no one.',

    // components/layouts/certificat.blade.php
    'certificat_titre_page' => 'Verify a certificate — Vérif\'All',
    'certificat_meta_description' => 'Publicly verify the authenticity of a Vérif\'All certificate using its unique identifier and digital signature.',
    'certificat_og_description' => 'Public authenticity check for Vérif\'All certificates.',
    'certificat_se_connecter' => 'Sign in',
    'certificat_tous_droits_reserves' => 'All rights reserved.',

    // components/live-class/carte.blade.php
    'live_en_ce_moment' => 'Right now',
    'live_en_direct' => 'LIVE',
    'live_planifiee' => 'SCHEDULED',
    'live_terminee' => 'ENDED',
    'live_seance_ouverte' => 'Open session',
    'live_animee_par' => '· hosted by',
    'live_l_equipe_verif_all' => 'the Vérif\'All team',
    'live_rejoindre_maintenant' => 'Join now',
    'live_ajouter_au_calendrier' => 'Add to calendar',
    'live_rejoindre' => 'Join',
    'live_le_lien_s_active_15_minutes_avant' => 'The link opens 15 minutes before the start',
    'live_rejoindre_bientot' => 'Join shortly',
    'live_voir_le_replay' => 'Watch the replay',

    // components/lms/carte-cours.blade.php
    'cours_nouveau' => 'New',
    'cours_lecons' => 'lessons',
    'cours_gratuit' => 'FREE',
    'cours_offert_jusqu_au' => 'free until',
    'cours_premium' => 'PREMIUM ·',
    'cours_unites' => 'units',
    'cours_termine' => '✓ Completed',
    'cours_revoir' => 'Review',
    'cours_commencer' => 'Start',

    // components/mention-legale.blade.php
    'mention_non_communique' => 'Not disclosed',
    'mention_a_renseigner' => 'To be filled in:',
    'mention_reglages_onglet_general' => '(Settings, General tab)',

    // components/offcanvas-form.blade.php
    'offcanvas_formulaire' => 'Form',
    'offcanvas_fermer' => 'Close',

    // components/outil/carte.blade.php
    'outil_ideal_pour' => 'Ideal for',
    'outil_ouvrir_l_outil' => 'Open the tool',

    // components/skeleton.blade.php
    'skeleton_chargement_en_cours' => 'Loading',
    'skeleton_chargement_en_cours_points' => 'Loading…',

    /*
     | Rediffusion des Live Classes (lot 1, B-06).
     */
    'live_rediffusion_a_venir' => 'Recording coming soon',
    'quiz_terminez_les_lecons' => 'Finish the lessons first',
    'live_rediffusion_reservee' => 'For enrolled learners',
];
