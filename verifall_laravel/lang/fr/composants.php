<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Composants partages, citoyen et public
|--------------------------------------------------------------------------
|
| Dix-sept composants se partagent cet espace de noms : ils sont trop petits
| pour meriter chacun son fichier, et ils s'affichent tous dans les memes
| pages. Chaque cle porte donc le prefixe de son composant — `offre_`, `live_`,
| `cours_` — car deux composants voisins emploient souvent le meme mot sans lui
| donner le meme sens : le « Rejoindre » d'une Live Class n'est pas le
| « Rejoindre » d'un forum.
|
| Les pluriels ne sont PAS fabriques en collant un « s » a la fin. Le francais
| l'admet (« 3 avis exprimes »), l'anglais non : le « s » y tombe sur le nom,
| pas sur la fin du groupe. Chaque pluriel a donc ses deux cles, et la vue
| choisit.
|
| Les ligatures Material Symbols (`close`, `verified`, `arrow_forward`…) ne
| figurent pas ici : ce sont des identifiants d'icone, pas du texte. Les
| traduire afficherait un carre vide.
|
*/

return [
    // components/auth/boutons-sociaux.blade.php
    'sociaux_ou_continuer_avec' => 'ou continuer avec',
    'sociaux_en_continuant_vous_acceptez_nos' => 'En continuant, vous acceptez nos',
    'sociaux_conditions_d_utilisation' => 'conditions d\'utilisation',
    'sociaux_et_notre' => 'et notre',
    'sociaux_politique_de_confidentialite' => 'politique de confidentialité',

    // components/data-table.blade.php
    'table_resultats' => 'résultat(s)',
    'table_tout_selectionner' => 'Tout sélectionner',

    // components/delete-modal.blade.php
    'modale_supprimer_avant' => 'Supprimer «',
    'modale_supprimer_apres' => '» ?',
    'modale_cette_action_est_definitive' => 'Cette action est définitive.',
    'modale_annuler' => 'Annuler',
    'modale_supprimer_definitivement' => 'Supprimer définitivement',

    // components/evaluation/action-quiz.blade.php
    'quiz_voir_le_cours' => 'Voir le cours',
    'quiz_reessayer' => 'Réessayer',
    'quiz_passer_le_quiz' => 'Passer le quiz',
    'quiz_s_inscrire_au_cours' => 'S\'inscrire au cours',

    // components/evaluation/badge-quiz.blade.php
    'badge_reussi' => 'RÉUSSI',
    'badge_a_refaire' => 'À REFAIRE',
    'badge_a_passer' => 'À PASSER',
    'badge_disponible_apres_inscription_au_cours' => 'Disponible après inscription au cours',
    'badge_verrouille' => 'VERROUILLÉ',

    // components/forum/carte-sujet.blade.php
    'sujet_compte_supprime' => 'Compte supprimé',
    'sujet_sujet_epingle' => 'Sujet épinglé',
    'sujet_epingle' => 'Épinglé',
    'sujet_verif_all_confirme' => '✓ VÉRIF\'ALL CONFIRME —',
    'sujet_avis_de_la_communaute' => 'Avis de la communauté',
    'sujet_avis_exprime' => 'avis exprimé',
    'sujet_avis_exprimes' => 'avis exprimés',
    'sujet_source_signalee' => 'Source signalée :',
    'sujet_fil_clos' => 'Fil clos',

    // components/hub/aucune-opportunite.blade.php
    'hub_aucune_opportunite_ne_correspond' => 'Aucune opportunité ne correspond',
    'hub_essayez_une_autre_recherche_ou_une_autre' => 'Essayez une autre recherche ou une autre nature.',
    'hub_reinitialiser_les_filtres' => 'Réinitialiser les filtres',
    'hub_nous_ne_publions_que_des_opportunites' => 'Nous ne publions que des opportunités dont l\'organisation et le canal ont été contrôlés. Aucune ne l\'est aujourd\'hui — revenez bientôt.',

    // components/hub/carte-opportunite.blade.php
    'offre_verifiee' => 'Vérifiée',
    'offre_sans_date_limite_annoncee' => 'Sans date limite annoncée.',
    'offre_date_limite_depassee' => 'Date limite dépassée — l\'annonce sera retirée sous peu.',
    'offre_dernier_jour_pour_candidater' => 'Dernier jour pour candidater.',
    'offre_plus_que' => 'Plus que',
    'offre_jour' => 'jour',
    'offre_jours' => 'jours',
    'offre_jusqu_au' => '— jusqu\'au',
    'offre_aucun_frais_de_dossier_jamais' => 'Aucun frais de dossier, jamais.',
    'offre_voir_l_opportunite' => 'Voir l\'opportunité',

    // components/infolettre-abonnement.blade.php
    'infolettre_titre' => 'L\'infolettre du lundi',
    'infolettre_chapo' => 'Chaque lundi, le récapitulatif des pires intox détectées au Bénin durant la semaine, avec le fact-check complet et ses sources.',
    'infolettre_verifiez_votre_boite' => 'Merci. Si cette adresse peut recevoir l\'infolettre, un message de confirmation vient d\'y être envoyé, un clic suffira.',
    'infolettre_confirmee' => 'Abonnement confirmé. Votre premier récapitulatif arrivera lundi.',
    'infolettre_desabonnee' => 'Vous êtes désabonné. Vous ne recevrez plus l\'infolettre, aucune autre démarche n\'est nécessaire.',
    'infolettre_lien_invalide' => 'Ce lien n\'est plus valable. Il a peut-être été coupé par votre messagerie : réessayez en le recopiant en entier.',
    'infolettre_adresse_email' => 'Adresse e-mail *',
    'infolettre_placeholder_email' => 'nom@exemple.com',
    'infolettre_je_m_abonne' => 'Je m\'abonne',
    'infolettre_mentions' => 'Un courriel par semaine, jamais davantage. Un lien de désabonnement en un clic figure dans chaque message, sans mot de passe et sans connexion. Votre adresse ne sert qu\'à cela et n\'est transmise à personne.',

    // components/layouts/certificat.blade.php
    'certificat_titre_page' => 'Vérifier un certificat — Vérif\'All',
    'certificat_meta_description' => 'Vérifiez publiquement l\'authenticité d\'un certificat Vérif\'All grâce à son identifiant unique et sa signature numérique.',
    'certificat_og_description' => 'Vérification publique de l\'authenticité des certificats Vérif\'All.',
    'certificat_se_connecter' => 'Se connecter',
    'certificat_tous_droits_reserves' => 'Tous droits réservés.',

    // components/live-class/carte.blade.php
    'live_en_ce_moment' => 'En ce moment',
    'live_en_direct' => 'EN DIRECT',
    'live_planifiee' => 'PLANIFIÉE',
    'live_terminee' => 'TERMINÉE',
    'live_seance_ouverte' => 'Séance ouverte',
    'live_animee_par' => '· animée par',
    'live_l_equipe_verif_all' => 'l\'équipe Vérif\'All',
    'live_rejoindre_maintenant' => 'Rejoindre maintenant',
    'live_ajouter_au_calendrier' => 'Ajouter au calendrier',
    'live_rejoindre' => 'Rejoindre',
    'live_le_lien_s_active_15_minutes_avant' => 'Le lien s\'active 15 minutes avant le début',
    'live_rejoindre_bientot' => 'Rejoindre bientôt',
    'live_voir_le_replay' => 'Voir le replay',

    // components/lms/carte-cours.blade.php
    'cours_nouveau' => 'Nouveau',
    'cours_lecons' => 'leçons',
    'cours_gratuit' => 'GRATUIT',
    'cours_offert_jusqu_au' => 'offert jusqu\'au',
    'cours_premium' => 'PREMIUM ·',
    'cours_unites' => 'unités',
    'cours_termine' => '✓ Terminé',
    'cours_revoir' => 'Revoir',
    'cours_commencer' => 'Commencer',

    // components/mention-legale.blade.php
    'mention_non_communique' => 'Non communiqué',
    'mention_a_renseigner' => 'À renseigner :',
    'mention_reglages_onglet_general' => '(Réglages, onglet Général)',

    // components/offcanvas-form.blade.php
    'offcanvas_formulaire' => 'Formulaire',
    'offcanvas_fermer' => 'Fermer',

    // components/outil/carte.blade.php
    'outil_ideal_pour' => 'Idéal pour',
    'outil_ouvrir_l_outil' => 'Ouvrir l\'outil',

    // components/skeleton.blade.php
    'skeleton_chargement_en_cours' => 'Chargement en cours',
    'skeleton_chargement_en_cours_points' => 'Chargement en cours…',

    /*
     | Rediffusion des Live Classes (lot 1, B-06).
     */
    'live_rediffusion_a_venir' => 'Rediffusion à venir',
    'quiz_terminez_les_lecons' => 'Terminez les leçons',
    'live_rediffusion_reservee' => 'Réservée aux inscrits',
];
