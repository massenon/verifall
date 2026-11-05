<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page: Legal notice
|--------------------------------------------------------------------------
|
| ── THE FOUR VERDICTS ARE NAMED HERE, SO THEY MUST BE EXACT ──────────────
|
| TRUE, FALSE, MISLEADING and UNVERIFIABLE. These are the same four words the
| platform publishes on every fact-check, in the ClaimReview markup and on the
| shareable cards. A fifth word appearing here, or a different wording for one
| of them, would commit the publisher to something it does nowhere else.
|
| UNVERIFIABLE is defined as "the available evidence does not allow a
| conclusion", never as "half true". That sentence exists precisely to close
| that misreading, and the English keeps it closed.
|
| ── THE LAW IS CITED, NOT TRANSLATED ─────────────────────────────────────
|
| The Beninese statute keeps its French title and number. A translated name
| for a law is not the law: anyone checking the reference needs the string
| that appears in the official text.
|
*/

return [
    'derniere_mise_a_jour' => 'Last updated: :date',

    // "Publisher" block. The values (company name, address, email) come from
    // the settings; only the labels are translated.
    'le_site_web_et_l_application_sont_edites_par' => 'The verifall.com website and the Vérif\'All mobile application (hereafter "the Platform") are published by:',
    'organisation_enregistree_en_republique_du_benin' => 'Organisation registered in the Republic of Benin',
    'adresse' => 'Address:',
    'courriel' => 'Email:',
    'contact' => 'Contact:',

    // Labels passed to the <x-mention-legale> component: they appear when the
    // matching setting has not been filled in yet.
    'libelle_raison_sociale' => 'Registered name',
    'libelle_adresse_du_siege' => 'Registered address',
    'libelle_directeur_de_la_publication' => 'Publication director',
    'libelle_nom_de_l_hebergeur' => 'Host name',

    'le_directeur_de_la_publication_est' => 'The publication director is',
    'en_sa_qualite_de_responsable_du_contenu' => ', as the person responsible for the content published on the Platform.',
    'la_plateforme_est_hebergee_par' => 'The Platform is hosted by:',

    // Sentences broken by an internal link: one fragment either side.
    'une_erreur_de_verdict_se_corrige' => 'A mistaken verdict is corrected, not erased. Every change to a published verdict is recorded, with its date and its reason, in the',
    'journal_public_des_corrections' => 'public corrections log',
    'consultable_sans_compte' => ', which anyone can read without an account. The fact-check page concerned also carries a note of its correction.',
    'pour_signaler_une_erreur_dans_une_verification' => 'To report an error in a verification, write to :courriel giving the address of the page and the point being disputed. The method used is set out on the',
    'methodologie' => 'methodology page',
    'tout_contenu_publie_par_un_utilisateur' => 'Any content published by a user (forum post, reply, community note) can be reported to moderation from within the Platform, or by email to :courriel. A report states the address of the content and the grounds invoked.',
    'le_traitement_des_donnees_personnelles_est_decrit' => 'The processing of personal data is described in the',
    'politique_de_confidentialite' => 'privacy policy',
    'l_usage_des_traceurs_est_decrit_dans' => '. The use of trackers is described in the',
    'politique_des_cookies' => 'cookie policy',
    'les_sources_de_financement_sont_publiees_sur' => 'The Platform\'s sources of funding are published on the',
    'financement' => 'funding page',
    'aucun_financeur_n_exerce_de_controle_editorial' => '. No funder exercises editorial control over the verifications we publish.',
    'l_utilisation_de_la_plateforme_est_soumise' => 'Use of the Platform is governed by Beninese law. In the event of a dispute, an amicable settlement is sought first: the complaint is addressed to :courriel and receives a reasoned reply.',

    'creer_mon_compte' => 'Create my account',

    'mentions_legales' => 'Legal Notice',

    'conformement_aux_dispositions_de_la_loi_n2017' => "In accordance with the provisions of law no. 2017-20 of 20 April 2018 establishing the Digital Code of the Republic of Benin (Code du Numérique), users of the Vérif'All website and application are informed of the identity of the parties involved in its creation and upkeep.",

    '1_edition_du_site_et_de_l' => '1. Publisher of the website and application',
    '2_directeur_de_la_publication' => '2. Director of publication',
    '3_hebergement' => '3. Hosting',
    '4_propriete_intellectuelle' => '4. Intellectual property',

    'l_ensemble_des_elements_de_la_plateforme' => 'All elements of the Platform (text, logos, images, icons, applications, scripts) are the exclusive property of the publisher, with the exception of content submitted by users and of extracts quoted as part of a check. Any reproduction, distribution, alteration or use of these elements, even in part, without prior written permission, is prohibited and may constitute an infringement offence.',

    'les_rapports_de_verification_peuvent_etre_cites' => "Verification reports may be quoted and shared freely, provided the quotation is faithful, credits Vérif'All and links back to the original page. A quotation that distorts a verdict, or isolates part of one, falls outside this permission.",

    '5_nature_et_portee_des_verifications' => '5. Nature and scope of the checks',

    'verif_all_publie_quatre_verdicts_et_quatre' => "Vérif'All publishes four verdicts, and four only: TRUE, FALSE, MISLEADING and UNVERIFIABLE. The verdict UNVERIFIABLE means that the available evidence does not allow a conclusion, not that the claim would be half true.",

    'les_rapports_de_verification_sont_fournis_a' => 'Verification reports are provided for information and education. They account for the material available at the date of publication and do not engage the liability of the publisher for decisions taken on their basis. Users remain responsible for how they use the information published.',

    'la_plateforme_contient_des_liens_vers_des' => 'The Platform contains links to third-party sites, in particular to the sources cited. The publisher exercises no control over those sites and is not answerable for their content.',

    '6_politique_de_correction' => '6. Corrections policy',
    '7_signalement_d_un_contenu_illicite' => '7. Reporting unlawful content',

    'chaque_signalement_est_examine_et_fait_l' => 'Every report is examined and results in a decision, upheld or dismissed. The record of that decision is kept: it is what makes it possible to answer, later, why a piece of content was removed or left in place.',

    '8_donnees_personnelles' => '8. Personal data',
    '9_financement_et_independance' => '9. Funding and independence',
    '10_droit_applicable_et_reglement_des_litiges' => '10. Governing law and dispute resolution',

    'a_defaut_d_accord_competence_exclusive_est' => 'Failing agreement, exclusive jurisdiction is granted to the courts of Cotonou.',

    '11_accessibilite_numerique' => '11. Digital accessibility',
    'verifall_s_engage_a_rendre_son_site_accessible' => 'Vérif\'All is committed to making its website and mobile application accessible, in accordance with WCAG 2.1 Level AA guidelines.',
    'etat_de_conformite_actuel' => 'Current conformance status: partially conformant. Improvement work is underway, in particular on text alternatives for images, colour contrasts in certain modules, and keyboard navigation in interactive forms.',
    'pour_signaler_un_probleme_accessibilite' => 'To report an accessibility problem or request an accessible version of a piece of content, write to',
    'accusé_reception_accessibilite' => '. We acknowledge receipt within 5 working days and handle your request within 30 days.',
    'voie_de_recours_accessibilite' => 'Escalation: if you do not receive a response within that period, you may contact ARCEP Bénin or the Ombudsman of your country of residence.',

    'rejoignez_un_espace_d_information_transparent_et' => 'Join an information space that is transparent and secure.',

    'maintenant_que_vous_connaissez_nos_engagements_passez' => 'Now that you know what we commit to, take the next step.<br>Create your free account to use all our tools and take an active part in the fight against disinformation.',
];
