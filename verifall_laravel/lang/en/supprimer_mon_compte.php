<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page /supprimer-mon-compte (Account deletion)
|--------------------------------------------------------------------------
|
| Public page describing the account deletion procedure, the data erased
| and kept, and the cooling-off period.
|
| The :jours token is replaced by SuppressionCompteService::JOURS_RETRACTATION.
|
*/

return [
    'titre_page' => 'Delete my account',
    'derniere_mise_a_jour' => 'Last updated:',

    'intro' => 'Vérif\'All guarantees your right to erasure, as provided by Benin\'s Digital Code Act No. 2017-20. You can delete your account at any time from the app, from the website, or by writing to us if you have lost access to your account.',

    '1_depuis_l_application' => '1. From the mobile app',
    'depuis_l_application_intro' => 'On iOS and Android, the deletion procedure is in the account settings:',
    'depuis_l_app_etape_1' => 'Open the app and go to <strong>Settings</strong>.',
    'depuis_l_app_etape_2' => 'Tap <strong>My account</strong>, then <strong>Delete my account</strong>.',
    'depuis_l_app_etape_3' => 'Enter your password and type <strong>DELETE</strong> in the confirmation field.',
    'depuis_l_app_etape_4' => 'A code is sent to your e-mail address. Enter it to confirm.',
    'depuis_l_app_resultat' => 'Your account is closed immediately. Your personal data is anonymised after a cooling-off period of :jours days (see below).',

    '2_depuis_le_site_web' => '2. From the website',
    'depuis_le_web_intro' => 'The procedure is identical from your citizen area:',
    'depuis_le_web_etape_1' => 'Sign in and go to <strong>Settings</strong>.',
    'depuis_le_web_etape_2' => 'Open <strong>Security</strong> and click <strong>Delete account</strong>.',
    'depuis_le_web_etape_3' => 'Enter your password and type <strong>DELETE</strong> in the confirmation field.',
    'depuis_le_web_etape_4' => 'A code is sent to your e-mail address. Enter it to confirm.',

    '3_ce_qui_est_efface_et_conserve' => '3. What is erased and what is kept',
    'ce_qui_est_efface_conserve_intro' => 'Deletion is not a complete erasure of all records. Here is exactly what happens:',
    'fermeture_immediate' => 'Immediate closure',
    'fermeture_immediate_texte' => 'Your account is closed immediately: you can no longer sign in, and your personal data is no longer accessible.',
    'apres_jours_jours' => 'After :jours days',
    'apres_jours_jours_texte' => 'Your personal data is irreversibly anonymised: name, surname, e-mail address, profile photo, biography, login history.',
    'ce_qui_est_conserve' => 'What is kept (anonymised)',
    'ce_qui_est_conserve_texte' => 'Your published checks, your Forum contributions and your comments remain visible — their author is replaced by "Deleted user". This content is part of the public fact-checking archive and cannot be removed.',

    '4_annuler_la_suppression' => '4. Cancelling the deletion',
    'annuler_intro' => 'You have :jours days to change your mind.',
    'annuler_texte' => 'A cancellation link is sent to you as soon as your account is closed. This link is valid for :jours days. Once the period has passed, the anonymisation is irreversible and your account can no longer be restored.',

    '5_sans_acces_au_compte' => '5. No longer have access to your account?',
    'sans_acces_intro' => 'If you have lost access to your account (forgotten password, lost device), you can request deletion by e-mail. Enter the e-mail address registered on your account.',
    'champ_email_label' => 'E-mail address',
    'champ_email_placeholder' => 'your@address.com',
    'bouton_envoyer' => 'Send the deletion link',
    'message_confirmation' => 'If this address is linked to an account, a deletion link valid for 24 hours has just been sent. Check your inbox.',
    'note_securite' => 'This form is limited to 3 submissions per hour. If you do not receive an e-mail, check your spam folder or write to',

    'compte_ferme_confirmation' => 'Your account has been closed. An e-mail with your cancellation link has just been sent. You have 30 days to change your mind.',
    'suppression_deja_en_cours' => 'A deletion is already scheduled for this account. Check your inbox for the cancellation link.',

    'footer_confidentialite' => 'See also our',
    'footer_lien_confidentialite' => 'privacy policy',
    'footer_et' => 'and our',
    'footer_lien_mentions' => 'legal notice',
    'footer_point' => '.',
];
