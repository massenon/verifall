<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, mes-tutoriels
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
    'mes_tutoriels' => 'Mes Tutoriels',
    'votre_parcours_d_apprentissage_continuez_vos_cours' => 'Votre parcours d\'apprentissage — continuez vos cours, reprenez vos quiz et développez vos réflexes pour mieux vérifier l\'information.',
    'resume_de_mon_apprentissage' => 'Résumé de mon apprentissage',
    'moyenne_quiz' => 'Moyenne Quiz',
    'unites_investies' => 'Unités investies',
    'unites_gagnees' => 'Unités gagnées',
    'certificats_obtenus' => 'Certificats obtenus',
    'continuer' => 'Continuer',
    'continuer_votre_apprentissage' => 'Continuer votre apprentissage',
    'cours_en_cours' => 'Cours en cours',
    'votre_parcours_commence_ici' => 'Votre parcours commence ici',
    'decouvrez_les_tutoriels_verif_all_et_developpez' => 'Découvrez les tutoriels Vérif\'All et développez vos réflexes de vérification.',
    'quiz_a_reprendre' => 'Quiz à reprendre',
    'vous_pouvez_progresser_chaque_tentative_compte' => 'Vous pouvez progresser — chaque tentative compte.',
    'exercices_a_terminer' => 'Exercices à terminer',
    'guides_pratiques' => 'Guides pratiques',
    'des_formats_courts_pour_apprendre_un_reflexe' => 'Des formats courts pour apprendre un réflexe précis en quelques minutes.',
    'tutoriels_termines' => 'Tutoriels terminés',
    'terminez_un_cours_a_100_pour_le' => 'Terminez un cours à 100 % pour le retrouver ici avec son certificat.',

    /*
     | Second passage. Presque tout ce qui suit est un BOUTON : leur balise
     | portait une classe calculee ou un `wire:navigate`, et l'extraction les
     | sautait tous. C'est exactement ce que l'utilisateur voyait rester en
     | francais sur l'interface anglaise.
     */
    'fil_d_ariane' => 'Fil d\'Ariane',
    'apprentissage' => 'Apprentissage',
    'continuer_mon_apprentissage' => 'Continuer mon apprentissage',
    'catalogue' => 'Catalogue',
    'acces_rapides' => 'Accès rapides',
    'live_class' => 'Live class',
    'mes_quiz' => 'Mes Quiz',
    'exercices' => 'Exercices',
    'reprendre' => 'Reprendre',
    'commencer' => 'Commencer',
    'le_cours' => 'le cours',
    'en_cours_capitales' => 'EN COURS',
    'decouvrir_le_catalogue' => 'Découvrir le catalogue',
    'seuil' => 'Seuil :',
    'reprendre_le_quiz' => 'Reprendre le quiz',
    'ouvrir_l_exercice' => 'Ouvrir l\'exercice',
    'lire_le_guide' => 'Lire le guide',
    'termine_capitales' => 'TERMINÉ',
    'termine_le' => 'Terminé le',
    'voir_le_cours' => 'Voir le cours',
    'telecharger_le_certificat' => 'Télécharger le certificat',
    'voir_ma_recompense' => 'Voir ma récompense',

    /*
     | Les quatre guides pratiques etaient un tableau PHP en dur dans la vue.
     | C'est de la copie, pas de la structure : elle se traduit.
     */
    'guides' => [
        [
            'niveau' => 'Débutant · 3 min',
            'titre' => 'Vérifier une image en 3 minutes',
            'resume' => 'La recherche inversée expliquée pas à pas, avec un cas réel d\'image recyclée.',
            'etapes' => [
                ['Enregistrez l\'image', 'Depuis WhatsApp ou le réseau social, téléchargez l\'image sur votre téléphone ou copiez son lien direct.'],
                ['Lancez une recherche inversée', 'Ouvrez Google Lens ou TinEye et importez l\'image. Les résultats montrent toutes ses publications antérieures sur le web.'],
                ['Comparez les dates et contextes', 'Si l\'image apparaît en 2019 pour un autre événement, elle est recyclée : le contenu qui l\'accompagne aujourd\'hui est trompeur.'],
                ['Ou laissez Vérif\'All faire tout cela', 'Soumettez simplement l\'image sur la plateforme : recherche inversée, détection IA et recoupement de sources sont exécutés automatiquement.'],
            ],
        ],
        [
            'niveau' => 'Débutant · 4 min',
            'titre' => 'Repérer une arnaque au recrutement',
            'resume' => 'Les 5 signaux d\'alerte des fausses offres d\'emploi et les bons réflexes.',
            'etapes' => [
                ['Méfiez-vous des « frais de dossier »', 'Aucun employeur sérieux ne demande d\'argent pour étudier une candidature. C\'est le signal d\'alerte numéro un.'],
                ['Vérifiez l\'adresse e-mail', 'Une grande entreprise n\'écrit pas depuis une adresse gratuite (gmail, yahoo). Comparez avec le domaine officiel.'],
                ['Cherchez l\'offre à la source', 'Consultez le site officiel de l\'entreprise ou l\'ANPE. Si l\'offre n\'y figure pas, considérez-la fausse.'],
                ['Signalez pour protéger les autres', 'Soumettez le message sur Vérif\'All : le rapport public alertera les autres candidats ciblés.'],
            ],
        ],
        [
            'niveau' => 'Débutant · 2 min',
            'titre' => 'Lire un rapport Vérif\'All',
            'resume' => 'Score, verdict, sources croisées : comprendre chaque élément de votre rapport.',
            'etapes' => [
                ['Le verdict', 'Icône + couleur + texte : ✓ VRAI (vert), ⚠ DOUTEUX (jaune), ✕ FAUX (rouge). Jamais la couleur seule.'],
                ['Le score de fiabilité', 'De 0 à 100 %. Au-dessus de 75 % : fiable. Entre 40 et 75 % : prudence. En dessous : très probablement faux.'],
                ['Les explications', 'Chaque rapport détaille les signaux détectés : sources retrouvées, incohérences, indices de manipulation.'],
                ['Le conseil de partage', 'Le rapport vous dit explicitement quoi faire : partager la source officielle, attendre l\'investigation, ou alerter vos proches avec le rapport.'],
            ],
        ],
        [
            'niveau' => 'Intermédiaire · 5 min',
            'titre' => 'Que faire face à un audio suspect ?',
            'resume' => 'Voix clonées et audios sortis de leur contexte : la marche à suivre complète.',
            'etapes' => [
                ['Ne transférez pas', 'Un audio choquant appelle au transfert immédiat — c\'est exactement l\'effet recherché par les manipulateurs.'],
                ['Identifiez la voix et le contexte', 'La personne citée a-t-elle un canal officiel ? Un démenti a-t-il été publié ? Cherchez la première diffusion de l\'audio.'],
                ['Écoutez les détails', 'Coupures brutales, respiration absente, intonation plate : les voix clonées laissent des indices audibles.'],
                ['Soumettez l\'audio à Vérif\'All', 'Le pipeline audio analyse l\'empreinte vocale et recoupe le contenu avec les déclarations officielles connues.'],
            ],
        ],
    ],
    'retour_aux_guides' => 'Retour aux guides',
    'mettre_en_pratique' => 'Mettre en pratique',
];
