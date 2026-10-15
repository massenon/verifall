<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, mon-parrainage
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
    'mon_parrainage' => 'Mon Parrainage',
    'partagez_verif_all_faites_decouvrir_la_verification' => 'Partagez Vérif\'All. Faites découvrir la vérification fiable. Gagnez ensemble.',
    'resume_du_parrainage_expand_more' => '<span class="text-sm font-display font-bold text-ink">Résumé du parrainage</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',
    'invitations_ouvertes' => 'Invitations ouvertes',
    'inscrits' => 'Inscrits',
    'actifs' => 'Actifs',
    'unites_gagnees' => 'Unités gagnées',
    'votre_invitation' => 'Votre invitation',
    'partagez_ce_lien_pour_inviter_vos_proches' => 'Partagez ce lien pour inviter vos proches. Il est personnel et associé à votre compte.',
    'code_de_parrainage' => 'Code de parrainage',
    'votre_qr_code' => 'Votre QR Code',
    'scannez_pour_decouvrir_verif_all' => 'Scannez pour découvrir Vérif\'All',
    'recompense_actuelle' => 'Récompense actuelle',
    'lorsque_votre_filleul_realise_sa_premiere_verification' => 'Lorsque votre filleul réalise sa <strong class="text-ink">première vérification</strong> :',
    'vous' => '🎁 Vous',
    'creditees_des_que_votre_filleul_realise_sa' => 'créditées dès que votre filleul réalise sa première vérification.',
    'votre_filleul' => '🎁 Votre filleul',
    'creditees_sur_sa_premiere_verification_en_plus' => 'créditées sur sa première vérification, en plus de son avantage de bienvenue.',
    'l_inscription_seule_ne_declenche_rien_c' => 'L\'inscription seule ne déclenche rien : c\'est l\'usage réel qui compte, et c\'est ce qui protège le dispositif des faux comptes.',
    'comment_fonctionne_le_parrainage' => 'Comment fonctionne le parrainage ?',
    'quatre_etapes_simples_pas_de_formulaire_complique' => 'Quatre étapes simples. Pas de formulaire compliqué.',
    'mes_invitations' => 'Mes invitations',
    'suivez_simplement_l_etat_de_vos_invitations' => 'Suivez simplement l\'état de vos invitations.',
    'personne_n_a_encore_rejoint' => 'Personne n\'a encore rejoint',
    'partagez_votre_lien_chaque_personne_qui_verifie' => 'Partagez votre lien : chaque personne qui vérifie avant de partager, c\'est une rumeur de moins qui circule.',
    'historique_des_recompenses' => 'Historique des récompenses',
    'aucune_recompense_pour_l_instant_la_premiere' => 'Aucune récompense pour l\'instant. La première arrive dès qu\'un filleul fait sa première vérification.',
    'votre_progression_vers_le_prochain_bonus' => 'Votre progression vers le prochain bonus',
    'pourquoi_inviter_vos_proches' => 'Pourquoi inviter vos proches ?',
    'partagez_aussi_autour_de_vous_votre_qr' => '<strong class="text-ink">Partagez aussi autour de vous :</strong> votre QR Code peut être utilisé lors d\'une formation, d\'un atelier, d\'une rencontre ou d\'une activité communautaire.',
    'questions_frequentes' => 'Questions fréquentes',
    'un_programme_concu_pour_etre_transparent' => 'Un programme conçu pour être transparent',
    'les_recompenses_sont_attribuees_automatiquement_lorsque_les' => 'Les récompenses sont attribuées automatiquement lorsque les conditions d\'éligibilité sont remplies. Vérif\'All peut empêcher, suspendre ou annuler une récompense lorsqu\'une activité semble frauduleuse, automatisée ou contraire aux règles du programme (auto-parrainage, faux comptes, multi-comptes, spam).',
    'partager_mon_invitation' => 'Partager mon invitation',
    'invitez_quelqu_un_a_decouvrir_verif_all' => 'Invitez quelqu\'un à découvrir Vérif\'All.',
    /*
     | Second passage. Deux points meritent une note.
     |
     | Le bouton « Copier » change de texte apres le clic, via un `x-text`
     | Alpine qui portait ses deux libelles EN DUR dans l'attribut. Ils passent
     | par `@js(__())` : la chaine reste correctement echappee pour JavaScript,
     | comme le `@js($lien)` voisin le faisait deja.
     |
     | Le repli entre les balises est conserve : sans lui, le bouton est vide
     | tant qu'Alpine n'a pas demarre.
     */
    'fil_d_ariane' => 'Fil d\'Ariane',
    'vous_avez_ete_parraine_par' => 'Vous avez été parrainé par',
    'un_membre' => 'un membre',
    'unites' => 'unités',
    'n_unites' => ':count unité|:count unités',
    'unites_recues' => 'unités reçues',
    'unites_vous_attendent' => 'unités vous attendent',
    'lancer_ma_premiere_verification' => 'Lancer ma première vérification',
    'votre_lien_d_invitation' => 'Votre lien d\'invitation',
    'copie' => 'Copié',
    'copier' => 'Copier',
    'copier_le_lien' => 'Copier le lien',
    'qr_code_de_votre_lien_d_invitation' => 'QR code de votre lien d\'invitation Vérif\'All',
    'code_capitales' => 'CODE :',
    'inscription' => 'Inscription',
    'premiere_verification' => 'Première vérification',
    'a_realiser' => 'à réaliser',
    'recompense' => 'Récompense',
    'en_attente' => 'En attente',
    'invitations_eligibles_plus_que' => 'invitations éligibles — plus que',
    'pour_debloquer_le_prochain_palier' => 'pour débloquer le prochain palier.',
    'invitation_verif_all' => 'Invitation Vérif\'All',
    'e_mail' => 'E-mail',
    'partager_avec_mon_telephone' => 'Partager avec mon téléphone',

    'fermer' => 'Fermer',
];
