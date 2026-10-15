<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page: Features
|--------------------------------------------------------------------------
|
| The filter tabs carry their own markup, including `data-feature-filter`
| values. Those values are IDENTIFIERS read by the script, never labels: they
| stay in French. Translating them would silently break the filtering, and
| nothing would report it.
|
*/

return [
    'explorer_nos_fonctionnalites' => 'Explore our features',

    'illustration_technologique_verif_all' => 'Vérif\'All technology illustration',

    'voir_les_outils_integres' => 'See the built-in tools',

    'illustration_de_l_orchestrateur' => 'Illustration of the orchestrator',

    'commencer_a_apprendre' => 'Start learning',

    'illustration_du_lms' => 'Illustration of the learning platform',

    'explorer_la_communaute' => 'Explore the community',

    'verifier_une_information_maintenant' => 'Check a piece of information now',

    'filtrer_les_fonctionnalites' => 'Filter the features',

    // Etiquettes de bas de carte : la technique employee, en deux mots.
    'detection_ia_analyse_semantique' => 'AI Detection & Semantic Analysis',
    'recherche_inversee_metadonnees' => 'Reverse Search & Metadata',
    'detection_deepfake_analyse_d_images_cles' => 'Deepfake Detection & Key Frame Analysis',
    'analyse_spectrale_detection_vocale_ia' => 'Spectral Analysis & AI Voice Detection',
    'quiz_interactifs_certificats' => 'Interactive Quizzes & Certificates',
    'moderation_expertise' => 'Moderation & Expertise',
    'curation_fiabilite' => 'Curation & Reliability',
    'intelligence_collective' => 'Collective Intelligence',

    'reponse_comment_l_ia_detecte_t_elle' => 'We use several engines (GPTZero, Google Vision, and others) that analyse the structure of the language, where the web servers are hosted and how far back the images go, so the evidence can be cross-checked.',

    'reponse_mes_donnees_signalees_sont_elles_privees' => 'Yes. What you submit is analysed anonymously. We do not share your identity when a public debunk is published.',

    'reponse_c_est_quoi_un_deepfake' => 'A deepfake is a video or an audio clip created by AI to imitate a real person convincingly. Vérif\'All has dedicated tools for detecting this kind of synthetic content.',

    'toute_la_puissance_du_fact_checking_a' => 'All the power of fact-checking, within reach.',

    'decouvrez_l_arsenal_d_outils_que_verif' => 'Discover the set of tools Vérif’All puts at your disposal to analyse, understand and counter disinformation in all its forms.',

    'un_ecosysteme_integre_pour_retablir_la_verite' => 'An integrated ecosystem for setting the record straight',

    'ne_vous_contentez_pas_d_un_seul' => 'Do not settle for a single opinion. For every piece of content you submit, Vérif’All queries several specialised technologies (AI detection, reverse search, source analysis) and brings the results together into one report that is easy to follow.',

    'orchestrateur_de_verification' => 'Verification orchestrator',

    'notre_technologie_analyse_votre_contenu_en_le' => 'Our technology analyses your content by submitting it to the best detection tools at the same time.',

    'un_systeme_educatif_interactif' => 'An interactive learning system',

    'ne_vous_contentez_pas_de_verifier_apprenez' => 'Do not stop at checking. Learn to recognise manipulation techniques through our tutorials, quizzes and certificates.',

    'rejoignez_une_communaute_engagee' => 'Join a committed community',

    'participez_a_notre_forum_echangez_avec_nos' => 'Take part in our forum, exchange with our experts and help build an internet that can be trusted.',

    'un_doute_verifiez_en_quelques_secondes' => 'In doubt? Check in seconds.',

    'notre_outil_principal_est_concu_pour_etre' => 'Our main tool is designed to be as simple as possible. Paste a link, upload an image or an audio file, and let our technology give you a first verdict, clear and quick.',

    'decouvrez_nos_fonctionnalites' => 'Discover our features',

    /*
     | Les valeurs `data-feature-filter` restent en français : ce sont des
     | IDENTIFIANTS lus par le script, jamais des libellés. Les traduire
     | casserait le filtrage en silence.
     */
    'toutes' => '<button type="button" class="feat-tab is-active rounded-lg bg-[#f8f9fa] text-sm font-medium px-3.5 py-2 transition-colors" data-feature-filter="" role="tab" aria-selected="true">All</button>',
    'verification' => '<button type="button" class="feat-tab rounded-lg bg-[#f8f9fa] text-sm font-medium px-3.5 py-2 transition-colors" data-feature-filter="verification" role="tab" aria-selected="false">Verification</button>',
    'education' => '<button type="button" class="feat-tab rounded-lg bg-[#f8f9fa] text-sm font-medium px-3.5 py-2 transition-colors" data-feature-filter="education" role="tab" aria-selected="false">Learning</button>',
    'communaute' => '<button type="button" class="feat-tab rounded-lg bg-[#f8f9fa] text-sm font-medium px-3.5 py-2 transition-colors" data-feature-filter="communaute" role="tab" aria-selected="false">Community</button>',
    'plateforme' => '<button type="button" class="feat-tab rounded-lg bg-[#f8f9fa] text-sm font-medium px-3.5 py-2 transition-colors" data-feature-filter="plateforme" role="tab" aria-selected="false">Platform</button>',

    'verification_de_texte' => 'Text checking',
    'analysez_les_articles_et_messages_pour_detecter' => 'Analyse articles and messages to detect AI-generated content, plagiarism and the markers of disinformation.',

    'verification_d_image' => 'Image checking',
    'retrouvez_l_origine_d_une_photo_sa' => 'Trace where a photograph comes from and when it first appeared, and detect manipulation through reverse search and forensic analysis.',

    'verification_de_video' => 'Video checking',
    'identifiez_les_videos_sorties_de_leur_contexte' => 'Identify videos taken out of context and obtain a confidence score on how likely a deepfake is.',

    'verification_audio' => 'Audio checking',
    'detectez_les_voix_clonees_par_intelligence_artificielle' => 'Detect voices cloned by artificial intelligence, and manipulation in audio messages and recordings.',

    'module_d_apprentissage' => 'Learning module',
    'devenez_autonome_grace_a_nos_tutoriels_interactifs' => 'Become self-reliant through our interactive tutorials. Learn the techniques used by professional fact-checkers and put your skills to the test.',

    'communaute_forum' => 'Community & forum',
    'echangez_avec_des_citoyens_des_journalistes_et' => 'Exchange with citizens, journalists and experts on our forum. Ask questions and share what you find.',

    'hub_opportunites' => 'Opportunities hub',
    'accedez_a_un_flux_d_actualites_provenant' => 'Access a news feed from reliable, verified media sources, together with the official e-services of Benin.',

    'signalement' => 'Reporting',
    'vous_avez_un_doute_soumettez_le_nous' => 'In doubt? Send it to us. Every report helps our experts and our algorithms identify new waves of disinformation.',

    'une_rumeur_ou_fake_news_vous_fait' => 'Is a rumour or a piece of fake news making you doubt?',
    'ne_soyez_plus_le_relais_de_la' => 'Stop being a relay for disinformation. Use our fact-checking tools to get a first verdict in seconds.',

    'questions_frequentes' => 'Frequently asked questions',

    'comment_l_ia_detecte_t_elle_les' => '<button class="accordion-button" type="button" aria-expanded="false"> How does AI detect fake news?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'mes_donnees_signalees_sont_elles_privees' => '<button class="accordion-button" type="button" aria-expanded="false"> Is the content I report kept private?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'c_est_quoi_un_deepfake' => '<button class="accordion-button" type="button" aria-expanded="false"> What is a “deepfake”?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
];
