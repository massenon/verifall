<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Public front, a course page
|--------------------------------------------------------------------------
|
| Four tabs, a sticky side column, then "Explore other tutorials".
|
| `ce_cours_n_a_pas_encore_ete_note` states the absence of a rating rather
| than papering over it. An average computed from zero reviews is an invented
| number, and inventing one on the platform whose job is to say what is true
| would be the worst of economies. The English keeps the statement bare.
|
| `avis`, `lecons`, `unites` and `unites_majuscules` are PLURAL forms served
| by `trans_choice()`. The view used `Str::plural()`, which applies ENGLISH
| rules: it turned the French "avis" into "avises". The bug was already there
| in French; it is fixed here for both languages.
|
| "Francais" is the course's TEACHING language, not an interface label, so it
| is translated as "French" — it does not follow the reader's own language.
|
*/

return [
    'fil_d_ariane' => 'Breadcrumb',
    'accueil' => 'Home',
    'tutoriels' => 'Tutorials',
    'avis' => 'review|reviews',
    'nouveau' => 'New',
    'lecons' => 'lesson|lessons',
    'sections_du_cours' => 'Course sections',
    'apercu' => 'Overview',
    'curriculum' => 'Curriculum',
    'instructeur' => 'Instructor',
    'avis_onglet' => 'Reviews',
    'ce_que_vous_allez_apprendre' => 'What you will learn',
    'description_du_cours' => 'Course description',
    'programme' => 'Syllabus',
    'lecons_titre' => 'Lessons',
    'min' => 'min',
    'le_programme_de_ce_cours_est_en' => 'The syllabus for this course is being published.',
    'evaluations' => 'Assessments',
    'minimum' => 'minimum',
    'formateur_verif_all' => 'Vérif\'All trainer',
    'ce_cours_est_porte_par_l_equipe' => 'This course is run by the Vérif\'All teaching team.',
    'notes_avis_des_apprenants' => 'Ratings & Learner reviews',
    'base_sur' => 'Based on',
    'compte_supprime' => 'Deleted account',
    'ce_cours_n_a_pas_encore_ete_note' => 'This course has not been rated yet. The first reviews will appear here.',
    'gratuit' => 'FREE',
    'unites_majuscules' => 'UNIT|UNITS',
    's_inscrire_gratuitement' => 'Sign up for free',
    's_inscrire' => 'Sign up',
    'langue' => 'Language:',
    'francais' => 'French',
    'duree' => 'Duration:',
    'certificat' => 'Certificate:',
    'oui' => 'Yes',
    'non' => 'No',
    'partager_ce_tutoriel' => 'Share this tutorial:',
    'partager_sur_facebook' => 'Share on Facebook',
    'partager_sur_whatsapp' => 'Share on WhatsApp',
    'partager_sur_x' => 'Share on X',
    'explorez_d_autres_tutoriels' => 'Explore other tutorials',
    'voir_tous_les_tutoriels' => 'See all tutorials',
    'acces_libre' => 'Free access',
    'unites' => 'unit|units',
    'decouvrir' => 'Discover',
];
