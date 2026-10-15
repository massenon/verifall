<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: my profile
|--------------------------------------------------------------------------
|
| Changing the e-mail address is the one operation on this screen that can
| lock someone out of their own account, so it explains itself twice, in the
| order the questions arise: first WHY the address matters (it identifies you
| and recovers your password), then WHAT PROTECTS YOU during the change (the
| code expires in ten minutes, and the current address stays active until the
| new one is confirmed).
|
| That second sentence is the one that lets a cautious person go ahead, and it
| is kept whole.
|
| The required-field asterisks carry their own markup and stay attached to the
| label they mark.
|
*/

return [
    'fil_d_ariane' => 'Breadcrumb',
    'dashboard' => 'Dashboard',
    'mon_profil' => 'My profile',
    'vos_informations_publiques_et_votre_identite_verifiee' => 'Your public details and your verified identity.',

    'citoyen_verificateur' => 'Citizen fact-checker',
    'identite_verifiee_kyc' => 'Identity verified (KYC)',
    'identite_non_verifiee' => 'Identity not verified',
    'membre_depuis' => 'Member since',
    'jour' => 'day',
    'd_affilee' => 'in a row',

    'informations_personnelles' => 'Personal details',

    'prenom' => 'First name <span class="text-vfalse">*</span>',
    'nom' => 'Surname <span class="text-vfalse">*</span>',
    'telephone' => 'Phone',
    'ville' => 'City',
    'bio_publique' => 'Public bio',

    'enregistrer_les_modifications_enregistrement' => '<span wire:loading.remove wire:target="enregistrer">Save changes</span> <span wire:loading wire:target="enregistrer">Saving…</span>',

    'adresse_e_mail' => 'E-mail address <span class="text-vfalse">*</span>',
    'modifier' => 'Change',

    'cette_adresse_vous_identifie_et_sert_a' => 'This address identifies you and is used to recover your password. Changing it requires a code sent to the new address.',

    'nouvelle_adresse_exemple' => 'new@address.com',
    'code_a_6_chiffres' => '6-digit code',
    'envoyer_le_code' => 'Send the code',
    'annuler' => 'Cancel',
    'confirmer' => 'Confirm',

    'le_code_expire_dans_10_minutes_tant' => 'The code expires in 10 minutes. Until it is confirmed, your current address stays active.',
];
