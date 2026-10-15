<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: my tutorials
|--------------------------------------------------------------------------
|
| "You can make progress, every attempt counts" sits above the list of quizzes
| to retake. It is placed where someone is looking at their own failures, and
| it is the same commitment the course FAQ makes. The English keeps both
| halves: the encouragement AND the reason it is true.
|
| The empty states name the next step rather than the absence. "Your path
| starts here" is what a new learner sees instead of "no course".
|
*/

return [
    'mes_tutoriels' => 'My tutorials',

    'votre_parcours_d_apprentissage_continuez_vos_cours' => 'Your learning path: carry on with your courses, retake your quizzes and build the reflexes to check information better.',

    'resume_de_mon_apprentissage' => 'My learning summary',

    'moyenne_quiz' => 'Quiz average',
    'unites_investies' => 'Units invested',
    'unites_gagnees' => 'Units earned',
    'certificats_obtenus' => 'Certificates earned',

    'continuer' => 'Continue',
    'continuer_votre_apprentissage' => 'Carry on learning',
    'cours_en_cours' => 'Courses in progress',

    'votre_parcours_commence_ici' => 'Your path starts here',
    'decouvrez_les_tutoriels_verif_all_et_developpez' => 'Explore the Vérif\'All tutorials and build your checking reflexes.',

    'quiz_a_reprendre' => 'Quizzes to retake',
    'vous_pouvez_progresser_chaque_tentative_compte' => 'You can make progress, every attempt counts.',

    'exercices_a_terminer' => 'Exercises to finish',

    'guides_pratiques' => 'Practical guides',
    'des_formats_courts_pour_apprendre_un_reflexe' => 'Short formats to learn one precise reflex in a few minutes.',

    'tutoriels_termines' => 'Tutorials finished',
    'terminez_un_cours_a_100_pour_le' => 'Finish a course at 100% to find it here with its certificate.',

    /*
     | Second pass. Almost everything below is a BUTTON: their tags carried a
     | computed class or a `wire:navigate`, and the extraction skipped every
     | one. This is exactly what the user saw staying French on the English
     | interface.
     |
     | 'le_cours' is a fragment: the view renders "Resume"/"Start" and then
     | this word, because the choice depends on progress. English keeps the
     | same split so the sentence reads "Resume the course".
     */
    'fil_d_ariane' => 'Breadcrumb',
    'apprentissage' => 'Learning',
    'continuer_mon_apprentissage' => 'Continue learning',
    'catalogue' => 'Catalogue',
    'acces_rapides' => 'Quick links',
    'live_class' => 'Live class',
    'mes_quiz' => 'My quizzes',
    'exercices' => 'Exercises',
    'reprendre' => 'Resume',
    'commencer' => 'Start',
    'le_cours' => 'the course',
    'en_cours_capitales' => 'IN PROGRESS',
    'decouvrir_le_catalogue' => 'Browse the catalogue',
    'seuil' => 'Pass mark:',
    'reprendre_le_quiz' => 'Retake the quiz',
    'ouvrir_l_exercice' => 'Open the exercise',
    'lire_le_guide' => 'Read the guide',
    'termine_capitales' => 'COMPLETED',
    'termine_le' => 'Completed on',
    'voir_le_cours' => 'View the course',
    'telecharger_le_certificat' => 'Download the certificate',
    'voir_ma_recompense' => 'See my reward',

    /*
     | The four practical guides were a PHP array hardcoded in the view. That
     | is copy, not structure: it gets translated.
     */
    'guides' => [
        [
            'niveau' => 'Beginner · 3 min',
            'titre' => 'Check an image in 3 minutes',
            'resume' => 'Reverse image search, step by step, with a real case of a recycled photo.',
            'etapes' => [
                ['Save the image', 'From WhatsApp or the social network, download the image to your phone or copy its direct link.'],
                ['Run a reverse search', 'Open Google Lens or TinEye and upload the image. The results show every earlier posting of it on the web.'],
                ['Compare dates and contexts', 'If the image appears in 2019 for a different event, it has been recycled: the story around it today is misleading.'],
                ['Or let Vérif\'All do all of it', 'Just submit the image on the platform: reverse search, AI detection and source cross-checking all run automatically.'],
            ],
        ],
        [
            'niveau' => 'Beginner · 4 min',
            'titre' => 'Spot a recruitment scam',
            'resume' => 'The 5 warning signs of fake job offers, and what to do about them.',
            'etapes' => [
                ['Beware of "application fees"', 'No serious employer asks for money to consider an application. That is warning sign number one.'],
                ['Check the e-mail address', 'A large company does not write from a free address (gmail, yahoo). Compare it with the official domain.'],
                ['Look for the offer at the source', 'Check the company\'s own site or the national employment agency. If the offer is not there, treat it as fake.'],
                ['Report it to protect others', 'Submit the message on Vérif\'All: the public report will warn the other candidates being targeted.'],
            ],
        ],
        [
            'niveau' => 'Beginner · 2 min',
            'titre' => 'Read a Vérif\'All report',
            'resume' => 'Score, verdict, cross-checked sources: understanding every part of your report.',
            'etapes' => [
                ['The verdict', 'Icon + colour + text: ✓ TRUE (green), ⚠ DOUBTFUL (amber), ✕ FALSE (red). Never colour alone.'],
                ['The reliability score', 'From 0 to 100%. Above 75%: reliable. Between 40 and 75%: caution. Below: very probably false.'],
                ['The explanations', 'Every report details the signals found: sources located, inconsistencies, signs of manipulation.'],
                ['The sharing advice', 'The report tells you plainly what to do: share the official source, wait for the investigation, or warn people with the report.'],
            ],
        ],
        [
            'niveau' => 'Intermediate · 5 min',
            'titre' => 'What to do with a suspicious audio clip?',
            'resume' => 'Cloned voices and clips taken out of context: the full procedure.',
            'etapes' => [
                ['Do not forward it', 'A shocking clip invites an immediate forward — that is exactly the effect its makers want.'],
                ['Identify the voice and the context', 'Does the person quoted have an official channel? Has a denial been published? Look for the first posting of the clip.'],
                ['Listen to the detail', 'Abrupt cuts, no breathing, flat intonation: cloned voices leave audible traces.'],
                ['Submit the audio to Vérif\'All', 'The audio pipeline analyses the voice print and cross-checks the content against known official statements.'],
            ],
        ],
    ],
    'retour_aux_guides' => 'Back to the guides',
    'mettre_en_pratique' => 'Try it now',
];
