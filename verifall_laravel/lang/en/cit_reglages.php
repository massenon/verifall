<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: settings
|--------------------------------------------------------------------------
|
| ── "TYPE DELETE" IS CHECKED BY THE SERVER ───────────────────────────────
|
| The word on this screen is not decoration: `SuppressionCompteService`
| compares what is typed against it. The French screen asks for SUPPRIMER, so
| an English screen asking for DELETE would have been refused by the check
| until the service was taught to accept both. It now accepts either word in
| either language, which is why DELETE can be printed here honestly.
|
| Never change this word without changing `MOTS_DE_CONFIRMATION` with it. A
| screen that asks for a word the server rejects locks people out of deleting
| their own account, and the error they get would tell them to type what they
| already typed.
|
| ── THE OTHER SENTENCES THAT DO REAL WORK ────────────────────────────────
|
| Recovery codes: "they cannot be shown again, because we only keep a
| fingerprint of them". The reason is what makes the warning credible, and it
| is also true, so it stays.
|
| Account deletion names exactly what is erased (checks, course progress,
| certificates and units) rather than saying "your data". Someone about to
| lose a certificate they earned deserves to read that word before deciding.
|
| A failed data export says NO DATA WAS LOST. That is the first thing the
| person is afraid of, so it is answered first.
|
| The newsletter line says consent and withdrawal are both timestamped, and
| that unsubscribing is as easy as subscribing was. Under the Beninese digital
| code that is an obligation; written this way it also reads as a promise the
| reader can hold us to.
|
*/

return [
    'fil_d_ariane' => 'Breadcrumb',
    'dashboard' => 'Dashboard',
    'reglages' => 'Settings',
    'securite_notifications_confidentialite_et_gestion_du_compte' => 'Security, notifications, privacy and account management.',

    'securite' => '🔐 Security',
    'double_authentification_2fa' => 'Two-factor authentication (2FA)',
    'code_de_confirmation_demande_a_chaque_connexion' => 'A confirmation code is asked for at every sign-in.',
    'activer_desactiver_la_double_authentification' => 'Turn two-factor authentication on or off',
    'activer_desactiver' => 'Turn on or off',
    'sessions_actives' => 'Active sessions',
    'session_actuelle' => 'Current session',
    'deconnecter' => 'Sign out',
    'aucun_autre_appareil_connecte' => 'No other device signed in.',
    'changer_mon_mot_de_passe' => 'Change my password',

    'notifications' => '🔔 Notifications',
    'lettre_d_information' => 'Newsletter',
    'le_recap_mensuel_des_rumeurs_verifiees' => 'The monthly round-up of checked rumours.',
    'historique_du_hub' => 'History of my Hub reading',
    'historique_du_hub_aide' => 'Keeps what you read in the News Hub for 90 days, so you can find it again. Turning it off deletes the whole history.',
    'votre_accord_et_son_retrait_sont_horodates' => 'Your consent and its withdrawal are both timestamped: you can unsubscribe as easily as you subscribed.',

    'donnees_personnelles' => '🛡️ Personal data',
    'conformement_au_code_du_numerique_beninois_vous' => 'Under the Beninese digital code, you may export all of your data or ask for it to be deleted.',

    'exporter_mes_donnees_json_demande_en_cours' => '<span wire:loading.remove wire:target="exporterMesDonnees">Export my data (JSON)</span> <span wire:loading wire:target="exporterMesDonnees">Request in progress…</span>',

    'archive_en_preparation_vous_recevrez_un_courriel' => 'Archive being prepared. You will receive an e-mail as soon as it is ready.',
    'la_preparation_a_echoue_aucune_donnee_n' => 'Preparation failed. No data was lost: send the request again.',
    'votre_precedente_archive_a_expire_et_a' => 'Your previous archive expired and was destroyed. You can ask for a new one.',

    'politique_de_confidentialite' => 'Privacy policy',
    'archive_prete' => 'Archive ready',
    'ko' => 'KB',
    'expire' => 'expires',
    'telecharger_l_archive' => 'Download the archive',
    'zone_de_danger' => '⚠ Danger zone',
    'votre_compte_sera_ferme_immediatement_mais_vos' => 'Your account will be closed straight away, but your data will not be erased for <strong class="text-ink">:jours days</strong>: a cancellation link will be sent to you by e-mail.',
    'saisissez_le_code_a_6_chiffres_envoye' => 'Enter the 6-digit code sent to your address. Your account will be closed straight away, and your data erased in :jours days.',
    'la_suppression_du_compte_est_definitive_verifications' => 'Deleting the account is permanent: checks, course progress, certificates and units will all be erased.',
    'supprimer_mon_compte' => 'Delete my account',

    'mot_de_passe_actuel' => 'Current password',
    'nouveau_mot_de_passe_12_caracteres_min' => 'New password (12 characters min.)',
    'confirmer_le_nouveau_mot_de_passe' => 'Confirm the new password',
    'vos_autres_appareils_connectes_seront_deconnectes' => 'Your other signed-in devices will be signed out.',

    'annuler' => 'Cancel',
    'mettre_a_jour_mise_a_jour' => '<span wire:loading.remove wire:target="changerMotDePasse">Update</span> <span wire:loading wire:target="changerMotDePasse">Updating…</span>',

    'activer_la_double_authentification' => 'Turn on two-factor authentication',
    'scannez_ce_code_avec_google_authenticator_authy' => 'Scan this code with Google Authenticator, Authy or an equivalent, then enter the code it shows.',
    'code_a_6_chiffres' => '6-digit code',
    'confirmer' => 'Confirm',

    'vos_codes_de_recuperation' => 'Your recovery codes',
    'conservez_les_hors_de_votre_telephone_ils' => 'Keep them somewhere other than your phone. They are your only way back if you lose your app, and <strong class="text-ink">they cannot be shown again</strong>, because we only keep a fingerprint of them.',
    'je_les_ai_notes' => 'I have written them down',

    'desactiver_la_double_authentification' => 'Turn off two-factor authentication?',
    'votre_compte_ne_sera_plus_protege_que' => 'Your account will then be protected by its password alone. Confirm with your password and a current code.',
    'mot_de_passe' => 'Password',
    'code_en_cours_ou_code_de_recuperation' => 'Current code or recovery code',
    'desactiver' => 'Turn off',

    'supprimer_definitivement_le_compte' => 'Permanently delete the account?',
    'tapez_supprimer_et_confirmez_avec_votre_mot' => 'Type <strong class="text-ink">DELETE</strong> and confirm with your password. A code will then be sent to you by e-mail.',
    'tapez_supprimer' => 'Type DELETE',
    'continuer' => 'Continue',
    'derniere_etape' => 'Last step',
];
