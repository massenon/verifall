<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, reglages
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
    'reglages' => 'Réglages',
    'securite_notifications_confidentialite_et_gestion_du_compte' => 'Sécurité, notifications, confidentialité et gestion du compte.',
    'securite' => '🔐 Sécurité',
    'double_authentification_2fa' => 'Double authentification (2FA)',
    'code_de_confirmation_demande_a_chaque_connexion' => 'Code de confirmation demandé à chaque connexion.',
    'activer_desactiver_la_double_authentification' => 'Activer / désactiver la double authentification',
    'activer_desactiver' => 'Activer / désactiver',
    'sessions_actives' => 'Sessions actives',
    'session_actuelle' => 'Session actuelle',
    'deconnecter' => 'Déconnecter',
    'aucun_autre_appareil_connecte' => 'Aucun autre appareil connecté.',
    'changer_mon_mot_de_passe' => 'Changer mon mot de passe',
    'notifications' => '🔔 Notifications',
    'lettre_d_information' => 'Lettre d\'information',
    'le_recap_mensuel_des_rumeurs_verifiees' => 'Le récap mensuel des rumeurs vérifiées.',
    'votre_accord_et_son_retrait_sont_horodates' => 'Votre accord et son retrait sont horodatés : vous pouvez vous désabonner aussi simplement que vous vous êtes abonné.',
    'donnees_personnelles' => '🛡️ Données personnelles',
    'conformement_au_code_du_numerique_beninois_vous' => 'Conformément au code du numérique béninois, vous pouvez exporter l\'ensemble de vos données ou demander leur suppression.',
    'exporter_mes_donnees_json_demande_en_cours' => '<span wire:loading.remove wire:target="exporterMesDonnees">Exporter mes données (JSON)</span> <span wire:loading wire:target="exporterMesDonnees">Demande en cours…</span>',
    'archive_en_preparation_vous_recevrez_un_courriel' => 'Archive en préparation. Vous recevrez un courriel dès qu\'elle sera prête.',
    'la_preparation_a_echoue_aucune_donnee_n' => 'La préparation a échoué. Aucune donnée n\'a été perdue : relancez la demande.',
    'votre_precedente_archive_a_expire_et_a' => 'Votre précédente archive a expiré et a été détruite. Vous pouvez en demander une nouvelle.',
    'politique_de_confidentialite' => 'Politique de confidentialité',
    'archive_prete' => 'Archive prête',
    'ko' => 'Ko',
    'expire' => 'expire',
    'telecharger_l_archive' => 'Télécharger l\'archive',
    'zone_de_danger' => '⚠ Zone de danger',
    'votre_compte_sera_ferme_immediatement_mais_vos' => 'Votre compte sera fermé immédiatement, mais vos données ne seront effacées qu\'après <strong class="text-ink">:jours jours</strong> : un lien d\'annulation vous sera envoyé par courriel.',
    'saisissez_le_code_a_6_chiffres_envoye' => 'Saisissez le code à 6 chiffres envoyé à votre adresse. Votre compte sera fermé immédiatement, et vos données effacées dans :jours jours.',
    'la_suppression_du_compte_est_definitive_verifications' => 'La suppression du compte est définitive : vérifications, progression de cours, certificats et unités seront effacés.',
    'supprimer_mon_compte' => 'Supprimer mon compte',
    'mot_de_passe_actuel' => 'Mot de passe actuel',
    'nouveau_mot_de_passe_12_caracteres_min' => 'Nouveau mot de passe (12 caractères min.)',
    'confirmer_le_nouveau_mot_de_passe' => 'Confirmer le nouveau mot de passe',
    'vos_autres_appareils_connectes_seront_deconnectes' => 'Vos autres appareils connectés seront déconnectés.',
    'annuler' => 'Annuler',
    'mettre_a_jour_mise_a_jour' => '<span wire:loading.remove wire:target="changerMotDePasse">Mettre à jour</span> <span wire:loading wire:target="changerMotDePasse">Mise à jour…</span>',
    'activer_la_double_authentification' => 'Activer la double authentification',
    'scannez_ce_code_avec_google_authenticator_authy' => 'Scannez ce code avec Google Authenticator, Authy ou équivalent, puis saisissez le code affiché.',
    'code_a_6_chiffres' => 'Code à 6 chiffres',
    'confirmer' => 'Confirmer',
    'vos_codes_de_recuperation' => 'Vos codes de récupération',
    'conservez_les_hors_de_votre_telephone_ils' => 'Conservez-les hors de votre téléphone. Ils sont votre seul recours si vous perdez votre application — <strong class="text-ink">ils ne pourront plus être réaffichés</strong>, car nous n\'en gardons qu\'une empreinte.',
    'je_les_ai_notes' => 'Je les ai notés',
    'desactiver_la_double_authentification' => 'Désactiver la double authentification ?',
    'votre_compte_ne_sera_plus_protege_que' => 'Votre compte ne sera plus protégé que par son mot de passe. Confirmez avec votre mot de passe et un code en cours.',
    'mot_de_passe' => 'Mot de passe',
    'code_en_cours_ou_code_de_recuperation' => 'Code en cours ou code de récupération',
    'desactiver' => 'Désactiver',
    'supprimer_definitivement_le_compte' => 'Supprimer définitivement le compte ?',
    'tapez_supprimer_et_confirmez_avec_votre_mot' => 'Tapez <strong class="text-ink">SUPPRIMER</strong> et confirmez avec votre mot de passe. Un code vous sera ensuite envoyé par courriel.',
    'tapez_supprimer' => 'Tapez SUPPRIMER',
    'continuer' => 'Continuer',
    'derniere_etape' => 'Dernière étape',
];
