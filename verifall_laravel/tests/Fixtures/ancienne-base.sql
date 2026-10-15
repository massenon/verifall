-- Extrait RÉDUIT de l'ancienne base, au format exact de mysqldump.
--
-- Huit comptes et six signalements suffisent à éprouver tout ce qui peut mal
-- tourner : compte déjà présent, compte jamais vérifié, compte effacé,
-- soumission anonyme, analyse échouée, texte contenant une virgule, et — depuis
-- le relevé de propreté du 2 septembre 2026 — un compte au nom sali d'espaces
-- et un compte qui se connectait sans avoir jamais vérifié son courriel.
--
-- Les empreintes sont du VRAI bcrypt coût 12 (mot de passe « motdepasse »).
--
-- ATTENTION : aucun commentaire `--` à l'intérieur d'une liste VALUES. Le
-- lecteur de dump arrête la collecte à la première ligne qui ne commence pas
-- par « ( » — comme le fait un vrai mysqldump, qui n'en insère jamais là. Deux
-- comptes et un signalement ont été silencieusement ignorés pour cette raison.

INSERT INTO `roles` (`id`, `name`, `guard_name`) VALUES
(1, 'Admin', 'web'),
(2, 'Mentor', 'web'),
(3, 'User', 'web');

INSERT INTO `users` (`id`, `uuid`, `name`, `firstname`, `lastname`, `email`, `phone`, `profession`, `remaining_units`, `points`, `passed_onboarding_quiz`, `otp_code`, `email_verified_at`, `password`, `remember_token`, `last_login_at`, `created_at`, `updated_at`, `notify_alerts`, `notify_news`, `current_streak`, `max_streak`, `has_seen_tour`, `deleted_at`) VALUES
(1, 'aaaaaaaa-0000-4000-8000-000000000001', 'Aïcha Dossou', 'Aïcha', 'Dossou', 'aicha@ancienne.test', '97000001', 'Journaliste', 12, 340, 1, NULL, '2026-04-01 10:00:00', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-08-01 09:00:00', '2026-03-15 08:00:00', '2026-08-01 09:00:00', 1, 0, 3, 7, 1, NULL),
(2, 'aaaaaaaa-0000-4000-8000-000000000002', 'Koffi, le mentor', 'Koffi', 'Agbo', 'koffi@ancienne.test', NULL, 'Formateur', 0, 0, 0, NULL, '2026-04-02 10:00:00', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, NULL, '2026-03-16 08:00:00', '2026-03-16 08:00:00', 1, 1, 0, 0, 0, NULL),
(3, 'aaaaaaaa-0000-4000-8000-000000000003', 'Jamais vérifié', NULL, NULL, 'jamais@ancienne.test', NULL, NULL, 5, 0, 0, NULL, NULL, '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, NULL, '2026-05-01 08:00:00', '2026-05-01 08:00:00', 1, 0, 0, 0, 0, NULL),
(4, 'aaaaaaaa-0000-4000-8000-000000000004', 'Déjà présente', NULL, NULL, 'deja@nouvelle.test', NULL, NULL, 99, 999, 0, NULL, '2026-04-03 10:00:00', '$2y$12$qeuAEpprfKUvkJ6x2CD3Q.DrJp7.aaaa5mJ.ghBTmgVR3CLFEE.3O', NULL, NULL, '2026-03-17 08:00:00', '2026-03-17 08:00:00', 1, 0, 0, 0, 0, NULL),
(5, 'aaaaaaaa-0000-4000-8000-000000000005', 'Administrateur', NULL, NULL, 'admin@ancienne.test', NULL, NULL, 0, 0, 0, NULL, '2026-04-04 10:00:00', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, NULL, '2026-03-18 08:00:00', '2026-03-18 08:00:00', 1, 0, 0, 0, 0, NULL),
(6, 'aaaaaaaa-0000-4000-8000-000000000006', 'Compte effacé', NULL, NULL, 'efface@ancienne.test', NULL, NULL, 0, 0, 0, NULL, '2026-04-05 10:00:00', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, NULL, '2026-03-19 08:00:00', '2026-03-19 08:00:00', 1, 0, 0, 0, 0, '2026-07-01 12:00:00'),
(7, 'aaaaaaaa-0000-4000-8000-000000000007', 'Jean marie  EYEMI ', ' Jean marie ', ' EYEMI ', '  Sale@Ancienne.TEST ', ' 97000007 ', '  ', 0, 0, 0, NULL, '2026-04-06 10:00:00', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, NULL, '2026-03-20 08:00:00', '2026-03-20 08:00:00', 1, 0, 0, 0, 0, NULL),
(8, 'aaaaaaaa-0000-4000-8000-000000000008', 'Connecté sans vérifier', NULL, NULL, 'actif@ancienne.test', NULL, NULL, 0, 0, 0, NULL, NULL, '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-08-30 13:38:30', '2026-03-21 08:00:00', '2026-08-30 13:38:30', 1, 0, 0, 0, 0, NULL);

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(3, 'App\\Models\\User', 1),
(2, 'App\\Models\\User', 2),
(3, 'App\\Models\\User', 3),
(1, 'App\\Models\\User', 5);

INSERT INTO `signalements` (`id`, `uuid`, `title`, `content_type`, `theme`, `description`, `content_text`, `source_url`, `evidence_file`, `platform`, `status`, `processing_status`, `verdict`, `confidence_score`, `hate_speech_score`, `hate_speech_explanation`, `created_by`, `updated_by`, `assigned_to`, `deleted_at`, `created_at`, `updated_at`, `ai_explanation`, `is_helpful`, `is_published_to_hub`) VALUES
(1, 'bbbbbbbb-0000-4000-8000-000000000001', 'Ce remède, dit-on, soigne le paludisme', 'text', 'sante', 'Reçu sur WhatsApp', 'Le message complet', NULL, NULL, 'whatsapp', 'verified_false', 'completed', 'faux', 92, NULL, NULL, 1, 1, 2, NULL, '2026-06-01 08:00:00', '2026-06-01 09:00:00', 'Aucune source médicale ne soutient cette affirmation.', 1, 0),
(2, 'bbbbbbbb-0000-4000-8000-000000000002', 'Une image truquée', 'image', 'politique', NULL, NULL, NULL, 'preuves/photo.jpg', NULL, 'pending', 'submitted', NULL, 0, NULL, NULL, 1, NULL, NULL, NULL, '2026-06-02 08:00:00', '2026-06-02 08:00:00', NULL, NULL, 0),
(3, 'bbbbbbbb-0000-4000-8000-000000000003', 'Soumission anonyme', 'url', 'general', NULL, NULL, 'https://exemple.test/article', NULL, NULL, 'investigating', 'analyzing', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-03 08:00:00', '2026-06-03 08:00:00', NULL, NULL, 0),
(4, 'bbbbbbbb-0000-4000-8000-000000000004', 'Analyse échouée', 'text', 'general', NULL, 'Texte', NULL, NULL, NULL, 'failed', 'failed', NULL, 0, NULL, NULL, 3, NULL, NULL, NULL, '2026-06-04 08:00:00', '2026-06-04 08:00:00', NULL, NULL, 0),
(5, 'bbbbbbbb-0000-4000-8000-000000000005', 'Propos haineux', 'text', 'general', NULL, 'Texte', NULL, NULL, NULL, 'debunked', 'completed', 'faux', 78, 64, 'Contient des propos visant un groupe.', 1, NULL, NULL, '2026-07-15 10:00:00', '2026-06-05 08:00:00', '2026-07-15 10:00:00', 'Le contenu est faux et hostile.', NULL, 0),
(6, 'bbbbbbbb-0000-4000-8000-000000000006', '  Un titre coupé\nen deux lignes  ', 'text', '  sante  ', '  Premier paragraphe.\n\nSecond paragraphe.  ', '  Le texte soumis.  ', NULL, NULL, NULL, 'verified_true', 'completed', 'vrai', 88, NULL, NULL, 1, NULL, NULL, NULL, '2026-06-06 08:00:00', '2026-06-06 09:00:00', 'Vérifié.', NULL, 0),
(7, 'bbbbbbbb-0000-4000-8000-000000000007', 'Analyse Invité : https://www.inserm.fr/c-est-quoi/', 'url', 'sante', NULL, NULL, 'https://www.inserm.fr/c-est-quoi/debout-cest-quoi-le-systeme-vestibulaire', NULL, NULL, 'verified_true', 'completed', 'vrai', 70, NULL, NULL, 1, NULL, NULL, NULL, '2026-06-07 08:00:00', '2026-06-07 09:00:00', 'Source institutionnelle.', NULL, 0),
(8, 'bbbbbbbb-0000-4000-8000-000000000008', 'Une description fleuve', 'text', 'general', 'ABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABAB', 'Le contenu réel.', NULL, NULL, NULL, 'verified_true', 'completed', 'douteux', 55, NULL, NULL, 1, NULL, NULL, NULL, '2026-06-08 08:00:00', '2026-06-08 09:00:00', 'Douteux.', NULL, 0);

INSERT INTO `analysis_reports` (`id`, `signalement_id`, `tool_name`, `raw_response`, `confidence_score`, `hate_speech_score`, `summary_explanation`, `created_at`, `updated_at`) VALUES
(1, 1, 'gemini', '{}', 90, NULL, 'Rien ne soutient l’affirmation.', '2026-06-01 08:30:00', '2026-06-01 08:30:00'),
(2, 1, 'google_fact_check', '{}', 95, NULL, 'Démenti déjà publié ailleurs.', '2026-06-01 08:31:00', '2026-06-01 08:31:00'),
(3, 5, 'gemini', '{}', 78, 64, 'Contenu faux et hostile.', '2026-06-05 08:30:00', '2026-06-05 08:30:00');

-- Le répertoire d'outils : les 21 lignes RÉELLES de `tools`, recopiées octet pour
-- octet depuis massenon_verifalltest.sql (et non retapées : incident n° 1).
-- « Test1test » est le déchet de test du dump réel, laissé tel quel.

INSERT INTO `tools` (`id`, `name`, `slug`, `category`, `description`, `content_type`, `languages`, `region`, `status_label`, `pricing_type`, `url_official`, `api_status`, `reliability_level`, `main_use_case`, `special_notes`, `logo_url`, `created_by`, `deleted_at`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 'Forensic', 'forensic', 'IMAGE', 'Suite d\'outils forensiques pour images numériques: détection de clonage, ELA', 'Image', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit (open source)', 'https://29a.ch/photo-forensics/', 'Non', 5, 'Analyse complète d\'authenticité d\'image, clonage, bruit visuel', 'Open source par Jonas Wagner; très complet pour un outil gratuit', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-20 13:40:02', 1),
(2, 'ExifTool', 'exiftool', 'IMAGE', 'Outil open source en ligne de commande pour lire, écrire et éditer les métadonnées EXIF de photos et vidéos.', 'Image / Vidéo', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit (open source)', 'https://exiftool.org', 'Oui (bibliothèque Perl)', 5, 'Extraction métadonnées GPS, timestamp, modèle appareil...', 'Outil de référence utilisé par tous les professionnels OSINT', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(3, 'PimEyes', 'pimeyes', 'IMAGE', 'Moteur de recherche facial puissant. Permet de retrouver toutes les photos d\'un visage sur internet.', 'Image (visages)', 'Toutes', 'Mondial', 'Outil externe', 'Freemium / Payant', 'https://pimeyes.com', 'Oui (payant)', 4, 'Identification de personnes, détection de faux profils...', 'Outil puissant mais sensible vie privée; usage responsable', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(4, 'Fake Image Detector', 'fake-image-detector', 'IMAGE', 'Plateforme en ligne de détection d\'images manipulées combinant analyse ELA et métadonnées EXIF.', 'Image', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://www.fakeimagedetector.com', 'Non', 3, 'Détection rapide de manipulation, vérification EXIF', 'Accès public sans inscription', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(5, 'Google Fact Check Explorer', 'google-fact-check-explorer', 'IMAGE', 'Outil beta de Google permettant de rechercher si une image a déjà été vérifiée par des organisations IFCN.', 'Image / Texte', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://toolbox.google.com/factcheck/explorer', 'Oui (API ClaimReview)', 5, 'Vérification si image/claim déjà fact-checké', 'Accès API pour journalistes accrédités', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(6, 'InVID / WeVerify Plugin', 'invid-weverify-plugin', 'VIDÉO', 'Extension navigateur de référence pour la vérification de vidéos et images sur réseaux sociaux.', 'Image / Vidéo', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://www.invid-project.eu/', 'Oui (partielle)', 5, 'Vérification vidéos virales, extraction keyframes, reverse search', 'Extension Chrome/Opera essentielle; intègre détection IA', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(7, 'Deepware Scanner', 'deepware-scanner', 'VIDÉO', 'Scanner deepfake gratuit et accessible au grand public. Analyse les vidéos en soumettant l\'URL.', 'Vidéo', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://scanner.deepware.ai/', 'Non', 3, 'Détection rapide de face-swap dans vidéos publiques', 'Gratuit et facile; se concentre sur face-swap', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(8, 'Microsoft Video Authenticator', 'microsoft-video-authenticator', 'VIDÉO', 'Outil de Microsoft pour analyser les photos et vidéos et fournir un score de confiance sur la manipulation IA.', 'Image / Vidéo', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit (accès limité)', 'https://news.microsoft.com/', 'Non (pas d\'API publique)', 5, 'Score d\'authenticité vidéo et image', 'Distribué en partenariat avec organisations électorales', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(9, 'ElevenLabs Speech Classifier', 'elevenlabs-speech-classifier', 'AUDIO', 'Outil gratuit d\'ElevenLabs pour détecter si un audio a été généré par leurs modèles.', 'Audio', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://elevenlabs.io/ai-speech-classifier', 'Non', 4, 'Détection rapide si audio généré avec ElevenLabs', 'Premier test utile mais non exhaustif', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(10, 'Resemble AI Detect', 'resemble-ai-detect', 'AUDIO', 'Détecteur audio deepfake de Resemble AI. Réseau neuronal identifiant les empreintes de synthèse vocale.', 'Audio', 'Toutes', 'Mondial', 'Outil externe', 'Payant (API)', 'https://www.resemble.ai/detect/', 'Oui (API)', 5, 'Détection voix synthétiques via API', '90% de détection audio deepfakes; couvre GenAI', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(11, 'GPTZero', 'gptzero', 'TEXTE', 'Détecteur de texte IA référence en contexte académique. Détecte GPT-4, Llama, Claude.', 'Texte', 'Toutes', 'Mondial', 'Outil externe', 'Freemium', 'https://gptzero.me', 'Oui (API)', 4, 'Détection contenu IA académique et journalistique', 'Créé par étudiant Princeton; 8 millions d\'utilisateurs', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(12, 'Snopes', 'snopes', 'TEXTE', 'L\'un des plus anciens et réputés sites de fact-checking (fondé 1994). Spécialisé rumeurs virales.', 'Texte / Image', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://www.snopes.com', 'Non', 5, 'Vérification hoaxes, rumeurs et légendes urbaines', 'Référence mondiale; excellent pour vérifier les chaînes', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(13, 'CopyLeaks AI Detector', 'copyleaks-ai-detector', 'TEXTE', 'Détecteur IA et plagiat avec support de 30+ langues dont le Français. Analyse phrase par phrase.', 'Texte', 'Toutes', 'Mondial', 'Outil externe', 'Freemium / Payant', 'https://copyleaks.com/', 'Oui (API)', 4, 'Détection texte IA multilanguage', 'Très efficace pour identifier le contenu paraphrasé', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(14, 'Africa Check', 'africa-check', 'AFRIQUE', 'Première organisation indépendante de fact-checking en Afrique. Bureaux au Sénégal, Nigeria.', 'Texte / Image / Vidéo', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://africacheck.org', 'Non', 5, 'Fact-checking politique, santé et économie en Afrique', 'Pionnier en Afrique; base de données exhaustive', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(15, 'Dubawa / CJID', 'dubawa-cjid', 'AFRIQUE', 'Projet de vérification indépendant en Afrique de l\'Ouest (Nigeria, Ghana, Sierra Leone, Gambie).', 'Texte / Audio / Image', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://dubawa.org', 'Oui (Chatbot WhatsApp)', 5, 'Fact-checking politique et santé en Afrique de l\'Ouest', 'Outil crucial pour Afrique où la radio reste majeure', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(16, 'PesaCheck', 'pesacheck', 'AFRIQUE', 'Plus grande initiative de fact-checking en Afrique, spécialisée dans les données budgétaires et financières.', 'Texte / Image', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://pesacheck.org', 'Oui (WhatsApp)', 5, 'Vérification promesses politiques et budgets', 'Opère dans 12 pays africains', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(17, 'Google Earth Pro', 'google-earth-pro', 'OSINT', 'Logiciel de cartographie et imagerie satellite historique. Permet de vérifier les lieux de vidéos.', 'Image / Vidéo', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://earth.google.com/web/', 'Non', 5, 'Vérification géolocalisation, comparaison imagerie historique', 'Imagerie satellite haute résolution', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(18, 'Bellingcat Toolkit', 'bellingcat-toolkit', 'OSINT', 'Répertoire collaboratif de centaines d\'outils d\'investigation open source classés par catégorie.', 'Tous types', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://www.bellingcat.com/resources/', 'Non', 5, 'Découverte d\'outils OSINT spécialisés', 'La bible des outils d\'enquête numérique', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(19, 'Wayback Machine', 'wayback-machine', 'SITE WEB', 'Archive internet permettant de consulter des versions passées de n\'importe quelle page web.', 'Site web / Texte', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://web.archive.org', 'Oui (API)', 5, 'Vérification modification d\'article, récupération contenu supprimé', 'Archive de 835 milliards de pages', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(20, 'VirusTotal', 'virustotal', 'SITE WEB', 'Service qui analyse fichiers et URLs avec 70+ antivirus et outils de détection de malware.', 'Site web / Fichier', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit / Payant', 'https://www.virustotal.com', 'Oui (API)', 5, 'Vérification sécurité URL, détection phishing', 'Standard industriel en cybersécurité', NULL, 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44', NULL),
(21, 'Test1test', 'test1test', 'IMAGE', 'Service qui analyse fichiers et URLs avec 70+ antivirus et outils de détection de malware.', 'Test', 'Toutes', 'Mondial', 'Outil externe', 'Gratuit', 'https://test.com', 'Non', 3, 'test', 'testtesttesttesttest', 'tools-logos/y3XTWI9kEX8mtuGbUavAH4cBYsL6ZsyScVeOWbcB.png', 1, '2026-05-24 16:29:50', '2026-03-23 19:27:57', '2026-05-24 16:29:50', 1);

-- `fact_check_tools` est VIDE dans le dump réel (0 ligne). Cette ligne-ci est
-- donc SYNTHÉTIQUE, et c'est la seule du fichier : elle éprouve qu'une clé
-- d'API présente dans un dump ultérieur n'est jamais reprise. Colonnes réelles.

INSERT INTO `fact_check_tools` (`id`, `name`, `slug`, `api_key`, `api_endpoint`, `is_active`, `config`, `created_at`, `updated_at`) VALUES
(1, 'Google Fact Check', 'google-fact-check', 'CLE-API-ANCIENNE-NE-DOIT-JAMAIS-ETRE-REPRISE', 'https://factchecktools.googleapis.com/v1alpha1/claims:search', 1, NULL, '2026-03-14 05:44:44', '2026-03-14 05:44:44');

-- ════════════════════════════════════════════════════════════════════════
-- Passe du 14 septembre 2026 (seconde) : sources, Académie, forum, Hub.
--
-- Chaque bloc ci-dessous est recopié OCTET POUR OCTET depuis
-- massenon_verifalltest.sql (extraction par numéro de ligne, jamais retapé :
-- incident n° 1). Les identifiants de comptes (user_id) sont les VRAIS.
--
-- La seule partie synthétique est le bloc `users` qui suit : les adresses
-- réelles des apprenants et des membres du forum sont des données
-- personnelles, qui n'ont rien à faire dans un dépôt de code. Les
-- identifiants 26 à 136 y reçoivent donc une adresse fictive ; l'identifiant
-- 5 (réel : un compte de l'équipe) est déjà « admin@ancienne.test » plus haut.

INSERT INTO `users` (`id`, `uuid`, `name`, `email`, `password`, `remember_token`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(26, 'cccccccc-0000-4000-8000-000000000026', 'Compte 26', 'compte-26@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(27, 'cccccccc-0000-4000-8000-000000000027', 'Compte 27', 'compte-27@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(28, 'cccccccc-0000-4000-8000-000000000028', 'Compte 28', 'compte-28@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(29, 'cccccccc-0000-4000-8000-000000000029', 'Compte 29', 'compte-29@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(34, 'cccccccc-0000-4000-8000-000000000034', 'Compte 34', 'compte-34@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(35, 'cccccccc-0000-4000-8000-000000000035', 'Compte 35', 'compte-35@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(36, 'cccccccc-0000-4000-8000-000000000036', 'Compte 36', 'compte-36@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(40, 'cccccccc-0000-4000-8000-000000000040', 'Compte 40', 'compte-40@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(51, 'cccccccc-0000-4000-8000-000000000051', 'Compte 51', 'compte-51@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(55, 'cccccccc-0000-4000-8000-000000000055', 'Compte 55', 'compte-55@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(57, 'cccccccc-0000-4000-8000-000000000057', 'Compte 57', 'compte-57@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(74, 'cccccccc-0000-4000-8000-000000000074', 'Compte 74', 'compte-74@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(88, 'cccccccc-0000-4000-8000-000000000088', 'Compte 88', 'compte-88@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(89, 'cccccccc-0000-4000-8000-000000000089', 'Compte 89', 'compte-89@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(93, 'cccccccc-0000-4000-8000-000000000093', 'Compte 93', 'compte-93@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(96, 'cccccccc-0000-4000-8000-000000000096', 'Compte 96', 'compte-96@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(104, 'cccccccc-0000-4000-8000-000000000104', 'Compte 104', 'compte-104@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(128, 'cccccccc-0000-4000-8000-000000000128', 'Compte 128', 'compte-128@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(136, 'cccccccc-0000-4000-8000-000000000136', 'Compte 136', 'compte-136@ancienne.test', '$2y$12$XKL4j0.e1MM1pZpd2LV9xergWiBYo9O/C.WyKnrZc6D.kAHnM8AaG', NULL, '2026-05-01 10:00:00', '2026-05-01 10:00:00', '2026-05-01 10:00:00');

-- Les sources vérifiées : 143 lignes réelles, et leurs 3 catégories.

INSERT INTO `verified_sources` (`id`, `name`, `url`, `trust_index`, `category_id`, `category`, `is_blacklisted`, `created_at`, `updated_at`) VALUES
(1, 'Gouvernement du Bénin (Site Officiel)', 'https://www.gouv.bj', 100, 1, 'institution', 0, '2026-03-17 07:37:03', '2026-03-24 01:27:00'),
(2, 'Ministère de la Santé - Bénin', 'https://sante.gouv.bj', 100, 1, 'institution', 0, '2026-03-17 07:37:03', '2026-03-24 01:27:17'),
(3, 'Africa Check (Partenaire IFCN)', 'https://africacheck.org', 95, 1, 'fact-checker', 0, '2026-03-17 07:37:03', '2026-03-26 01:22:20'),
(4, 'Site de Phishing \"Concours-Benin.top\"', 'https://concours-benin.top', 0, 1, 'social_account', 1, '2026-03-17 07:37:03', '2026-03-26 01:20:21'),
(7, 'Les Surligneurs', 'lessurligneurs.eu', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(8, '20 Minutes - Fake off', '20minutes.fr/monde/fake-off', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(9, 'TF1 - Les Vérificateurs', 'tf1info.fr/les-verificateurs', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(10, 'France Info - Vrai ou Fake', 'francetvinfo.fr/vrai-ou-fake', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(11, 'Le Figaro - Le Vrai du Faux', 'lefigaro.fr/actualite-france/le-vrai-du-faux', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(12, 'Libération - CheckNews', 'liberation.fr/checknews', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(13, 'Le Monde - Les Décodeurs', 'lemonde.fr/les-decodeurs', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(14, 'France 24 - Info ou Intox', 'france24.com/fr/emissions/info-ou-intox', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(15, 'Conspiracy Watch', 'conspiracywatch.info', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(16, 'Observatoire du conspirationnisme', 'observatoire-conspirationnisme.info', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(17, 'Inserm - Canal Détox', 'inserm.fr/c-est-quoi', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(18, 'Hoaxbuster', 'hoaxbuster.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(19, 'RTS Info', 'rts.ch/info', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(20, 'RTBF Faux', 'rtbf.be/info/dossier/faux', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(21, 'Radio Canada - Décrypteurs', 'ici.radio-canada.ca/decrypteurs', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(22, 'AFP Factuel', 'factuel.afp.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(23, 'Science Feedback', 'sciencefeedback.co', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(24, 'Climate Feedback', 'climatefeedback.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(25, 'Health Feedback', 'healthfeedback.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(26, 'Full Fact', 'fullfact.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(27, 'PolitiFact', 'politifact.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(28, 'Snopes', 'snopes.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(29, 'FactCheck.org', 'factcheck.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(30, 'The Washington Post Fact Checker', 'washingtonpost.com/news/fact-checker', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(31, 'Reuters Fact Check', 'reuters.com/fact-check', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(32, 'AP Fact Check', 'apnews.com/ap-fact-check', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(33, 'Bellingcat', 'bellingcat.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(34, 'First Draft', 'firstdraftnews.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(35, 'Poynter', 'poynter.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(36, 'Lead Stories', 'leadstories.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(37, 'Check Your Fact', 'checkyourfact.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(38, 'Media Matters', 'mediamatters.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(39, 'Vox', 'vox.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(40, 'Africa Check', 'africacheck.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(41, 'Dubawa', 'dubawa.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(42, 'PesaCheck', 'pesacheck.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(43, 'Congo Check', 'congocheck.net', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(44, 'Fact Crescendo', 'factcrescendo.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(45, 'Alt News', 'altnews.in', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(46, 'Boom Live', 'boomlive.in', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(47, 'Vishvas News', 'vishvasnews.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(48, 'Newschecker', 'newschecker.in', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(49, 'The Quint', 'thequint.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(50, 'Factly', 'factly.in', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(51, 'India Today Fact Check', 'indiatoday.in/fact-check', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(52, 'RMIT ABC Fact Check', 'abc.net.au/news/factcheck', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(53, 'AAP FactCheck', 'aap.com.au/factcheck', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(54, 'Maldita.es', 'maldita.es', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(55, 'Newtral', 'newtral.es', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(56, 'EFE Verifica', 'verifica.efe.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(57, 'Chequeado', 'chequeado.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(58, 'Aos Fatos', 'aosfatos.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(59, 'Agência Lupa', 'piaui.folha.uol.com.br/lupa', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(60, 'Estadão Verifica', 'estadao.com.br/estadao-verifica', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(61, 'Colombiacheck', 'colombiacheck.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(62, 'Animal Político', 'animalpolitico.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(63, 'Polígrafo', 'poligrafo.sapo.pt', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(64, 'Observador', 'observador.pt/factchecks', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(65, 'Correctiv', 'correctiv.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(66, 'Deutsche Welle', 'dw.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(67, 'dpa-Factchecking', 'dpa-factchecking.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(68, 'TjekDet', 'tjekdet.dk', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(69, 'Faktisk', 'faktisk.no', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(70, 'Källkritikbyrån', 'kallkritikbyran.se', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(71, 'VRT NWS', 'vrt.be/vrtnws', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(72, 'Knack', 'knack.be', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(73, 'Demagog', 'demagog.cz', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(74, 'Oštro', 'ostro.si', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(75, 'Faktograf', 'faktograf.hr', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(76, 'Istinomer', 'istinomer.rs', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(77, 'StopFake', 'stopfake.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(78, 'VoxCheck', 'voxukraine.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(79, 'Myth Detector', 'mythdetector.ge', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(80, 'Vistinomer', 'vistinomer.mk', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(81, 'Ellinika Hoaxes', 'ellinikahoaxes.gr', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(82, 'Teyit', 'teyit.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(83, 'Dogruluk Payi', 'dogrulukpayi.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(84, 'Fatabyyano', 'fatabyyano.net', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(85, 'Misbar', 'misbar.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(86, 'Rappler', 'rappler.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(87, 'Vera Files', 'verafiles.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(88, 'Taiwan FactCheck Center', 'tfc-taiwan.org.tw', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(89, 'Annie Lab', 'annielab.org', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(90, 'Tempo', 'tempo.co', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(91, 'Mafindo', 'mafindo.or.id', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(92, 'Liputan6', 'liputan6.com/cek-fakta', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(93, 'Kompas', 'kompas.com/cekfakta', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(94, 'CekFakta', 'cekfakta.com', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(95, 'InFact', 'infact.press', 100, 3, 'fact-checking', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(96, '20 Minutes', '20minutes.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(97, 'AFP', 'afp.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(98, 'BFM TV', 'bfmtv.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(99, 'Bloomberg', 'bloomberg.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(100, 'Capital', 'capital.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(101, 'Challenges', 'challenges.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(102, 'CNBC', 'cnbc.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(103, 'CNN', 'cnn.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(104, 'Courrier International', 'courrierinternational.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(105, 'Europe 1', 'europe1.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(106, 'Financial Times', 'ft.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(107, 'France Bleu', 'francebleu.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(108, 'France Culture', 'franceculture.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(109, 'France Info', 'francetvinfo.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(110, 'France Inter', 'franceinter.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(111, 'HuffPost', 'huffingtonpost.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(112, 'L\'Express', 'lexpress.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(113, 'L\'Humanité', 'humanite.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(114, 'L\'Obs', 'nouvelobs.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(115, 'L\'Opinion', 'lopinion.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(116, 'La Croix', 'la-croix.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(117, 'La Dépêche', 'ladepeche.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(118, 'La Tribune', 'latribune.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(119, 'Le Canard Enchaîné', 'lecanardenchaine.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(120, 'Le Figaro', 'lefigaro.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(121, 'Le Journal du Dimanche (JDD)', 'lejdd.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(122, 'Le Monde', 'lemonde.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(123, 'Le Parisien', 'leparisien.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(124, 'Le Point', 'lepoint.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(125, 'Les Echos', 'lesechos.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(126, 'Libération', 'liberation.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(127, 'Marianne', 'marianne.net', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(128, 'Mediapart', 'mediapart.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(129, 'Midi Libre', 'midilibre.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(130, 'Ouest-France', 'ouest-france.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(131, 'Paris Match', 'parismatch.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(132, 'Public Sénat', 'publicsenat.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(133, 'Radio France', 'radiofrance.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(134, 'Reuters', 'reuters.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(135, 'RFI', 'rfi.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(136, 'RMC', 'rmc.bfmtv.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(137, 'RTL', 'rtl.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(138, 'Slate', 'slate.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(139, 'Sud Ouest', 'sudouest.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(140, 'TF1', 'tf1.fr', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(141, 'The New York Times', 'nytimes.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(142, 'The Wall Street Journal', 'wsj.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(143, 'TV5 Monde', 'tv5monde.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(144, 'Valeurs Actuelles', 'valeursactuelles.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04'),
(145, 'The Washington Post', 'washingtonpost.com', 100, 2, 'reference', 0, '2026-05-06 09:32:04', '2026-05-06 09:32:04');

INSERT INTO `verified_source_categories` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Medias Publics', 'medias-publics', 'Medias Publics', '2026-03-24 01:26:41', '2026-03-24 01:26:41'),
(2, 'Reference', 'reference', 'reference', '2026-03-24 01:26:41', '2026-03-24 01:26:41'),
(3, 'Fact-checking', 'fact-checking', 'fact-checking', '2026-03-24 01:26:41', '2026-03-24 01:26:41');

-- L'Académie : cours, sections, leçons, quiz, pièces jointes, inscriptions,
-- leçons lues, tentatives — toutes les lignes réelles.

INSERT INTO `courses` (`id`, `uuid`, `category_id`, `title`, `slug`, `description`, `level`, `language`, `has_certificate`, `requirements`, `outcomes`, `duration_total`, `is_published`, `is_premium`, `points_required`, `thumbnail_url`, `meta_title`, `meta_description`, `meta_keywords`, `instructor_id`, `created_by`, `deleted_at`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 'af486735-77cd-4b9c-a2dc-853c6e3de653', 6, 'TEST TEST TEST TEST TEST', 'test-test-test-test-test', '<p>PMOLIUJYTGRSEFQRBJNLKNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNN</p>', 'beginner', 'Français', 1, '[\".KJHGFDXFCGVBN?\"]', '[\"MLIKUGYTFGDRSFGHJKL\"]', NULL, 0, 0, 0, 'courses/eiepxifAAlUDDBaqys4naT2dOnBoxXKDZm4f9Sun.jpg', 'TEST TEST TEST TEST TEST', 'TEST TEST TEST TEST TEST', 'TEST TEST TEST TEST TEST', 19, 19, '2026-05-24 00:46:01', '2026-05-13 01:47:58', '2026-05-24 00:46:01', NULL),
(2, '05f02a9e-0e28-4e2d-b8c1-a19c3b78a78c', 6, 'MLJKHGFDXCGVBJKLMLKJGHGXGFCHJK', 'mljkhgfdxcgvbjklmlkjghgxgfchjk', '<p>MLHKHGFDWCVBNLkJHKGJWXCLM%L</p><p>MLJKGGDFDSHJKJLKM%LKGFDWD%LMLKDDWHJKMIUYFDF</p>', 'beginner', 'Français', 1, '[\".JVHCGXFCHVJBNKJVH\"]', '[\"JBHVGCV\"]', NULL, 0, 0, 0, 'courses/nw8w2bqFma6re2sqF82fbM8i68tT2HYqFP01i4Te.jpg', 'LKHJGHGFDXCGVHJ', 'MLMJKHJGHFGDFDXGCH', 'LKJGHGFDXGCHVJ', 19, 1, '2026-05-23 22:36:40', '2026-05-13 02:01:59', '2026-05-23 22:36:40', NULL),
(3, '582fc409-b50d-4950-aa12-723ea6e8dafd', 10, 'Introduction au fact-checking -  CLOM Vérif\'All', 'introduction-au-fact-checking-clom-verifall', '<p>Ce premier cours constitue votre point d\'entrée dans le parcours de formation au fact-checking de Vérif\'All. Avant d\'entrer dans la pratique, il pose le cadre. Comment fonctionne ce CLOM, et pourquoi le fact-checking est devenu une compétence citoyenne indispensable pour tous, pas seulement pour les journalistes. Un quiz d\'introduction vous permettra de tester vos premiers réflexes face aux fausses informations.</p>', 'beginner', 'Français', 1, '[\"Aucun prérequis technique. Avoir un smartphone ou un ordinateur connecté à internet.\"]', '[\"Comprendre l\'organisation et les objectifs du CLOM\", \"Tester ses premiers réflexes face aux fausses informations\", \"Comprendre les enjeux actuels du fact-checking en Afrique de l\'Ouest\"]', NULL, 1, 0, 0, 'courses/KAC40Y0Ma5iKpOVgRz6FpAykcmo0pV2CoyjtGsH6.jpg', 'Introduction au fact-checking -  CLOM Vérif\'All', 'Démarrez votre formation au fact-checking avec Vérif\'All. Ce cours d\'introduction présente le parcours et teste vos premiers réflexes face aux fausses informations.', 'fact-checking, fake news, désinformation, Afrique, formation en ligne, Vérif\'All, CLOM', 14, 1, NULL, '2026-05-23 22:05:05', '2026-06-09 22:57:26', 1),
(4, '6958cbc0-7a04-4242-a9b1-ff66a429b202', 6, 'Généralités sur les fake news  -  Comprendre pour mieux détecter', 'generalites-sur-les-fake-news-comprendre-pour-mieux-detecter', '<p>Ce cours pose les bases conceptuelles indispensables avant d\'entrer dans la pratique du fact-checking. Vous apprendrez à distinguer les différents types de fausses informations  (fake news, hoax, infox, clickbait, deepfake)  à comprendre leurs mécanismes de diffusion et à reconnaître leurs différentes formes. Des exemples concrets tirés du contexte béninois et ouest-africain illustrent chaque notion pour une appropriation immédiate.</p>', 'beginner', 'Français', 1, '[\"Avoir complété le Cours : Introduction au fact-checking\"]', '[\"Définir les concepts de fake news, hoax, infox, clickbait et deepfake\", \"Comprendre pourquoi et comment les fausses informations se propagent\", \"Identifier les différents formats de fake news \", \"Reconnaître les types de contenus manipulateurs dans son environnement numérique\"]', NULL, 1, 0, 0, 'courses/w7MoPEx0YfE0y0MrBl72jJOnkqzkHRsrrCQsKF32.jpg', 'Comprendre les fake news - Formation fact-checking Vérif\'All', 'Apprenez à reconnaître et comprendre les fake news, hoax et deepfakes. Formation gratuite au fact-checking adaptée au contexte africain, Vérif\'All.', 'fake news, hoax, infox, deepfake, désinformation, WhatsApp, réseaux sociaux, Bénin, Afrique de l\'Ouest', 14, 1, NULL, '2026-05-23 22:56:54', '2026-06-09 22:57:50', 1),
(5, '2184d3e3-8cdc-4e67-8bf3-360c84bfa477', 6, 'Journalistes vs Infox', 'journalistes-vs-infox', '<p>Depuis le Moyen-Âge jusqu\'aux réseaux sociaux, les fausses informations ont toujours existé. Mais jamais elles n\'ont circulé aussi vite qu\'aujourd\'hui. Dans ce cours, des journalistes de RFI et France 24 expliquent pourquoi lutter contre les infox fait désormais partie intégrante du métier de journaliste et de tout citoyen connecté.</p>', 'beginner', 'Français', 1, '[\"Avoir complété le Cours :  Généralités sur les fake news\"]', '[\"Comprendre l\'histoire et l\'évolution des fausses informations\", \"Mesurer l\'impact réel des infox sur nos sociétés\", \"Comprendre le rôle du journaliste face aux fake news\", \"Saisir pourquoi la vérification est devenue un enjeu citoyen majeur\"]', NULL, 1, 0, 0, 'courses/JebknidPbqSKhAc27dk3Ypz05zXtVpeh7B3HvPJq.jpg', 'Journalistes vs Infox - Lutter contre la désinformation | Vérif\'All', 'Comment les journalistes luttent-ils contre les fausses informations ? De la rumeur au Moyen-Âge aux fake news sur WhatsApp - cours vidéo CFI | Vérif\'All.', 'infox, fake news, journalisme, désinformation, réseaux sociaux, fact-checking, vérification, histoire', 21, 1, NULL, '2026-05-24 01:13:53', '2026-06-09 22:56:33', 1),
(6, 'a6c4e52a-8a5d-4e29-a594-561e9c6d6390', 6, 'Un siècle de fact-checking', 'un-siecle-de-fact-checking', '<p>Saviez-vous que le fact-checking existe depuis plus d\'un siècle ? Ce cours retrace l\'histoire de la vérification des faits, de ses origines dans les rédactions américaines des années 1920 jusqu\'à l\'explosion des sites spécialisés à l\'ère d\'Internet. Comprendre d\'où vient le fact-checking, c\'est mieux comprendre pourquoi il est plus indispensable que jamais aujourd\'hui.</p>', 'beginner', 'Français', 1, '[\"Avoir complété le cours Introduction au fact-checking, CLOM Vérif\'All\"]', '[\"Connaître les origines et l\'histoire du fact-checking\", \"Comprendre les révolutions successives qui ont façonné la pratique\", \"Identifier les grands acteurs mondiaux du fact-checking\", \"Analyser les critiques adressées au fact-checking et y répondre\"]', NULL, 1, 0, 0, 'courses/kO6N5pt4PFuFYl3RK84FPhszLZCQ0e2CUUs99Y4u.jpg', 'Histoire du fact-checking - Un siècle de vérification | Vérif\'All', 'Découvrez l\'histoire du fact-checking des années 1920 à aujourd\'hui. Des rédactions américaines aux 300+ sites spécialisés dans le monde, CLOM Vérif\'All', 'fact-checking, histoire, Time Magazine, Charte de Munich, déontologie, journalisme, vérification, Poynter, Africa Check', 21, 1, NULL, '2026-05-28 21:47:05', '2026-06-09 22:56:06', 1);

INSERT INTO `sections` (`id`, `course_id`, `title`, `order`, `created_by`, `updated_by`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 3, 'Bienvenue dans le CLOM', 1, NULL, NULL, NULL, '2026-05-23 22:17:39', '2026-05-25 20:47:08'),
(2, 4, 'Comprendre les fausses informations', 1, NULL, NULL, NULL, '2026-05-23 22:58:19', '2026-05-23 22:58:22'),
(3, 5, 'Journalistes vs Infox', 1, NULL, NULL, NULL, '2026-05-24 01:15:28', '2026-05-26 07:04:41'),
(4, 5, 'Quizz', 2, NULL, NULL, NULL, '2026-05-25 00:22:07', '2026-05-26 07:04:41'),
(5, 6, 'Un siècle de fact-checking', 1, NULL, NULL, NULL, '2026-05-28 21:50:26', '2026-05-28 21:51:29'),
(6, 6, 'Quiz ', 2, NULL, NULL, NULL, '2026-05-28 21:58:01', '2026-05-28 21:58:01');

INSERT INTO `lessons` (`id`, `section_id`, `title`, `slug`, `type`, `content`, `video_url`, `video_provider`, `duration_minutes`, `is_preview`, `order`, `xp_points`, `created_by`, `updated_by`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'Partagez nous vos connaissances et/ou les sujets qui vous intéressent!', 'presentation-du-parcours-et-des-objectifs', 'text', 'Dites nous ce que vous savez du fact-checking ou que vous souhaitez vous apprendre en venant ici ? \nDites nous les sujets qui vous intéressent!', NULL, 'youtube', 5, 1, 1, 5, 1, 1, NULL, '2026-05-23 22:18:55', '2026-05-28 22:41:01'),
(2, 2, 'Fake news, hoax, infox ! De quoi parle-t-on ?', 'fake-news-hoax-infox-de-quoi-parle-t-on', 'text', 'Exercices pratiques \n-  Prenez une info virale sur les réseaux sociaux. \n-  Demandez-vous : qui publie ? Pourquoi ? quelles preuves ? \n-  Cherchez si des médias fiables en parlent. \n-  Classez-la : info fiable, fake news, hoax ou clickbait. ', NULL, 'youtube', 10, 1, 1, 5, 1, 1, NULL, '2026-05-23 23:03:15', '2026-05-28 22:33:11'),
(3, 2, 'Anatomie d\'une fausse information', 'anatomie-dune-fausse-information', 'text', '<p><span style=\"color:rgb(0,0,0);background-color:transparent;\">Loin d\'être de simples erreurs ou des méprises informationnelles, les fake news sont le fruit de stratégies délibérées visant à saturer l\'espace public numérique à des fins politiques, économiques etc. </span></p><p><strong>Quand parler de fake news? </strong></p><p><span style=\"color:rgb(0,0,0);background-color:transparent;\">Reconnaître une infox impose de dépasser le stade de la simple lecture émotionnelle pour comprendre son ossature. Trois principaux critères conditionnent une fake news. </span></p><ol><li><span></span><strong>La préméditation, la construction consciente du faux </strong></li></ol><p><span style=\"color:rgb(0,0,0);background-color:transparent;\">Pour qu\'une information soit qualifiée de fake news (ou infox), elle doit résulter d\'un acte de préméditation où l\'émetteur a pleine conscience du caractère mensonger de l’information. Cette volonté de tromper se manifeste par une usurpation des codes journalistiques, usage de titres accrocheurs, de logos de médias reconnus ou de mises en page hyper-ressemblantes, pour lui donner une apparence crédible. Contrairement à la mésinformation, qui relève d\'une méprise involontaire ou d\'un manque de rigueur, l\'infox est une manipulation délibérée de la réalité. Cette distinction est capitale car là où le journaliste honnête peut commettre une bévue, le producteur d\'infox orchestre une désinformation structurée, transformant le récit en une arme de manipulation.</span></p><ol><li><span></span><strong>L\'intention malveillante, la volonté de nuire à autrui</strong></li></ol><p><span style=\"color:rgb(0,0,0);background-color:transparent;\">La seconde conditionnalité de l\'infox réside dans sa finalité malveillante. Le contenu n\'est jamais diffusé au hasard, il répond à une intention de déstabiliser un processus démocratique, de nuire à la réputation d\'une personnalité ou d’orchestrer un climat de peur. Partout, cette tactique est fréquemment observée en période électorale pour discréditer l\'opposition ou influencer l\'opinion publique. Parallèlement, une dimension mercantile puissante alimente ce phénomène via les pièges à clics (clickbait).</span></p><ol><li><span></span><strong>La viralité, la diffusion à grande échelle</strong></li></ol><p><span style=\"color:rgb(0,0,0);background-color:transparent;\">L’infox tire sa puissance de sa diffusion à grande échelle, propulsée par les algorithmes des réseaux sociaux. Grâce au paradigme du Web 2.0, chaque internaute passe du consommateur au « producteur » désormais capable de relayer une information à la vitesse de la lumière sans aucun filtre déontologique. Cette viralité est souvent amplifiée de manière artificielle par des bots, des usines à trolls ou des messages sponsorisés visant des publics bien identifiés. Des études soulignent d\'ailleurs qu\'une fausse nouvelle a 70 % de chances supplémentaires d\'être partagée qu\'une information réelle, car elle mise sur des ressorts émotionnels comme l\'indignation, la peur ou la surprise. Cette propagation exponentielle rend la correction ultérieure extrêmement difficile, l\'impact initial du mensonge persistant souvent dans la mémoire du public malgré les démentis.</span></p><p><em>Il existe trois conditions pour qu’un contenu soit qualifié de fake news. La première est la préméditation, la conscience de l’auteur que le contenu est faux et sa volonté à le diffuser en l’état. La deuxième est l’intention de l’auteur de nuire à autrui, à son intégrité. La troisième condition est la diffusion à grande échelle. </em></p><p><br /></p>', NULL, 'youtube', 5, 1, 2, 5, 1, 1, '2026-05-28 22:34:53', '2026-05-23 23:42:21', '2026-05-28 22:34:53'),
(4, 2, 'Typologie des fake news', 'typologie-des-fake-news-6a1241ca5aab8', 'text', NULL, NULL, 'youtube', 0, 0, 3, 10, 1, NULL, '2026-05-28 22:27:04', '2026-05-24 00:09:46', '2026-05-28 22:27:04'),
(5, 1, 'Le fact-checking, qu\'est-ce que c\'est ?', 'le-fact-checking-quest-ce-que-cest', 'video', '<p><em>Des journalistes internationaux de RFI et France 24 t\'expliquent ce qu\'est le fact-checking, pourquoi il est indispensable et comment il a évolué à l\'ère des réseaux sociaux.</em></p><p><em>Source : Conseils de Journalistes  -  CFI &amp; France Médias Monde</em></p>', 'https://youtu.be/owOeqzExFUQ?si=PmG-lOWZrYmyvQcm', 'youtube', 5, 1, 2, 5, 1, 1, NULL, '2026-05-24 00:53:11', '2026-05-24 00:56:19'),
(6, 3, 'Journalistes vs Infox - Vidéo CFI', 'journalistes-vs-infox-video-cfi', 'video', '<p>En une minute, près de 42 millions de messages sont échangés sur WhatsApp et 500 heures de vidéos publiées sur YouTube. Dans ce flot d\'informations, comment distinguer le vrai du faux ? Le fact-checking est la réponse. </p><p>Source : Conseils de Journalistes - un projet CFI et France Médias Monde</p>', 'https://youtu.be/qkvKmy6xXUY?si=0yFnysBm_5mGQxBQ', 'youtube', 5, 0, 1, 5, 1, 1, NULL, '2026-05-24 01:15:48', '2026-05-24 01:24:42'),
(7, 3, 'Quiz - Journalistes vs Infox', 'quiz-journalistes-vs-infox', 'quiz', '<p><strong>Question 1 :</strong> L\'eau salée présentée comme remède contre la Covid-19 est un exemple de :</p><ol><li><span></span>Une information vraie confirmée par des experts </li><li><span></span>Une infox qui s\'appuie sur une situation réelle pour tromper </li><li><span></span>Une blague inventée par des journalistes </li><li><span></span>Un canular sans conséquences</li></ol><p><strong>Question 2 : </strong>Selon la Charte de Munich, quel est le premier devoir d\'un journaliste ?</p><ol><li><span></span>Publier rapidement avant les autres médias</li><li><span></span>Respecter la vérité quelles qu\'en soient les conséquences</li><li><span></span>Protéger ses sources à tout prix</li><li><span></span>Obtenir le maximum de vues</li></ol><p><strong>Question 3 : </strong>En 1835, le tabloïd New York Sun publie un canular affirmant que :</p><ol><li><span></span>La Terre est plate</li><li><span></span>Des extraterrestres ont atterri à New York </li><li><span></span>Il existe une civilisation extraterrestre sur la Lune </li><li><span></span>Voltaire est encore vivant</li></ol><p><strong>Question 4 :</strong> Les algorithmes de Facebook, WhatsApp et YouTube privilégient la précision et la vérification de l\'information.</p><ol><li><span></span><strong>Vrai</strong></li><li><span></span><strong>Faux</strong></li></ol><p><strong>Question 5 : </strong>La crise diplomatique entre le Qatar et l\'Arabie Saoudite en 2017 a été provoquée par une fausse information.</p><ol><li><span></span><strong>Vrai</strong></li><li><span></span><strong>Faux</strong></li></ol><p><strong>Question 6 : </strong>Internet ne peut être utilisé que pour diffuser des infox, pas pour les vérifier.</p><ol><li><span></span><strong>Vrai</strong></li><li><span></span><strong>Faux</strong></li></ol><p><br /></p>', NULL, 'youtube', 6, 1, 2, 5, 1, 1, '2026-05-25 00:21:52', '2026-05-24 01:29:05', '2026-05-25 00:21:52'),
(8, 4, 'Quiz - Journalistes vs Infox', 'quiz-journalistes-vs-infox-6a139650a48bf', 'quiz', NULL, NULL, 'youtube', 0, 0, 1, 10, 1, NULL, NULL, '2026-05-25 00:22:40', '2026-05-25 00:22:40'),
(9, 5, 'Un siècle de fact-checking', 'un-siecle-de-fact-checking', 'video', '<p>Saviez-vous que le fact-checking existe depuis plus d\'un siècle ? Bien avant Internet et les réseaux sociaux, les journalistes avaient déjà développé des méthodes rigoureuses de vérification des faits.</p><p>Dans cette vidéo proposée par <strong>CFI Médias</strong>, découvrez l\'histoire fascinante du fact-checking de sa naissance dans les rédactions américaines des années 1920 jusqu\'à l\'explosion des plateformes spécialisées à l\'ère numérique.</p><p><em>Source : CFI Médias - Conseils de Journalistes</em></p>', 'https://youtu.be/yJbthP2Koxw?si=ScDXzzorzNltm_Sp', 'youtube', 4, 1, 1, 5, 1, 1, NULL, '2026-05-28 21:50:51', '2026-05-28 21:57:19'),
(10, 6, 'Quiz', 'quiz-6a18ba780ccd7', 'quiz', NULL, NULL, 'youtube', 0, 0, 1, 10, 1, NULL, NULL, '2026-05-28 21:58:16', '2026-05-28 21:58:16');

INSERT INTO `quizzes` (`id`, `lesson_id`, `title`, `description`, `time_limit`, `time_limit_minutes`, `passing_score`, `is_published`, `created_by`, `updated_by`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 8, 'Évaluation : Quiz - Journalistes vs Infox', NULL, 10, 0, 70, 0, 1, 1, NULL, '2026-05-25 01:26:03', '2026-05-28 18:59:05'),
(2, 10, 'Évaluation : Quiz', NULL, 10, 0, 70, 1, 1, 1, NULL, '2026-05-28 21:58:26', '2026-05-28 22:15:11');

INSERT INTO `lesson_resources` (`id`, `lesson_id`, `title`, `name`, `file_path`, `file_type`, `download_count`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 6, 'Journalistes vs Infox.pdf', 'Journalistes vs Infox.pdf', 'lessons/resources/Y0HGJCnP48Vmyk93EG5LkrFqK3klhr0nYtYzjTqZ.pdf', 'pdf', 1, 1, '2026-05-24 01:24:42', '2026-05-24 09:16:52'),
(4, 9, 'Un siècle de fact-checking.pdf', 'Un siècle de fact-checking.pdf', 'lessons/resources/y5xRKI7Y1ZMW6boOm22G7r1OWWfgv0UdfUZNWboM.pdf', 'pdf', 0, 1, '2026-05-28 21:57:20', '2026-05-28 21:57:20');

INSERT INTO `course_user` (`id`, `user_id`, `course_id`, `progress_percent`, `enrolled_at`, `completed_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 5, 3, 0, '2026-05-23 22:36:51', NULL, NULL, '2026-05-23 22:36:51', '2026-07-16 08:16:09'),
(2, 5, 5, 50, '2026-05-24 01:40:37', NULL, NULL, '2026-05-24 01:40:37', '2026-07-26 12:43:39'),
(3, 5, 4, 100, '2026-05-24 09:12:51', '2026-07-26 13:13:36', NULL, '2026-05-24 09:12:51', '2026-07-26 13:13:36'),
(4, 5, 6, 50, '2026-05-31 22:33:26', NULL, NULL, '2026-05-31 22:33:26', '2026-08-20 13:57:14'),
(5, 26, 5, 0, '2026-06-21 19:58:47', NULL, NULL, '2026-06-21 19:58:47', '2026-06-21 19:58:47'),
(6, 26, 3, 0, '2026-06-21 19:59:25', NULL, NULL, '2026-06-21 19:59:25', '2026-06-21 19:59:25'),
(7, 26, 4, 0, '2026-06-21 19:59:54', NULL, NULL, '2026-06-21 19:59:54', '2026-07-16 11:09:11'),
(8, 27, 4, 0, '2026-06-22 02:52:30', NULL, NULL, '2026-06-22 02:52:30', '2026-06-22 02:52:30'),
(9, 27, 3, 0, '2026-06-22 03:10:43', NULL, NULL, '2026-06-22 03:10:43', '2026-07-16 23:11:43'),
(10, 28, 6, 0, '2026-06-22 12:46:56', NULL, NULL, '2026-06-22 12:46:56', '2026-06-22 12:46:56'),
(11, 28, 3, 0, '2026-06-22 12:47:15', NULL, NULL, '2026-06-22 12:47:15', '2026-06-22 12:47:15'),
(12, 29, 6, 0, '2026-06-24 21:04:15', NULL, NULL, '2026-06-24 21:04:15', '2026-06-24 21:04:15'),
(13, 34, 5, 50, '2026-07-01 04:10:54', NULL, NULL, '2026-07-01 04:10:54', '2026-08-09 10:48:16'),
(14, 34, 3, 0, '2026-07-05 10:45:39', NULL, NULL, '2026-07-05 10:45:39', '2026-08-09 10:46:29'),
(15, 40, 3, 0, '2026-07-10 13:01:42', NULL, NULL, '2026-07-10 13:01:42', '2026-07-10 13:01:42'),
(16, 35, 3, 0, '2026-07-15 22:43:25', NULL, NULL, '2026-07-15 22:43:25', '2026-07-15 22:43:25'),
(17, 55, 3, 0, '2026-07-16 21:56:35', NULL, NULL, '2026-07-16 21:56:35', '2026-07-19 15:16:24'),
(18, 51, 6, 0, '2026-07-24 14:15:03', NULL, NULL, '2026-07-24 14:15:03', '2026-07-24 14:16:36'),
(19, 74, 6, 50, '2026-07-26 14:22:59', NULL, NULL, '2026-07-26 14:22:59', '2026-07-26 14:26:45'),
(20, 89, 6, 0, '2026-07-29 07:04:04', NULL, NULL, '2026-07-29 07:04:04', '2026-07-29 07:04:10'),
(21, 88, 6, 0, '2026-07-29 08:04:28', NULL, NULL, '2026-07-29 08:04:28', '2026-07-29 08:05:41'),
(22, 93, 4, 0, '2026-07-29 08:15:17', NULL, NULL, '2026-07-29 08:15:17', '2026-07-29 08:23:17'),
(23, 96, 4, 0, '2026-07-29 09:10:29', NULL, NULL, '2026-07-29 09:10:29', '2026-07-29 09:10:33'),
(24, 104, 3, 0, '2026-07-29 16:53:40', NULL, NULL, '2026-07-29 16:53:40', '2026-07-29 16:55:24'),
(25, 57, 4, 0, '2026-08-15 10:05:38', NULL, NULL, '2026-08-15 10:05:38', '2026-08-15 10:05:43'),
(26, 136, 3, 0, '2026-08-26 13:16:52', NULL, NULL, '2026-08-26 13:16:52', '2026-08-26 13:18:28'),
(27, 136, 4, 0, '2026-08-26 13:16:58', NULL, NULL, '2026-08-26 13:16:58', '2026-08-26 13:25:55'),
(28, 136, 5, 0, '2026-08-26 13:16:59', NULL, NULL, '2026-08-26 13:16:59', '2026-08-26 13:16:59'),
(29, 136, 6, 50, '2026-08-26 13:17:00', NULL, NULL, '2026-08-26 13:17:00', '2026-08-26 13:31:18');

INSERT INTO `lesson_user` (`id`, `user_id`, `lesson_id`, `progress_percent`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 5, 6, NULL, '2026-05-28 18:56:49', '2026-05-28 18:56:49', '2026-05-28 18:56:49'),
(2, 5, 2, NULL, '2026-05-28 22:22:57', '2026-05-28 22:22:57', '2026-05-28 22:22:57'),
(3, 5, 9, NULL, '2026-07-16 08:12:23', '2026-07-16 08:12:23', '2026-07-16 08:12:23'),
(4, 74, 9, '0', '2026-07-26 14:25:06', '2026-07-26 14:25:06', '2026-07-26 14:26:13'),
(5, 88, 9, '0', '2026-07-29 08:05:53', '2026-07-29 08:05:53', '2026-07-29 08:05:53'),
(6, 34, 6, '0', '2026-08-09 10:47:17', '2026-08-09 10:47:16', '2026-08-09 10:47:56'),
(7, 136, 9, '0', '2026-08-26 13:31:13', '2026-08-26 13:31:13', '2026-08-26 13:31:13');

INSERT INTO `quiz_attempts` (`id`, `user_id`, `quiz_id`, `score`, `correct_answers`, `answers_data`, `status`, `user_responses`, `started_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 5, 1, 0.00, 0, NULL, 'failed', NULL, '2026-05-27 17:01:28', '2026-05-27 18:30:51', '2026-05-27 17:01:28', '2026-05-27 18:30:51'),
(2, 5, 1, 0.00, 0, NULL, 'failed', NULL, '2026-05-27 19:46:50', '2026-05-28 19:03:02', '2026-05-27 19:46:50', '2026-05-28 19:03:02'),
(3, 5, 1, 50.00, 0, NULL, 'failed', NULL, '2026-05-31 17:47:19', '2026-05-31 17:47:25', '2026-05-31 17:47:19', '2026-05-31 17:47:25'),
(4, 5, 1, 50.00, 0, NULL, 'failed', NULL, '2026-05-31 17:47:32', '2026-05-31 17:47:46', '2026-05-31 17:47:32', '2026-05-31 17:47:46'),
(5, 5, 1, 50.00, 0, NULL, 'failed', NULL, '2026-05-31 17:47:55', '2026-05-31 17:48:15', '2026-05-31 17:47:55', '2026-05-31 17:48:15'),
(6, 5, 1, 50.00, 0, NULL, 'failed', NULL, '2026-05-31 17:48:28', '2026-05-31 17:48:40', '2026-05-31 17:48:28', '2026-05-31 17:48:40'),
(7, 5, 1, 50.00, 0, NULL, 'failed', NULL, '2026-05-31 17:49:50', '2026-05-31 17:49:59', '2026-05-31 17:49:50', '2026-05-31 17:49:59'),
(8, 5, 1, 50.00, 0, NULL, 'failed', NULL, '2026-05-31 17:50:04', '2026-05-31 17:50:42', '2026-05-31 17:50:04', '2026-05-31 17:50:42'),
(9, 5, 1, 50.00, 0, NULL, 'failed', NULL, '2026-05-31 17:50:47', '2026-05-31 17:51:02', '2026-05-31 17:50:47', '2026-05-31 17:51:02'),
(10, 5, 1, 0.00, 0, NULL, 'failed', NULL, '2026-05-31 17:51:05', '2026-05-31 17:51:20', '2026-05-31 17:51:05', '2026-05-31 17:51:20'),
(11, 5, 1, NULL, 0, NULL, 'in_progress', NULL, '2026-05-31 22:24:20', NULL, '2026-05-31 22:24:20', '2026-05-31 22:24:20'),
(12, 5, 2, NULL, 0, NULL, 'in_progress', NULL, '2026-06-10 20:43:35', NULL, '2026-06-10 20:43:35', '2026-06-10 20:43:35'),
(13, 51, 2, NULL, 0, NULL, 'in_progress', NULL, '2026-07-24 14:16:43', NULL, '2026-07-24 14:16:43', '2026-07-24 14:16:43');

-- Le forum : 5 catégories, 5 sujets, 2 sondages, 5 options — toutes réelles.

INSERT INTO `forum_categories` (`id`, `created_by`, `name`, `slug`, `color`, `created_at`, `updated_at`) VALUES
(1, 1, 'Fake news', 'fake-news', '#25D366', '2026-03-17 06:04:44', NULL),
(2, 1, 'Santé', 'sante', '#137fec', '2026-03-17 06:04:44', NULL),
(3, 1, 'Débats', 'debats', '#6f42c1', '2026-03-17 06:04:44', NULL),
(4, 1, 'Election', 'election', '#ff9f43', '2026-03-17 06:04:44', NULL),
(5, 1, 'Politique', 'politique', '#b90993', '2026-03-20 08:33:02', '2026-03-20 08:33:02');

INSERT INTO `forum_threads` (`id`, `user_id`, `category_id`, `title`, `content`, `type`, `is_pinned`, `is_locked`, `is_sensitive`, `sensitive_reason`, `status`, `created_at`, `updated_at`, `deleted_at`, `is_fake_news_report`, `source_link`, `media_path`, `has_poll`, `created_by`, `updated_by`) VALUES
(1, 5, 5, NULL, 'Dites moi', 'text', 0, 0, 0, NULL, 'public', '2026-05-23 18:31:28', '2026-05-23 18:31:28', NULL, 0, NULL, NULL, 1, 5, NULL),
(2, 5, 1, NULL, 'Hello', 'text', 0, 0, 0, NULL, 'public', '2026-05-23 20:45:18', '2026-05-23 20:45:18', NULL, 0, NULL, NULL, 1, 5, NULL),
(3, 26, 1, NULL, 'Hello...', 'text', 0, 0, 0, NULL, 'public', '2026-06-21 20:01:24', '2026-06-21 20:01:24', NULL, 0, NULL, NULL, 0, 26, NULL),
(4, 36, 1, NULL, 'Salut a tous', 'text', 0, 0, 0, NULL, 'public', '2026-07-09 13:15:30', '2026-07-09 13:15:30', NULL, 0, NULL, NULL, 0, 36, NULL),
(5, 128, 1, NULL, 'Hello everyone here ', 'text', 0, 0, 0, NULL, 'public', '2026-08-13 08:19:39', '2026-08-13 08:19:39', NULL, 0, NULL, NULL, 0, 128, NULL);

INSERT INTO `forum_polls` (`id`, `forum_thread_id`, `question`, `created_at`, `updated_at`) VALUES
(1, 1, 'C\'est vrai que le président sénégalais a révoquer son premier ministre ?', '2026-05-23 18:31:28', '2026-05-23 18:31:28'),
(2, 2, 'Diomaye Faye a révoqué Sounko', '2026-05-23 20:45:18', '2026-05-23 20:45:18');

INSERT INTO `forum_poll_options` (`id`, `forum_poll_id`, `option_text`, `votes_count`, `created_at`, `updated_at`) VALUES
(1, 1, 'Oui', 0, '2026-05-23 18:31:28', '2026-05-23 18:31:28'),
(2, 1, 'Non', 0, '2026-05-23 18:31:28', '2026-05-23 18:31:28'),
(3, 1, 'Je ne sais pas', 0, '2026-05-23 18:31:28', '2026-05-23 18:31:28'),
(4, 2, '1', 0, '2026-05-23 20:45:18', '2026-05-23 20:45:18'),
(5, 2, '1', 0, '2026-05-23 20:45:18', '2026-05-23 20:45:18');

-- Le Hub : 36 e-services, 2 sources et 3 actualités — toutes réelles.

INSERT INTO `e_services` (`id`, `name`, `url`, `category`, `is_verified`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Portail National des services publics du Bénin', 'https://service-public.bj/', 'Administration', 1, 1, '2026-05-24 13:35:15', '2026-05-24 13:35:15'),
(2, 'E-Passeport', 'https://epasseport.service-public.bj/', 'Administration', 1, 1, '2026-05-24 13:43:59', '2026-05-24 13:43:59'),
(3, 'Casier judiciaire (Extrait B3 du casier judiciaire)', 'https://service-public.bj/public/services/service/PS00373', 'Administration', 1, 1, '2026-05-24 13:48:35', '2026-05-24 13:48:35'),
(4, ' Attestation fiscale', 'https://service-public.bj/public/services/service/PS00689', 'Administration', 1, 1, '2026-05-24 13:50:01', '2026-05-24 13:50:01'),
(5, 'Registre de Commerce et de Crédit Mobilier (RCCM)', 'https://service-public.bj/public/services/service/PS00024', 'Administration', 1, 1, '2026-05-24 13:52:08', '2026-05-24 13:52:08'),
(6, 'Accès à la base de données des Entreprises du Bénin', 'https://service-public.bj/public/services/service/PS00414', 'Administration', 1, 1, '2026-05-24 13:55:36', '2026-05-24 13:55:36'),
(7, 'Attestation de Non Faillite', 'https://service-public.bj/public/services/service/PS00349', 'Justice', 1, 1, '2026-05-24 13:58:16', '2026-05-24 13:58:16'),
(8, 'Attestation de succès à l’examen de permis de conduire', 'https://service-public.bj/public/services/service/PS00880', 'Administration', 1, 1, '2026-05-24 14:00:08', '2026-05-24 14:00:08'),
(9, 'Authenticité du permis de conduire', 'https://service-public.bj/public/services/service/PS00854', 'Administration', 1, 1, '2026-05-24 14:03:35', '2026-05-24 14:03:35'),
(10, 'Authentification de diplôme du supérieur', 'https://service-public.bj/public/services/service/PS00254', 'Éducation', 1, 1, '2026-05-24 14:05:00', '2026-05-24 14:05:00'),
(11, 'Autorisation parentale [Diaspora]', 'https://service-public.bj/public/services/service/PS01361', 'Administration', 1, 1, '2026-05-24 14:09:26', '2026-05-24 14:09:26'),
(12, 'Carte de résident (temporaire-ordinaire-privilégié)', 'https://service-public.bj/public/services/service/PS00409', 'Administration', 1, 1, '2026-05-24 14:13:26', '2026-05-24 14:13:26'),
(13, 'Certification de diplôme obtenu au Bénin', 'https://service-public.bj/public/services/service/PS00269', 'Éducation', 1, 1, '2026-05-24 14:15:36', '2026-05-24 14:15:36'),
(14, 'E-Résultats (Examens & Concours)', 'https://eresultats.bj/', 'Éducation', 1, 1, '2026-05-24 14:21:00', '2026-05-24 14:21:00'),
(15, 'Création d\'une entreprise au Bénin', 'https://service-public.bj/public/services/service/PS00062', 'Administration', 1, 1, '2026-05-24 14:22:23', '2026-05-24 14:22:23'),
(16, 'Demande d’autorisation de sortie de mineurs (Afrique et à l’étranger)', 'https://service-public.bj/public/services/service/PS01315', 'Administration', 1, 1, '2026-05-24 14:25:50', '2026-05-24 14:25:50'),
(17, 'Déclaration d\'impôts', 'https://service-public.bj/public/services/service/PS00030', 'Administration', 1, 1, '2026-05-24 14:28:00', '2026-05-24 14:28:00'),
(18, 'Déclaration de perte de document [Diaspora]', 'https://service-public.bj/public/services/service/PS01366', 'Administration', 1, 1, '2026-05-24 14:29:15', '2026-05-24 14:29:15'),
(19, 'Déclaration d’existence au Registre des Associations et Fondations', 'https://service-public.bj/public/services/service/PS01508', 'Administration', 1, 1, '2026-05-24 14:30:40', '2026-05-24 14:30:40'),
(20, 'Acte de naissance sécurisé', 'https://service-public.bj/public/services/service/PS00393', 'Administration', 1, 1, '2026-05-24 14:31:50', '2026-05-24 14:31:50'),
(21, 'Certificat de coutumes et de célibat', 'https://service-public.bj/public/services/service/PS00842', 'Administration', 1, 1, '2026-05-24 14:33:11', '2026-05-24 14:33:11'),
(22, 'Carte d\'identité nationale biométrique', 'https://service-public.bj/public/services/service/PS00406', 'Administration', 1, 1, '2026-05-24 14:36:27', '2026-05-24 14:36:27'),
(23, 'Attestation de régularité fiscale foncière', 'https://service-public.bj/public/services/service/PS01512', 'Administration', 1, 1, '2026-05-24 14:38:09', '2026-05-24 14:38:09'),
(24, 'Numéros ISBN, ISSN et de dépôt légal', 'https://service-public.bj/public/services/service/PS00938', 'Éducation', 1, 1, '2026-05-24 14:43:36', '2026-05-24 14:43:36'),
(25, 'E-Visa', 'https://service-public.bj/public/services/service/PS00005', 'Administration', 1, 1, '2026-05-24 14:44:36', '2026-05-24 14:44:36'),
(26, 'Certificat d\'Identification Personnelle (CIP)', 'https://service-public.bj/public/services/service/PS00392', 'Administration', 1, 1, '2026-05-24 14:45:23', '2026-05-24 14:45:23'),
(27, 'Duplicata du récépissé RAVIP', 'https://service-public.bj/public/services/service/PS00841', 'Administration', 1, 1, '2026-05-24 14:49:41', '2026-05-24 14:49:41'),
(28, 'Echange du permis de conduire', 'https://service-public.bj/public/services/service/PS00855', 'Administration', 1, 1, '2026-05-24 14:51:36', '2026-05-24 14:51:36'),
(29, 'Immatriculation consulaire [IDiaspora]', 'https://service-public.bj/public/services/service/PS01351', 'Administration', 1, 1, '2026-05-24 14:56:15', '2026-05-24 14:56:15'),
(30, 'Laissez-Passer [Diaspora]', 'https://service-public.bj/public/services/service/PS01354', 'Administration', 1, 1, '2026-05-24 14:57:20', '2026-05-24 14:57:20'),
(31, 'Paiement d\'une facture d\'eau SONEB', 'https://service-public.bj/public/services/service/PS00380', 'Administration', 1, 1, '2026-05-24 14:58:45', '2026-05-24 14:58:45'),
(32, 'Paiement facture d\'électricité SBEE', 'https://service-public.bj/public/services/service/PS00140', 'Administration', 1, 1, '2026-05-24 14:59:48', '2026-05-24 14:59:48'),
(33, 'Permis international', 'https://service-public.bj/public/services/service/PS00856', 'Administration', 1, 1, '2026-05-24 15:00:25', '2026-05-24 15:00:25'),
(34, 'Prise de rendez-vous au CNHU-HKM', 'https://service-public.bj/public/services/service/PS00914', 'Santé', 1, 1, '2026-05-24 15:02:08', '2026-05-24 15:02:08'),
(35, ' Procuration [Diaspora]', 'https://service-public.bj/public/services/service/PS01360', 'Administration', 1, 1, '2026-05-24 15:02:50', '2026-05-24 15:02:50'),
(36, 'Visa résident (court séjour / long séjour)', 'https://service-public.bj/public/services/service/PS00407', 'Administration', 1, 1, '2026-05-24 15:07:47', '2026-05-24 15:07:47');

INSERT INTO `news_sources` (`id`, `name`, `rss_url`, `website_url`, `logo_path`, `is_official`, `created_at`, `updated_at`, `created_by`) VALUES
(11, 'presidence.bj', 'https://presidence.bj/discours-investiture/', 'https://presidence.bj/discours-investiture/', NULL, 1, '2026-05-24 15:34:49', '2026-05-24 15:34:49', 1),
(12, 'SRTB', 'https://srtb.bj/sante-le-benin-se-dote-dune-agence-de-lutte-contre-la-malaria-et-les-moustiques/', 'https://srtb.bj/sante-le-benin-se-dote-dune-agence-de-lutte-contre-la-malaria-et-les-moustiques/', NULL, 1, '2026-05-24 15:39:20', '2026-05-24 16:01:50', 1);

INSERT INTO `news_articles` (`id`, `news_source_id`, `title`, `slug`, `summary`, `url`, `image_url`, `published_at`, `created_at`, `updated_at`, `summary_ai`) VALUES
(5, 11, 'Discours d\'investiture du Président de la République, Monsieur Romuald WADAGNI', 'discours-dinvestiture-du-president-de-la-republique-monsieur-romuald-wadagni-w3JJg', 'Discours du nouveau Président de la République du Bénin lors de la cérémonie d\'investiture', 'https://presidence.bj/discours-investiture/', NULL, '2026-05-24 15:34:49', '2026-05-24 15:34:49', '2026-05-24 15:34:49', '\"{\\\"verdict\\\":\\\"En attente\\\",\\\"score\\\":0}\"'),
(6, 12, 'Bénin : le Plan national de développement 2026-2035 approuvé', 'benin-le-plan-national-de-developpement-2026-2035-approuve-czpLW', 'Le gouvernement béninois a approuvé, mercredi 13 mai 2026 en Conseil des ministres, le Plan national de développement (PND) 2026-2035. Ce document stratégique marque le démarrage de la mise en œuvre de la vision nationale de développement à l’horizon 2060.', 'https://srtb.bj/benin-le-plan-national-de-developpement-2026-2035-approuve/', NULL, '2026-05-24 15:39:20', '2026-05-24 15:39:20', '2026-05-24 15:39:20', '\"{\\\"verdict\\\":\\\"En attente\\\",\\\"score\\\":0}\"'),
(7, 12, 'Santé : le Bénin se dote d’une Agence de lutte contre la malaria et les moustiques', 'sante-le-benin-se-dote-dune-agence-de-lutte-contre-la-malaria-et-les-moustiques-vqgBS', 'Le Bénin renforce son dispositif de lutte contre le paludisme. En Conseil des ministres, le mercredi 18 février 2026, le gouvernement a approuvé la création d’une Agence de lutte contre la malaria et les moustiques.', 'https://srtb.bj/sante-le-benin-se-dote-dune-agence-de-lutte-contre-la-malaria-et-les-moustiques/', NULL, '2026-05-24 16:01:50', '2026-05-24 16:01:50', '2026-05-24 16:01:50', '\"{\\\"verdict\\\":\\\"En attente\\\",\\\"score\\\":0}\"');

