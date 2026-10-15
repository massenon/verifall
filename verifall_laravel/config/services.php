<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Moteurs d'analyse (fact-checking)
    |--------------------------------------------------------------------------
    |
    | Aucune valeur n'est écrite ici : seulement des noms de variables. Les clés
    | vivent dans `.env`, hors du dépôt, et n'apparaissent ni dans un journal,
    | ni dans un rapport, ni dans un message d'exception.
    |
    | Un adaptateur dont la clé est absente ne lève pas : il rend un échec, et
    | l'analyse continue avec les autres moteurs.
    |
    */

    'gemini' => [
        /*
         | PLUSIEURS clés, essayées à tour de rôle. Le quota gratuit de Gemini
         | s'épuise vite ; avec une seule clé, la plateforme s'arrête net au
         | milieu de la journée. Une clé qui renvoie 429 ou 403 est mise au
         | repos quelques minutes, les autres prennent le relais.
         |
         | array_values(array_filter(...)) : les emplacements vides sont
         | écartés et les index renumérotés, pour que le disjoncteur — qui
         | s'indexe dessus — ne pointe jamais sur un trou.
         */
        'keys' => array_values(array_filter([
            env('GEMINI_API_KEY'),
            env('GEMINI_API_KEY_1'),
            env('GEMINI_API_KEY_2'),
            env('GEMINI_API_KEY_3'),
            env('GEMINI_API_KEY_4'),
        ])),

        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),

        /*
         | Le repli valait `gemini-1.5-flash`, et ce modèle N'EXISTE PLUS.
         |
         | Relevé le 10 septembre 2026 sur le catalogue officiel : 1.5 n'y
         | figure nulle part, pas même dans la section « Previous models », qui
         | recense pourtant la génération SUIVANTE — 2.0 Flash et 2.0 Flash-Lite
         | y sont marqués « Shut down ». Un modèle absent d'un catalogue qui
         | liste ses propres retraits rend `404 model_not_found`.
         |
         | Le repli n'était donc pas dégradé : il était MORT, et muet. Le 404 ne
         | figurait dans aucune liste de codes traités, la boucle passait à la
         | clé suivante où il se reproduisait, et rien n'était journalisé. Il
         | n'a jamais pris le relais et ne le pouvait pas.
         |
         | `gemini-2.5-flash-lite` le remplace : même génération que le modèle
         | principal — donc même accès pour une clé qui fonctionne déjà —, plus
         | rapide et moins cher, et il figure au catalogue. Ce n'est pas le
         | meilleur choix dans l'absolu : le catalogue recommande aujourd'hui la
         | famille 3.x, et la 2.5 est annoncée comme dépréciée. Mais changer de
         | génération sans pouvoir vérifier que les clés du projet y ont accès
         | remplacerait un repli mort par un autre.
         |
         | `AdaptateurHttp::signalerSiModeleInconnu()` journalise désormais tout
         | 404 de modèle : la prochaine disparition se verra le jour même.
         */
        'model_fallback' => env('GEMINI_MODEL_FALLBACK', 'gemini-3.1-flash-lite'),
    ],

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),

        /*
         | Le modèle était écrit EN DUR dans l'adaptateur, seul de tous les
         | moteurs à l'être — Gemini et OpenAI lisaient déjà le leur ici.
         |
         | La documentation du fournisseur, relevée le 10 septembre 2026, ne
         | liste plus `deepseek-chat` : elle donne `deepseek-v4-flash`,
         | `deepseek-v4-pro` et `deepseek-v4-flash-vision-exp`. L'alias
         | fonctionne peut-être encore — un alias survit souvent au modèle
         | qu'il désignait —, et le défaut n'a donc PAS été changé sur la seule
         | foi d'une absence : `verifall:sonder-les-moteurs` interroge `/models`
         | et tranchera avec la vraie clé.
         |
         | Ce qui change ici, c'est qu'il devienne réglable sans déploiement.
         */
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        // Réglable sans déploiement : les catalogues de modèles bougent
        // vite, et un modèle retiré éteindrait le moteur du jour au lendemain.
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        // Modèle de TranscriptionAdapter — distinct du modèle de raisonnement
        // ci-dessus, qui ne lit aucun fichier audio/vidéo.
        'transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-4o-transcribe'),
    ],
    'perplexity' => ['key' => env('PERPLEXITY_API_KEY')],

    'google_factcheck' => ['key' => env('GOOGLE_FACT_CHECK_KEY')],
    'google_cse' => [
        'key' => env('GOOGLE_CUSTOM_SEARCH_KEY'),
        'id' => env('GOOGLE_CSE_ID'),
    ],
    'google_kg' => ['key' => env('GOOGLE_API_KEY')],
    'google_safe_browsing' => ['key' => env('GOOGLE_SAFE_BROWSING_KEY')],
    'google_vision' => ['key' => env('GOOGLE_API_KEY')],

    'tineye' => ['key' => env('TINEYE_API_KEY')],
    'saucenao' => ['key' => env('SAUCENAO_API_KEY')],
    'aiornot' => ['key' => env('AIORNOT_API_KEY')],
    'gptzero' => ['key' => env('GPTZERO_API_KEY')],
    'winston' => ['key' => env('WINSTON_AI_API_KEY')],
    'huggingface' => ['key' => env('HUGGINGFACE_API_KEY')],

    /*
    |--------------------------------------------------------------------------
    | Fournisseurs d'identité
    |--------------------------------------------------------------------------
    |
    | Aucune valeur de repli : sans identifiants, `FournisseurSocial::estConfigure()`
    | rend faux, le bouton disparaît de l'écran et la route répond 404. Proposer
    | une connexion qui se solde par une erreur du fournisseur est pire que ne
    | rien proposer.
    |
    | L'URL de retour est calculée à partir de `APP_URL` : elle doit être
    | déclarée à l'identique dans la console du fournisseur, au caractère près.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/retour'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL').'/auth/facebook/retour'),
    ],

];
