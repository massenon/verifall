<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: my referrals
|--------------------------------------------------------------------------
|
| ── THE RULE THAT MAKES THIS PROGRAMME HONEST ────────────────────────────
|
| "Signing up on its own triggers nothing: what counts is real use, and that
| is what protects the scheme from fake accounts."
|
| Two things are said at once, and both are needed. It tells the referrer why
| their counter has not moved, so the delay reads as a rule rather than a
| fault. And it tells anyone considering gaming the programme that there is
| nothing to gain from creating accounts, before they try.
|
| Rewards land on the FIRST CHECK, for both people. The English keeps the
| trigger named on both sides rather than saying "when they join".
|
| The anti-abuse paragraph names what is actually barred (self-referral, fake
| accounts, multiple accounts, spam) instead of hiding behind "abuse". A rule
| you can be sanctioned under should be a rule you can read.
|
| "Vérif'All" is the platform's name and never translates.
|
*/

return [
    'mon_parrainage' => 'My referrals',

    'partagez_verif_all_faites_decouvrir_la_verification' => 'Share Vérif\'All. Introduce people to reliable checking. Gain together.',

    'resume_du_parrainage_expand_more' => '<span class="text-sm font-display font-bold text-ink">Referral summary</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',

    'invitations_ouvertes' => 'Invitations open',
    'inscrits' => 'Signed up',
    'actifs' => 'Active',
    'unites_gagnees' => 'Units earned',

    'votre_invitation' => 'Your invitation',
    'partagez_ce_lien_pour_inviter_vos_proches' => 'Share this link to invite the people around you. It is personal and tied to your account.',

    'code_de_parrainage' => 'Referral code',
    'votre_qr_code' => 'Your QR code',
    'scannez_pour_decouvrir_verif_all' => 'Scan to discover Vérif\'All',

    'recompense_actuelle' => 'Current reward',
    'lorsque_votre_filleul_realise_sa_premiere_verification' => 'When the person you invited runs their <strong class="text-ink">first check</strong>:',

    'vous' => '🎁 You',
    'creditees_des_que_votre_filleul_realise_sa' => 'credited as soon as the person you invited runs their first check.',

    'votre_filleul' => '🎁 The person you invited',
    'creditees_sur_sa_premiere_verification_en_plus' => 'credited on their first check, on top of their welcome bonus.',

    'l_inscription_seule_ne_declenche_rien_c' => 'Signing up on its own triggers nothing: what counts is real use, and that is what protects the scheme from fake accounts.',

    'comment_fonctionne_le_parrainage' => 'How do referrals work?',
    'quatre_etapes_simples_pas_de_formulaire_complique' => 'Four simple steps. No complicated form.',

    'mes_invitations' => 'My invitations',
    'suivez_simplement_l_etat_de_vos_invitations' => 'Simply follow where your invitations stand.',

    'personne_n_a_encore_rejoint' => 'Nobody has joined yet',
    'partagez_votre_lien_chaque_personne_qui_verifie' => 'Share your link: every person who checks before sharing is one rumour fewer going round.',

    'historique_des_recompenses' => 'Reward history',
    'aucune_recompense_pour_l_instant_la_premiere' => 'No reward yet. The first one arrives as soon as someone you invited runs their first check.',

    'votre_progression_vers_le_prochain_bonus' => 'Your progress towards the next bonus',

    'pourquoi_inviter_vos_proches' => 'Why invite the people around you?',

    'partagez_aussi_autour_de_vous_votre_qr' => '<strong class="text-ink">Share it in person too:</strong> your QR code can be used at a training session, a workshop, a meeting or a community event.',

    'questions_frequentes' => 'Frequently asked questions',

    'un_programme_concu_pour_etre_transparent' => 'A programme built to be transparent',

    'les_recompenses_sont_attribuees_automatiquement_lorsque_les' => 'Rewards are granted automatically when the eligibility conditions are met. Vérif\'All may withhold, suspend or cancel a reward where activity appears fraudulent, automated or contrary to the rules of the programme (self-referral, fake accounts, multiple accounts, spam).',

    'partager_mon_invitation' => 'Share my invitation',
    'invitez_quelqu_un_a_decouvrir_verif_all' => 'Invite someone to discover Vérif\'All.',
    /*
     | Second pass. Two points deserve a note.
     |
     | The "Copy" button changes its text after the click, through an Alpine
     | `x-text` that carried both labels HARDCODED in the attribute. They now
     | go through `@js(__())`: the string stays correctly escaped for
     | JavaScript, exactly as the neighbouring `@js($lien)` already did.
     |
     | The fallback between the tags is kept: without it the button is empty
     | until Alpine boots.
     |
     | "Parrainage" is rendered as "referral", the term the programme uses in
     | English; the French word carries a mentoring sense this feature has not.
     */
    'fil_d_ariane' => 'Breadcrumb',
    'vous_avez_ete_parraine_par' => 'You were referred by',
    'un_membre' => 'a member',
    'unites' => 'units',
    'n_unites' => ':count unit|:count units',
    'unites_recues' => 'units received',
    'unites_vous_attendent' => 'units are waiting for you',
    'lancer_ma_premiere_verification' => 'Run my first check',
    'votre_lien_d_invitation' => 'Your invitation link',
    'copie' => 'Copied',
    'copier' => 'Copy',
    'copier_le_lien' => 'Copy the link',
    'qr_code_de_votre_lien_d_invitation' => 'QR code for your Vérif\'All invitation link',
    'code_capitales' => 'CODE:',
    'inscription' => 'Sign-up',
    'premiere_verification' => 'First check',
    'a_realiser' => 'still to do',
    'recompense' => 'Reward',
    'en_attente' => 'Pending',
    'invitations_eligibles_plus_que' => 'eligible invitations — only',
    'pour_debloquer_le_prochain_palier' => 'to go before the next tier.',
    'invitation_verif_all' => 'Vérif\'All invitation',
    'e_mail' => 'Email',
    'partager_avec_mon_telephone' => 'Share from my phone',

    'fermer' => 'Close',
];
