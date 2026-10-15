<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: certificates and badges
|--------------------------------------------------------------------------
|
| "what you have proved you can do" sets the tone: badges here stand for
| demonstrated skill, not for time spent on the site. The English keeps the
| verb "proved" rather than softening it to "learned".
|
| A broken streak is stated without reproach, and immediately paired with the
| way back: one check today starts a new one. Gamification that scolds is
| gamification that loses the person it was meant to keep.
|
| The page title carries a BARE ampersand. It is rendered by {{ }}, which
| escapes on output: an entity written here would reach the screen verbatim
| ("Certificates &amp; Badges"). Same rule as notre_equipe.php.
|
*/

return [

    'd_ou_viennent_vos_xp' => 'Where your XP comes from',
    'points_anterieurs_au_journal' => 'Points earned before the ledger',
    'fil_d_ariane' => 'Breadcrumb',
    'dashboard' => 'Dashboard',
    'certificats_amp_badges' => 'Certificates & Badges',

    'votre_niveau_vos_series_et_ce_que' => 'Your level, your streaks, and what you have proved you can do.',

    'votre_progression' => 'Your progress',

    'vue_d_ensemble_expand_more' => '<span class="text-sm font-display font-bold text-ink">Overview</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',

    'niveau' => 'Level',
    'xp_cumules' => 'Total XP',
    'badges' => 'Badges',
    'certificats' => 'Certificates',

    'encore' => 'Another',
    'xp_avant_le_niveau_suivant' => 'XP to the next level',
    'prochaine_etape' => 'Next step',
    'badge_nomme' => '🎯 « :nom » badge',
    'continuer_un_tutoriel' => 'Continue a tutorial',
    'votre_serie' => 'Your streak',
    'votre_derniere_serie' => 'Your last streak',
    'jours_consecutifs' => ':count day in a row|:count days in a row',
    'plus_que_jours_avant_unites' => 'Only :count day to go before +:unites units.|Only :count days to go before +:unites units.',
    'jours_d_affilee' => ':count day in a row|:count days in a row',

    'tous_les_badges_sont_debloques' => '🎉 Every badge is unlocked',
    'il_ne_reste_rien_a_decrocher_continuez' => 'There is nothing left to earn, carry on for the rest of the path.',

    'serie_interrompue_une_verification_aujourd_hui_en' => 'Streak broken. One check today starts a new one.',

    'vos_badges' => 'Your badges',

    'verifier_mes_badges_verification' => '<span wire:loading.remove wire:target="verifierMesBadges">Check my badges</span> <span wire:loading wire:target="verifierMesBadges">Checking…</span>',

    'debloque' => '✓ Unlocked',
    'unites' => 'units',
    'vos_certificats' => 'Your certificates',
    'delivre_le' => 'Issued on',
    'code' => 'code',
    'voir_le_certificat' => 'View the certificate',
    'voir_le_catalogue' => 'View the catalogue',
    'certificat_authentifie' => '✓ Certificate authenticated',

    'aucun_certificat_pour_l_instant' => 'No certificate yet',
    'terminez_un_cours_de_l_academie_pour' => 'Finish an Academy course to earn one.',

    'activite_recente' => 'Recent activity',
    'rien_encore_votre_premiere_lecon_terminee_ouvrira' => 'Nothing yet. Your first finished lesson will open this list.',
];
