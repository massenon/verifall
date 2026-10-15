<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: dashboard
|--------------------------------------------------------------------------
|
| ── THE EMPTY STATES MATTER MORE THAN THE REST ───────────────────────────
|
| A new account sees an empty dashboard: no checks, no course, no forum
| thread. Those sentences are what tells someone the platform is working and
| waiting, rather than broken.
|
| Each of them therefore says what is missing AND what to do about it. "The
| forum has no thread yet. Yours could be the first." does both in one line,
| and the English keeps both halves.
|
| The `expand_more` ligature inside the accordion heading is an ICON
| IDENTIFIER, not a word: it stays as it is.
|
*/

return [
    'apercu_de_votre_activite_expand_more' => '<span class="font-display font-bold text-ink">Your activity at a glance</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',

    'verifications' => 'Checks',
    'cours_suivis' => 'Courses taken',
    'points_d_experience' => 'Experience points',
    'unites_disponibles' => 'Units available',

    'priorites' => 'Priorities',
    'a_traiter_maintenant' => 'To deal with now',

    'tout_est_a_jour_continuez_votre_serie' => '✓ Everything is up to date, keep your streak going!',

    'chaque_mois_un_cours_gratuit_mis_en' => 'Every month, one free course put forward for the whole community. One click to enrol.',

    'continuer_votre_apprentissage' => 'Carry on learning',

    'il_reste_un_quiz_ou_un_exercice' => 'A quiz or an exercise is still waiting to be completed.',

    'commencez_votre_premier_cours' => 'Start your first course',

    'le_clom_introduction_au_fact_checking_est' => 'The “Introduction to fact-checking” MOOC is free and takes only 42 minutes.',

    'mon_activite_sur_30_jours' => 'My activity over 30 days',

    'verifications_recentes' => 'Recent checks',

    'prochaine_live_class' => 'Next live class',

    'aucune_seance_programmee_pour_l_instant_les' => 'No session scheduled for now. Upcoming dates will be announced here.',

    'ca_discute_sur_le_forum' => 'People are talking on the forum',

    'le_forum_n_a_pas_encore_de' => 'The forum has no thread yet. Yours could be the first.',

    'unites_restantes' => 'Units left',
    '1_unite_1_verification_par_ia' => '1 unit = 1 AI check.',

    'vos_recompenses' => 'Your rewards',

    'votre_premier_reflexe_fact_checking' => 'Your first fact-checking reflex',
    'me_rappeler_plus_tard' => 'Remind me later',
    'valider' => 'Confirm',

    'verifiez_votre_premiere_info' => 'Check your first piece of information',
    'un_lien_un_texte_une_image_ou' => 'A link, a text, an image or an audio file',

    'une_rumeur_recue_sur_whatsapp_un_article' => 'A rumour received on WhatsApp, an article that looks doubtful, a screenshot with no source: paste it in and our analysis answers you with its sources.',

    'plus_tard' => 'Later',
    'lancer_une_verification' => 'Start a check',

    // ── TWO COUNTS THAT COULD NOT BE TRANSLATED BEFORE ───────────────────────
    //
    // The streak read "N jour(s)" and the forum line "N réponse(s)", both built
    // by Str::plural() on a French word. English needs "replies", not
    // "réponses" with an s bolted on, so both are trans_choice keys now.
    //
    // 'format_date_live_class' is a Carbon format, not a sentence: "\à" was a
    // backslash-escaped French "at". The English escapes "\a\t" the same way so
    // Carbon prints the word instead of reading a/t as format tokens.
    'bonjour' => 'Hello',
    'votre_boussole_de_l_information_est_active' => 'Your information compass is live. Current streak:',
    'compte_jours' => 'day|days',
    'serie_en_cours_revenez_demain' => 'Streak running — come back tomorrow to keep it',
    'serie_interrompue_une_verification_la_relance' => 'Streak broken — one check restarts it',
    'verifier_un_contenu' => 'Check a piece of content',
    'acheter_des_unites' => 'Buy units',
    'voir_la_file' => 'See the queue',

    'offre_du_mois' => 'Offer of the month —',
    'cours_offert_du_mois' => 'Free course this month:',
    's_inscrire_gratuitement' => 'Enrol for free',

    'prochaine_lecon' => 'Next lesson:',
    'reprendre' => 'Resume',
    'decouvrir_le_catalogue' => 'Browse the catalogue',

    'verifications_et_lecons' => 'Checks & lessons',
    'activite_des_30_derniers_jours' => 'Activity over the last 30 days',
    'legende_verifications' => 'Checks',
    'legende_lecons_terminees' => 'Lessons finished',

    'tout_voir' => 'See all',
    'en_cours' => 'IN PROGRESS',
    'aucune_verification_pour_le_moment' => 'No checks yet.',
    'commencez_par_un_lien_ou_un_texte' => 'Start with a link or a piece of text.',

    'animation_verif_all' => 'Hosted by Vérif\'All',
    'format_date_live_class' => 'D j M \a\t H:i',
    'voir_les_live_class' => 'See the Live Classes',

    'compte_reponses' => ':nombre reply|:nombre replies',
    'compte_supprime' => 'Deleted account',
    'rejoindre_le_forum' => 'Join the forum',

    'historique_complet' => 'Full history',
    'abreviation_jour' => 'd',

    '1_question_30_secondes' => '1 question · 30 seconds ·',
    'campagne_en_cours' => 'Campaign running',
    'lire_l_annonce' => 'Read the announcement',
];
