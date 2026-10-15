<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page faq
|--------------------------------------------------------------------------
|
| ENGENDRE depuis la vue, puis relu. La copie du prototype reste ainsi
| identique au caractere pres.
|
| Les valeurs qui portent du balisage en ligne sont rendues avec la syntaxe non
| echappee. Ce n'est PAS une entorse a la regle de securite : ces chaines sont
| ecrites par l'equipe, jamais par un visiteur. La regle vise les donnees
| d'utilisateur, et elle reste entiere.
|
*/

return [
    'placeholder_recherche' => 'Entrez votre mot-clé…',
    'contactez_nous' => 'Contactez-nous',

    // Les reponses de l'accordeon. La question porte deja sa propre cle ;
    // la reponse reprend le meme slug, prefixe par « reponse_ ».
    'reponse_qu_est_ce_que_verif_all' => 'Vérif\'All est la première plateforme technologique au Bénin dédiée à la lutte contre la désinformation. Elle combine l\'Intelligence Artificielle et l\'expertise humaine pour analyser la véracité des informations circulant sur les réseaux sociaux.',
    'reponse_pourquoi_dois_je_creer_un' => 'Le compte vous permet de suivre votre progression dans les tutoriels, d\'obtenir des certificats officiels et de bénéficier de 10 analyses IA gratuites.',
    'reponse_est_ce_que_l_utilisation_est_gratuite' => 'Oui, l\'accès à la plateforme et les vérifications de base sont entièrement gratuits pour tous les citoyens. Notre mission est d\'intérêt public.',
    'reponse_verif_all_est_il_un_organe_du' => 'Non, Vérif\'All est une plateforme indépendante. Cependant, nous collaborons avec des sources officielles pour confirmer ou infirmer les communiqués institutionnels.',
    'reponse_comment_verifier_un_lien_recu_sur_whatsapp' => 'Il suffit de copier le lien suspect et de le coller dans notre outil de vérification sur la page d\'accueil ou dans votre dashboard. Notre scanner d\'URL analysera instantanément s\'il s\'agit de phishing ou d\'une arnaque.',
    'reponse_puis_je_verifier_une_image_ou_une' => 'Absolument. Vous pouvez uploader une image. Le système effectuera une recherche inversée pour voir si l\'image a été recyclée ou manipulée, et analysera ses métadonnées techniques.',
    'reponse_que_signifie_le_score_de_confiance' => 'Le score est calculé par nos algorithmes : un score élevé (80-100%) indique une information fiable, tandis qu\'un score faible (0-20%) signale une fake news ou un site dangereux.',
    'reponse_comment_l_ia_detecte_t_elle_les' => 'Nous utilisons plusieurs moteurs (GPTZero, Google Vision, etc.) qui analysent la structure du langage, la provenance des serveurs web et l\'antériorité des images sur internet.',
    'reponse_mes_donnees_signalees_sont_elles_privees' => 'Oui. Les contenus que vous soumettez sont analysés anonymement. Nous ne partageons pas votre identité lors de la publication des démentis publics.',
    'reponse_c_est_quoi_un_deepfake' => 'Un deepfake est une vidéo ou un audio créé par l\'IA pour imiter parfaitement une personne réelle. Vérif\'All possède des outils spécifiques pour détecter ces manipulations vocales et visuelles.',
    'reponse_qui_sont_les_mentors_de_la_plateforme' => 'Les mentors sont des journalistes professionnels et des experts en cybersécurité formés aux techniques d\'investigation numérique (OSINT).',
    'reponse_combien_de_temps_prend_une_verification_humaine' => 'Si l\'IA ne peut pas trancher seule, un mentor prend le relais. Le délai moyen de traitement est de 2 à 6 heures selon la complexité du sujet.',
    'reponse_comment_obtenir_mon_certificat_de_fact_checker' => 'Il vous suffit de suivre nos tutoriels gratuits dans la section LMS et de réussir les quiz avec un score minimum de 70%. Votre certificat PDF sera généré automatiquement.',
    'reponse_mes_donnees_personnelles_sont_elles_protegees' => 'Absolument. Vérif\'All respecte les lois béninoises sur la protection des données. Vos signalements sont traités de manière confidentielle.',
    'reponse_que_se_passe_t_il_si_j' => 'Pas d\'inquiétude ! Vous pouvez toujours soumettre des signalements, mais ils seront traités manuellement par nos mentors au lieu de l\'analyse instantanée par IA.',
    'reponse_pourquoi_verifier_mon_compte' => 'La vérification permet de s\'assurer que vous êtes un citoyen réel et de vous attribuer vos 10 unités de fact-checking gratuites.',
    'reponse_qu_est_ce_qu_un_fact_checker' => 'C\'est un utilisateur qui a complété ses tutoriels et obtenu son certificat officiel sur Vérif\'All.',

    'questions_frequentes' => 'Questions Fréquentes',
    'tout_ce_que_vous_devez_savoir_sur' => 'Tout ce que vous devez savoir sur Vérif\'All et le Fact-checking.',
    'rechercher_dans_la_faq' => 'Rechercher dans la FAQ',
    'qu_est_ce_que_verif_all' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-0"> Qu\'est-ce que Vérif\'All ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'pourquoi_dois_je_creer_un' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-1"> Pourquoi dois-je créer un ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'est_ce_que_l_utilisation_est_gratuite' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-2"> Est-ce que l\'utilisation est gratuite ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'verif_all_est_il_un_organe_du' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-3"> Vérif\'All est-il un organe du gouvernement ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'comment_verifier_un_lien_recu_sur_whatsapp' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-4"> Comment vérifier un lien reçu sur WhatsApp ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'puis_je_verifier_une_image_ou_une' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-5"> Puis-je vérifier une image ou une photo ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'que_signifie_le_score_de_confiance' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-6"> Que signifie le score de confiance ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'comment_l_ia_detecte_t_elle_les' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-7"> Comment l\'IA détecte-t-elle les Fake News ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'mes_donnees_signalees_sont_elles_privees' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-8"> Mes données signalées sont-elles privées ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'c_est_quoi_un_deepfake' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-9"> C\'est quoi un \'Deepfake\' ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'qui_sont_les_mentors_de_la_plateforme' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-10"> Qui sont les mentors de la plateforme ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'combien_de_temps_prend_une_verification_humaine' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-11"> Combien de temps prend une vérification humaine ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'comment_obtenir_mon_certificat_de_fact_checker' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-12"> Comment obtenir mon certificat de Fact-checker ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'mes_donnees_personnelles_sont_elles_protegees' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-13"> Mes données personnelles sont-elles protégées ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'que_se_passe_t_il_si_j' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-14"> Que se passe-t-il si j\'épuise mes 10 unités mensuelles ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'pourquoi_verifier_mon_compte' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-15"> Pourquoi vérifier mon compte ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'qu_est_ce_qu_un_fact_checker' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-16"> Qu\'est-ce qu\'un Fact-checker certifié ?<span class="acc-icon" aria-hidden="true">▾</span> </button>',
    'aucune_question_ne_correspond_a_votre_recherche' => 'Aucune question ne correspond à votre recherche.',
    'vous_avez_encore_une_question' => 'Vous avez encore une question ?',
    'nous_serions_ravis_de_vous_aider_contactez' => 'Nous serions ravis de vous aider ! Contactez-nous et nous ferons de notre mieux pour vous répondre.',
];
