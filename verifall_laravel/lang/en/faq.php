<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page: FAQ
|--------------------------------------------------------------------------
|
| Every `aria-controls="collapse-N"` is reproduced UNCHANGED. Those values tie
| a button to the panel it opens: renumbering or dropping one would break the
| accordion for keyboard and screen-reader users, and nothing on screen would
| show it.
|
| One French question is grammatically incomplete in the source ("Pourquoi
| dois-je créer un ?", missing its noun). The English mirrors the intent
| ("create an account") rather than reproducing the mistake, because copying a
| typo across languages spreads it instead of surfacing it. The French is
| worth fixing.
|
*/

return [
    // Le gabarit d'origine avait laisse ce placeholder en anglais dans la page
    // francaise. Il est desormais traduit des deux cotes.
    'placeholder_recherche' => 'Enter your keyword…',
    'contactez_nous' => 'Contact us',

    // The question carries its own key; the answer reuses the same slug with a
    // "reponse_" prefix, so the pair stays obvious in both files.
    'reponse_qu_est_ce_que_verif_all' => 'Vérif\'All is the first technology platform in Benin dedicated to fighting disinformation. It combines artificial intelligence with human expertise to assess how truthful the information circulating on social media really is.',
    'reponse_pourquoi_dois_je_creer_un' => 'An account lets you track your progress through the tutorials, earn official certificates and receive 10 free AI analyses.',
    'reponse_est_ce_que_l_utilisation_est_gratuite' => 'Yes. Access to the platform and basic checks are entirely free for every citizen. Our mission serves the public interest.',
    'reponse_verif_all_est_il_un_organe_du' => 'No, Vérif\'All is an independent platform. We do work with official sources to confirm or deny institutional statements.',
    'reponse_comment_verifier_un_lien_recu_sur_whatsapp' => 'Copy the suspicious link and paste it into our checking tool on the home page or in your dashboard. Our URL scanner will tell you straight away whether it is phishing or a scam.',
    'reponse_puis_je_verifier_une_image_ou_une' => 'Absolutely. You can upload an image. The system runs a reverse search to see whether the image has been recycled or manipulated, and examines its technical metadata.',
    'reponse_que_signifie_le_score_de_confiance' => 'The score is calculated by our algorithms: a high score (80-100%) points to reliable information, while a low score (0-20%) flags fake news or a dangerous site.',
    'reponse_comment_l_ia_detecte_t_elle_les' => 'We use several engines (GPTZero, Google Vision, and others) that analyse the structure of the language, where the web servers are hosted and how far back the images go online.',
    'reponse_mes_donnees_signalees_sont_elles_privees' => 'Yes. What you submit is analysed anonymously. We do not share your identity when a public debunk is published.',
    'reponse_c_est_quoi_un_deepfake' => 'A deepfake is a video or an audio clip created by AI to imitate a real person convincingly. Vérif\'All has dedicated tools for detecting this kind of vocal and visual manipulation.',
    'reponse_qui_sont_les_mentors_de_la_plateforme' => 'The mentors are professional journalists and cybersecurity experts trained in digital investigation techniques (OSINT).',
    'reponse_combien_de_temps_prend_une_verification_humaine' => 'When the AI cannot settle the question on its own, a mentor takes over. Turnaround averages 2 to 6 hours depending on how complex the subject is.',
    'reponse_comment_obtenir_mon_certificat_de_fact_checker' => 'Follow our free tutorials in the learning section and pass the quizzes with a score of at least 70%. Your PDF certificate is then generated automatically.',
    'reponse_mes_donnees_personnelles_sont_elles_protegees' => 'Absolutely. Vérif\'All complies with Beninese data protection law. Your reports are handled confidentially.',
    'reponse_que_se_passe_t_il_si_j' => 'No need to worry. You can still submit reports, but they will be handled by our mentors by hand rather than analysed instantly by AI.',
    'reponse_pourquoi_verifier_mon_compte' => 'Verification confirms that you are a real citizen and releases your 10 free fact-checking units.',
    'reponse_qu_est_ce_qu_un_fact_checker' => 'Someone who has completed the tutorials and earned their official Vérif\'All certificate.',

    'questions_frequentes' => 'Frequently Asked Questions',

    'tout_ce_que_vous_devez_savoir_sur' => 'Everything you need to know about Vérif’All and fact-checking.',

    'rechercher_dans_la_faq' => 'Search the FAQ',

    'qu_est_ce_que_verif_all' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-0"> What is Vérif’All?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'pourquoi_dois_je_creer_un' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-1"> Why do I need to create an account?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'est_ce_que_l_utilisation_est_gratuite' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-2"> Is it free to use?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'verif_all_est_il_un_organe_du' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-3"> Is Vérif’All a government body?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'comment_verifier_un_lien_recu_sur_whatsapp' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-4"> How do I check a link received on WhatsApp?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'puis_je_verifier_une_image_ou_une' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-5"> Can I check an image or a photograph?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'que_signifie_le_score_de_confiance' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-6"> What does the confidence score mean?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'comment_l_ia_detecte_t_elle_les' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-7"> How does AI detect fake news?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'mes_donnees_signalees_sont_elles_privees' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-8"> Is the content I report kept private?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'c_est_quoi_un_deepfake' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-9"> What is a “deepfake”?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'qui_sont_les_mentors_de_la_plateforme' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-10"> Who are the platform’s mentors?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'combien_de_temps_prend_une_verification_humaine' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-11"> How long does a human check take?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'comment_obtenir_mon_certificat_de_fact_checker' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-12"> How do I get my fact-checker certificate?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'mes_donnees_personnelles_sont_elles_protegees' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-13"> Is my personal data protected?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'que_se_passe_t_il_si_j' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-14"> What happens if I use up my 10 monthly units?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'pourquoi_verifier_mon_compte' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-15"> Why should I verify my account?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'qu_est_ce_qu_un_fact_checker' => '<button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapse-16"> What is a certified fact-checker?<span class="acc-icon" aria-hidden="true">▾</span> </button>',

    'aucune_question_ne_correspond_a_votre_recherche' => 'No question matches your search.',

    'vous_avez_encore_une_question' => 'Still have a question?',

    'nous_serions_ravis_de_vous_aider_contactez' => 'We would be glad to help. Get in touch and we will do our best to answer.',
];
