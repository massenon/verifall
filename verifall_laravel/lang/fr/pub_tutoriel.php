<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front public, fiche d'un cours
|--------------------------------------------------------------------------
|
| Transposition de tutoriels-details.html : quatre onglets, colonne laterale
| collante, puis « Explorez d'autres tutoriels ».
|
| `ce_cours_n_a_pas_encore_ete_note` dit l'absence de note plutot que de la
| combler. Une moyenne calculee sur zero avis serait un chiffre invente, et
| l'inventer sur la plateforme dont le metier est de dire ce qui est vrai
| serait la pire des economies. La traduction garde le constat nu.
|
| `avis`, `lecons`, `unites` et `unites_majuscules` sont des formes de
| PLURIEL servies par `trans_choice()`. La vue appelait `Str::plural()`, qui
| applique les regles de l'ANGLAIS : « avis » y devenait « avises ». Le bogue
| etait deja la, en francais ; il est corrige ici pour les deux langues.
|
| « Francais » est ici la LANGUE d'enseignement du cours, pas une etiquette
| d'interface : elle se traduit donc (« French »), elle ne se remplace pas par
| la langue de lecture.
|
*/

return [
    'fil_d_ariane' => 'Fil d\'Ariane',
    'accueil' => 'Accueil',
    'tutoriels' => 'Tutoriels',
    'avis' => 'avis|avis',
    'nouveau' => 'Nouveau',
    'lecons' => 'leçon|leçons',
    'sections_du_cours' => 'Sections du cours',
    'apercu' => 'Aperçu',
    'curriculum' => 'Curriculum',
    'instructeur' => 'Instructeur',
    'avis_onglet' => 'Avis',
    'ce_que_vous_allez_apprendre' => 'Ce que vous allez apprendre',
    'description_du_cours' => 'Description du cours',
    'programme' => 'Programme',
    'lecons_titre' => 'Leçons',
    'min' => 'min',
    'le_programme_de_ce_cours_est_en' => 'Le programme de ce cours est en cours de publication.',
    'evaluations' => 'Évaluations',
    'minimum' => 'minimum',
    'formateur_verif_all' => 'Formateur Vérif\'All',
    'ce_cours_est_porte_par_l_equipe' => 'Ce cours est porté par l\'équipe pédagogique Vérif\'All.',
    'notes_avis_des_apprenants' => 'Notes & Avis des apprenants',
    'base_sur' => 'Basé sur',
    'compte_supprime' => 'Compte supprimé',
    'ce_cours_n_a_pas_encore_ete_note' => 'Ce cours n\'a pas encore été noté. Les premiers avis apparaîtront ici.',
    'gratuit' => 'GRATUIT',
    'unites_majuscules' => 'UNITÉ|UNITÉS',
    's_inscrire_gratuitement' => 'S\'inscrire gratuitement',
    's_inscrire' => 'S\'inscrire',
    'langue' => 'Langue :',
    'francais' => 'Français',
    'duree' => 'Durée :',
    'certificat' => 'Certificat :',
    'oui' => 'Oui',
    'non' => 'Non',
    'partager_ce_tutoriel' => 'Partager ce tutoriel :',
    'partager_sur_facebook' => 'Partager sur Facebook',
    'partager_sur_whatsapp' => 'Partager sur WhatsApp',
    'partager_sur_x' => 'Partager sur X',
    'explorez_d_autres_tutoriels' => 'Explorez d\'autres tutoriels',
    'voir_tous_les_tutoriels' => 'Voir tous les tutoriels',
    'acces_libre' => 'Accès Libre',
    'unites' => 'unité|unités',
    'decouvrir' => 'Découvrir',
];
