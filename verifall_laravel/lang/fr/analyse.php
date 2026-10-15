<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| L'analyse : étapes, moteurs, preuves, avis de la communauté
|--------------------------------------------------------------------------
|
| Les clés du pipeline sont les valeurs ENTIÈRES de `EtapePipeline` (1 à 6),
| l'ordre chronologique lui-même — renuméroter ici ne renumérote rien
| ailleurs, et déplacerait seulement un libellé sous une autre étape.
|
| Les NOMS DE MOTEURS sont des noms propres : Gemini, DeepSeek, OpenAI et
| TinEye s'écrivent pareil dans toutes les langues. Seule la fonction que le
| moteur remplit se traduit.
|
*/

return [

    'pipeline' => [
        'libelle' => [
            1 => 'Réception du contenu',
            2 => "Préparation de l'analyse",
            3 => 'Analyse des sources',
            4 => 'Analyse par IA',
            5 => 'Consolidation des preuves',
            6 => 'Verdict',
        ],

        'description' => [
            1 => 'Votre soumission est enregistrée et horodatée.',
            2 => 'Extraction du texte, des métadonnées et des empreintes.',
            3 => 'Croisement avec les bases de fact-checking et les sources de confiance.',
            4 => "Nos moteurs évaluent le langage, la provenance et l'antériorité.",
            5 => 'Constitution du dossier : sources, extraits, chronologie.',
            6 => 'Rédaction du rapport et calcul du score de confiance.',
        ],
    ],

    'moteur' => [
        'gemini' => 'Analyse sémantique (Gemini)',
        'deepseek' => 'Contre-analyse (DeepSeek)',
        'openai' => 'Raisonnement (OpenAI)',
        'perplexity_search' => 'Recherche web citée',
        'google_factcheck' => 'Fact-checks antérieurs',
        'google_custom_search' => 'Presse béninoise et sources officielles',
        'knowledge_graph' => 'Vérification des entités',
        'technical_analysis' => 'Analyse technique du domaine',
        'tineye' => "Antériorité de l'image",
        'vision' => "Lecture de l'image",
        'ai_detector' => 'Détection de contenu généré',
        'transcription' => 'Transcription',
    ],

    'position_preuve' => [
        'supports' => 'Confirme',
        'refutes' => 'Contredit',
        'neutral' => 'Neutre',
        'contested' => 'Contestée',
    ],

    /*
     | Ce qu'une phrase EST — `ActeDeLangage`, phase 2. Les clés sont les
     | valeurs de l'enum.
     */
    'acte_de_langage' => [
        'affirmation' => 'Affirmation',
        'question' => 'Question',
        'opinion' => 'Opinion',
        'prediction' => 'Prédiction',
        'non_factuel' => 'Non factuel',
    ],

    /*
     | Ce qu'un rapport DIT dans son ensemble — `CompositionDuRapport`, un axe
     | distinct du verdict (contrat V-8). `MIXTE` n'est jamais un verdict.
     */
    'composition' => [
        'homogene' => 'Verdict unique',
        'mixte' => 'Affirmations contradictoires',
        'insuffisante' => 'Aucun élément tranché',
    ],

    'composition_explication' => [
        'homogene' => 'Toutes les affirmations de ce contenu portent le même verdict.',
        'mixte' => 'Ce contenu contient à la fois des affirmations exactes et des affirmations fausses ou trompeuses — le détail ci-dessous distingue chacune.',
        'insuffisante' => "Ce contenu ne porte aucune affirmation que la plateforme puisse trancher.",
    ],

    /*
     | Ce qu'un claim dit du TEMPS — `PorteeTemporelle`, phase 3. `consigne`
     | est injectée telle quelle sous {{PORTEE_TEMPORELLE}} dans `analyse.md` :
     | c'est un texte adressé au MODÈLE, pas à un lecteur humain.
     */
    'portee_temporelle' => [
        'libelle' => [
            'present' => 'État présent',
            'historique' => 'Fait historique',
            'date_explicite' => 'Date explicite',
            'relatif' => 'Date relative',
        ],
        'consigne' => [
            'present' => "Ce claim porte sur un ÉTAT ACTUEL (qui occupe une fonction, un prix en vigueur, une règle applicable). Ta mémoire est FORMELLEMENT INTERDITE pour en juger : elle peut sembler à jour sans l'être. Si tu disposes d'un outil de recherche, utilise-le obligatoirement, avec la recherche contradictoire déjà exigée plus bas. Sans recherche, ou si les preuves manquent, le verdict est NON_VERIFIABLE.",
            'historique' => "Ce claim porte sur un fait HISTORIQUE réputé stable (un événement daté et clos, une constante). Ta mémoire est admise pour celui-ci, à condition qu'elle reste cohérente avec toute preuve que tu peux consulter.",
            'date_explicite' => "Ce claim porte sa PROPRE date ou période. C'est elle, et non la date d'aujourd'hui ({{DATE}}), qui doit guider ta recherche et ton jugement : cherche ce qui concerne CETTE période précise, pas l'état actuel.",
            'relatif' => "Ce claim se date RELATIVEMENT au moment de sa soumission (« hier », « cette semaine », « cette année »). Résous cette date par rapport à la date de référence {{DATE}} — tu ne peux pas t'appuyer sur ta propre notion du présent, qui n'est pas celle de cette vérification.",
        ],
    ],

    /*
     | Le Hero du rapport (`corps.blade.php`), pour `MIXTE` et `INSUFFISANTE`
     | seulement — `HOMOGENE` reste l'affaire de `verdicts.rapport.*`.
     */
    'composition_rapport' => [
        'libelle' => [
            'mixte' => 'Affirmations contradictoires',
            'insuffisante' => 'Non tranché',
        ],
        'phrase' => [
            'mixte' => 'Ce contenu mêle des affirmations exactes et des affirmations fausses ou trompeuses.',
            'insuffisante' => "Aucune affirmation de ce contenu ne pouvait être soumise à vérification factuelle.",
        ],
    ],

    /*
     | Ce que la communauté PENSE, jamais ce que la plateforme ÉTABLIT — les
     | verdicts officiels vivent dans `verdicts.php` et n'ont rien à faire
     | ici. Les capitales sont celles du prototype, reprises telles quelles.
     */
    'avis_communaute' => [
        'true' => 'VRAI',
        'doubt' => 'DOUTEUX',
        'false' => 'FAUX',
    ],
];
