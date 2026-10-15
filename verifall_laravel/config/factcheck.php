<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Coût d'une vérification, par type de contenu
    |--------------------------------------------------------------------------
    |
    | Valeurs affichées par le prototype (verifier.html). Le coût est annoncé
    | AVANT le lancement et débité APRÈS acceptation : une personne doit savoir
    | ce qu'elle dépense avant de le dépenser.
    |
    | Ces montants sont lus par le serveur seul. Le client n'envoie qu'un type.
    |
    */

    'costs' => [
        'text' => 1,
        'url' => 1,
        'image' => 2,
        'video' => 5,
        'audio' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Adaptateurs simulés
    |--------------------------------------------------------------------------
    |
    | Aucune clé d'API n'est disponible : le pipeline tourne avec des
    | adaptateurs simulés, qui respectent le MÊME contrat que les vrais. Le
    | jour où les clés arrivent, on bascule cette variable et on branche les
    | implémentations réelles — le scoring, le rapport et l'écran ne bougent pas.
    |
    | En production avec `true`, l'application rendrait des verdicts inventés :
    | l'orchestrateur refuse donc de démarrer dans ce cas.
    |
    */

    'fake_adapters' => env('FAKE_ADAPTERS', true),

    /*
    |--------------------------------------------------------------------------
    | Collecteur de preuves — phase 6, V-10
    |--------------------------------------------------------------------------
    |
    | Indépendant de `fake_adapters` : celui-ci décide si les MOTEURS sont
    | simulés, celui-ci décide si les preuves qu'ils citent sont réellement
    | RÉCUPÉRÉES ({@see CollecteurDePreuves}) avant d'être enregistrées. Les
    | deux peuvent varier séparément — notamment en test, où les moteurs
    | restent simulés mais où l'on veut éprouver le fetch réel sur de vraies
    | pages HTTP factices.
    |
    | Faux par défaut : brancher ce collecteur fait sortir du réseau à chaque
    | vérification (délai borné à 8 s par page, en parallèle — voir
    | `UrlSandbox`), un changement de comportement qui doit être une décision
    | explicite, pas un effet de bord d'une mise à jour.
    |
    */

    'collecteur_de_preuves_actif' => env('COLLECTEUR_DE_PREUVES_ACTIF', false),

    /*
    |--------------------------------------------------------------------------
    | Pondération des moteurs
    |--------------------------------------------------------------------------
    |
    | Tous les moteurs ne se valent pas. Un fact-check antérieur publié par un
    | tiers vaut mieux qu'une intuition de modèle de langage ; un détecteur d'IA
    | est un SIGNAL, pas un verdict — d'où son poids délibérément faible.
    |
    */

    'weights' => [
        /*
         | Un fact-check DEJA PUBLIE par une redaction est une PREUVE ; un
         | verdict de modele est une INFERENCE. Sur une plateforme de
         | fact-checking, la preuve prime — sinon nous ne sommes qu'un agent
         | conversationnel de plus.
         */
        'google_factcheck' => 1.0,

        // Faits durs, verifiables par quiconque : anteriorite d'une image, age
        // d'un domaine. Ils ne raisonnent pas, ils constatent.
        'tineye' => 0.9,
        'technical_analysis' => 0.9,

        /*
         | ANCRES : ces moteurs citent les pages qu'ils ont reellement ouvertes.
         | Leur avis est adosse a des sources verifiables, pas a un souvenir
         | d'entrainement — l'asymetrie que ni la documentation ni le moteur de
         | juillet ne faisaient.
         */
        'gemini' => 0.75,
        'perplexity_search' => 0.75,
        'openai' => 0.70,

        /*
         | SANS acces web : DeepSeek juge de memoire. Precieux comme second
         | avis independant — deux modeles de familles differentes hallucinent
         | rarement la meme chose — mais il ne peut rien prouver.
         */
        'deepseek' => 0.55,

        // Apportent des sources sans trancher : leur poids ne sert qu'aux rares
        // cas ou ils rendent tout de meme un verdict.
        'google_custom_search' => 0.6,
        'knowledge_graph' => 0.6,
        'vision' => 0.6,
        'transcription' => 0.6,

        // Signal, jamais verdict — ecarte du score par MoteurAnalyse.
        'ai_detector' => 0.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Décision
    |--------------------------------------------------------------------------
    |
    | Un verdict n'est retenu que s'il devance nettement le suivant ET dépasse
    | un plancher de confiance. Sans le premier seuil on trancherait à
    | pile ou face ; sans le second on affirmerait sur presque rien.
    |
    */

    'decision' => [
        'ecart_minimum' => 25,
        'score_minimum' => 60,

        // En dessous, le rapport sort quand même — mais en annonçant
        // franchement qu'il est partiel, et les unités sont remboursées.
        'moteurs_minimum' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exécution
    |--------------------------------------------------------------------------
    */

    'runtime' => [
        /*
         | Délai par défaut d'un appel de moteur, en secondes.
         |
         | Porté de 20 à 20… inchangé pour le défaut, mais les deux moteurs qui
         | le dépassaient ont désormais le leur. Ce qu'il faut savoir avant d'y
         | toucher, parce que trois paramètres se regardent :
         |
         |   · Gemini interroge la recherche Google avant de rédiger. Il
         |     dépassait franchement 20 s et rendait `cURL 28` — un échec muet
         |     qui faisait tomber le quorum à un seul moteur, donc un rapport
         |     partiel et un remboursement. Son délai est désormais réglable ici
         |     (`timeout_gemini`), à 35 s.
         |   · DeepSeek et OpenAI répondent en 2 à 3 secondes et gardent les
         |     20 s du défaut : la marge ne coûte rien en cas nominal et couvre
         |     une latence réseau depuis Cotonou. Le calibrage proposé était
         |     15 s ; 20 s a été retenu, parce qu'un moteur qu'on coupe à
         |     l'avant-dernière seconde est un moteur qu'on perd pour rien.
         |   · Le PLAFOND de la chaîne — 35 + 20 + 20, soit environ 75 s — doit
         |     rester sous `AnalyserVerification::$timeout` (180 s). Il l'est
         |     largement.
         |
         | Ce que `queue:work --max-time=55` NE fait PAS, contrairement à ce
         | qu'on croit en le lisant : il n'interrompt aucun job en cours.
         | Laravel teste le temps écoulé ENTRE deux jobs
         | (`Worker::stopIfNecessary`). Un job démarré à la 45e seconde va au
         | bout de ses 75 s, puis le worker rend la main. Et deux workers lancés
         | par le cron ne peuvent pas se voler un job : `retry_after` vaut
         | 3900 s dans `config/queue.php`, et `OrchestrateurVerification::prendreEnMain()`
         | prend la ligne par un UPDATE conditionnel.
         */
        'timeout_seconds' => 20,

        // Gemini seul : recherche Google puis rédaction, dans le même appel.
        'timeout_gemini' => 35,

        // TranscriptionAdapter seul : l'upload du fichier et sa transcription
        // par OpenAI prennent plus longtemps qu'un simple prompt texte.
        'timeout_transcription' => 60,

        /*
         | Fenêtre de fraîcheur de la recherche web (Perplexity) — REPLI
         | GLOBAL, pour les appels sans classification temporelle (phase 3) :
         | moteurs interrogés hors du pipeline de claims, tests existants.
         |
         | Valeurs acceptées par le fournisseur : `hour`, `day`, `week`,
         | `month`, `year`.
         */
        'fraicheur_recherche' => env('FACTCHECK_FRAICHEUR', 'year'),

        /*
         | Phase 3 — la fenêtre dépend désormais de la PORTÉE TEMPORELLE du
         | claim, et non plus d'un seul réglage global : un état présent
         | n'a pas la même exigence de fraîcheur qu'un fait historique.
         | Cette carte SURCLASSE les défauts codés dans
         | `PorteeTemporelle::fraicheurRecherche()` ; `null` désactive le
         | filtre (une date explicite ancre déjà la recherche).
         |
         | @see \App\Enums\PorteeTemporelle::fraicheurRecherche()
         */
        'fraicheur_par_portee' => [
            'present' => env('FACTCHECK_FRAICHEUR_PRESENT', 'month'),
            'relatif' => env('FACTCHECK_FRAICHEUR_RELATIF', 'week'),
            'date_explicite' => null,
            'historique' => env('FACTCHECK_FRAICHEUR_HISTORIQUE', 'year'),
        ],

        'retries' => 2,

        // Disjoncteur : après 3 échecs, le moteur est écarté 5 minutes. Sans
        // cela, un service en panne ferait attendre 20 secondes chaque
        // vérification, pour rien.
        'circuit_breaker' => ['echecs' => 3, 'repos_minutes' => 5],

        /*
         | Analyses par heure et par compte. Distinct du cout en unites :
         | les unites bornent la depense d'une personne, ce plafond borne la
         | charge sur des services tiers factures a l'appel. Un compte
         | recharge pourrait sinon lancer mille analyses en une minute et
         | epuiser les quotas de toute la plateforme.
         */
        'analyses_par_heure' => 20,

        /*
         | REPRISE. Une analyse qui n'aboutit pas parce que les moteurs sont
         | saturés n'est pas ratée : elle est à refaire plus tard. Elle est
         | reprogrammée, et la personne prévenue — « revenez dans douze
         | minutes » vaut mieux qu'un rapport creux.
         |
         | Le délai est volontairement dans la fourchette annoncée à l'écran :
         | promettre dix minutes et reprendre au bout de trois donnerait
         | l'impression d'un système erratique.
         */
        'reprise' => [
            'delai_minutes' => 12,
            'tentatives_max' => 3,
        ],
    ],

];
