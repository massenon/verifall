<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, profil
|--------------------------------------------------------------------------
|
| ENGENDRE depuis la vue, puis relu. La copie du prototype reste ainsi
| identique au caractere pres.
|
| Les valeurs qui portent du balisage en ligne sont rendues en syntaxe non
| echappee. Ce n'est PAS une entorse a la regle de securite : ces chaines sont
| ecrites par l'equipe, jamais par un visiteur.
|
*/

return [
    'fil_d_ariane' => 'Fil d\'Ariane',
    'dashboard' => 'Dashboard',
    'mon_profil' => 'Mon profil',
    'vos_informations_publiques_et_votre_identite_verifiee' => 'Vos informations publiques et votre identité vérifiée.',
    'citoyen_verificateur' => 'Citoyen vérificateur',
    'identite_verifiee_kyc' => 'Identité vérifiée (KYC)',
    'identite_non_verifiee' => 'Identité non vérifiée',
    'membre_depuis' => 'Membre depuis',
    'jour' => 'jour',
    'd_affilee' => 'd\'affilée',

    'informations_personnelles' => 'Informations personnelles',
    'prenom' => 'Prénom <span class="text-vfalse">*</span>',
    'nom' => 'Nom <span class="text-vfalse">*</span>',
    'telephone' => 'Téléphone',
    'ville' => 'Ville',
    'bio_publique' => 'Bio publique',
    'enregistrer_les_modifications_enregistrement' => '<span wire:loading.remove wire:target="enregistrer">Enregistrer les modifications</span> <span wire:loading wire:target="enregistrer">Enregistrement…</span>',
    'adresse_e_mail' => 'Adresse e-mail <span class="text-vfalse">*</span>',
    'modifier' => 'Modifier',
    'cette_adresse_vous_identifie_et_sert_a' => 'Cette adresse vous identifie et sert à récupérer votre mot de passe. La changer demande un code envoyé à la nouvelle adresse.',
    'nouvelle_adresse_exemple' => 'nouvelle@adresse.com',
    'code_a_6_chiffres' => 'Code à 6 chiffres',
    'envoyer_le_code' => 'Envoyer le code',
    'annuler' => 'Annuler',
    'confirmer' => 'Confirmer',
    'le_code_expire_dans_10_minutes_tant' => 'Le code expire dans 10 minutes. Tant qu\'il n\'est pas confirmé, votre adresse actuelle reste active.',
];
