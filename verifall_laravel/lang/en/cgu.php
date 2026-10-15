<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Conditions générales d'utilisation
|--------------------------------------------------------------------------
|
| ÉCRITES POUR CE PRODUIT, pas recopiées d'un modèle. Chaque clause se
| rattache à un comportement réel de la plateforme : les quatre verdicts, la
| nature des unités, le délai de rétractation de la suppression de compte, la
| révocation d'un certificat, la modération du forum.
|
| Le texte est structuré en sections plutôt qu'en clés plates : un document
| juridique doit se relire d'un bloc.
|
| ── DEUX AVERTISSEMENTS ─────────────────────────────────────────────────────
|
| 1. CE TEXTE N'A PAS ÉTÉ RELU PAR UN JURISTE. Il est rédigé de façon
|    conservatrice — favorable au membre partout où il aurait fallu deviner —
|    précisément parce qu'une clause trop large est nulle et se retourne
|    contre celui qui l'a écrite. Une relecture avant mise en production
|    réelle reste nécessaire ; voir docs/JOURNAL_DES_LOTS.md.
|
| 2. LA VERSION FRANÇAISE FAIT FOI, et le document le dit lui-même. La
|    traduction anglaise existe parce que le site est bilingue et que la
|    parité des clés est vérifiée par un test — elle n'a pas vocation à
|    fonder une interprétation.
|
| Les valeurs entre `:deux-points` sont substituées à l'affichage : identité
| de l'éditeur (réglages), âge minimum (réglage), délai de rétractation
| (constante du service de suppression). Aucun crochet n'est publié.
*/
return [
    'conditions_generales' => 'Terms of Use',

    'en_vigueur_depuis' => 'In force since :date',

    'version_fait_foi' => 'These terms are written in French. Where a translation differs, the French version prevails.',

    'sommaire' => 'Contents',

    'sections' => [
        [
            'titre' => '1. Purpose and acceptance',
            'paragraphes' => [
                'These terms govern access to and use of the Vérif\'All platform, published by the entity named above, hereafter "the platform". They apply to anyone who creates an account, hereafter "the member", and to anyone browsing the site without one.',
                'Creating an account requires express acceptance of these terms. That acceptance is timestamped and kept: a member may request a copy at any time.',
                'Reading a public verification, submitting content without an account and using the public certificate checker require no account, but remain subject to these terms.',
            ],
        ],
        [
            'titre' => '2. What the platform does',
            'paragraphes' => [
                'Vérif\'All is an information verification and media literacy service. It offers: verification of content submitted by a member, an academy of courses and examinations, a news and opportunities hub, and a citizen forum.',
                'A verification produces a report carrying a verdict, a reliability score, the sources found and the signals detected. The four possible verdicts are: TRUE, FALSE, MISLEADING and UNVERIFIABLE.',
            ],
        ],
        [
            'titre' => '3. Scope and limits of verifications',
            'paragraphes' => [
                'A verification report is an ANALYSIS, produced on a given date, from the sources available at that time. It is neither a court decision, nor a judicial expert opinion, nor an official attestation.',
                'The platform commits to the MEANS employed — plurality of sources, traceability of the evidence retained, publication of the method — not to an outcome. A finding may be revised if new evidence appears: corrections are published and dated.',
                'The UNVERIFIABLE verdict means the available evidence does not allow a conclusion. It means neither that the claim is true, nor that it is false.',
                'The member alone remains responsible for the decisions they take, the content they share and the statements they make on the strength of a report.',
            ],
        ],
        [
            'titre' => '4. Opening and keeping an account',
            'paragraphes' => [
                'Accounts are open to natural persons aged at least :age. A minor above that age must have the authorisation of a parent or guardian.',
                'The member provides accurate information and keeps it current. One account per person: multiple accounts opened by the same person, in particular to collect an allowance or a referral reward more than once, may be closed without notice.',
                'An account becomes active only after a code sent to the declared address is validated. The member is responsible for keeping their password confidential and for all activity carried out from their account, and reports any unauthorised use without delay.',
            ],
        ],
        [
            'titre' => '5. Verification units',
            'paragraphes' => [
                'Verifications are metered in "units". Units are an internal measure of usage: they are NEITHER a currency, NOR a means of payment, NOR a claim. They cannot be transferred between members, exchanged, or converted into money.',
                'Units are granted free of charge on opening an account, monthly, and through the referral programme. Others may be purchased. The amounts in force are displayed on the platform before any commitment.',
                'An analysis that fails for a technical reason attributable to the platform gives rise to the units being credited back. An analysis carried through is due, whatever verdict it reaches: what is metered is the work, not the answer obtained.',
                'On closure of an account, unused units are lost and give rise to no refund in money.',
            ],
        ],
        [
            'titre' => '6. Payments',
            'paragraphes' => [
                'Payments are made through the means offered on the platform. The member receives a confirmation, and units are credited once the operator confirms the transaction.',
                'Where an amount is debited without the corresponding units being credited, the member contacts the platform, which credits the units or issues a refund after checking with the operator.',
            ],
        ],
        [
            'titre' => '7. Academy, examinations and certificates',
            'paragraphes' => [
                'A certificate attests to the completion of a training path on the platform. It is neither a State diploma nor a recognised professional qualification.',
                'Every certificate carries a code allowing anyone to check its authenticity publicly. The platform may revoke a certificate obtained by fraud; the revocation is reasoned and visible on the checking page.',
            ],
        ],
        [
            'titre' => '8. Content published by members',
            'paragraphes' => [
                'The member retains the rights to the content they publish. For as long as it remains online, they grant the platform the right to host, display and moderate it for the sole purposes of the service.',
                'The following are prohibited: hateful, discriminatory or violence-inciting speech; harassment; manifestly unlawful content; disclosure of third parties\' personal data; impersonation; unsolicited advertising; and the deliberate spreading of false information.',
                'The platform may remove content contrary to these terms and suspend or close the account concerned. Moderation decisions are reasoned and may be challenged at the contact address.',
                'Anyone may report content from the platform or at the contact address.',
            ],
        ],
        [
            'titre' => '9. Prohibited uses of the service',
            'paragraphes' => [
                'The following are prohibited in particular: unauthorised automated access, resale of access or of units, circumvention of usage limits, interference with the security or availability of the service, and bulk extraction of content.',
            ],
        ],
        [
            'titre' => '10. Intellectual property',
            'paragraphes' => [
                'The platform, its design system, its teaching materials and its methodology are protected. Any reproduction beyond private use requires the publisher\'s authorisation.',
                'Published verification reports may be freely quoted and shared, provided Vérif\'All is credited and the original report is linked. Quoting a verdict without its context or its date distorts its meaning.',
            ],
        ],
        [
            'titre' => '11. Personal data',
            'paragraphes' => [
                'The processing of personal data is described in the privacy policy, which forms an integral part of these terms.',
                'A member may export their data and request deletion of their account at any time from their settings. Deletion takes effect after a :retractation-day withdrawal period, during which it can be cancelled.',
            ],
        ],
        [
            'titre' => '12. Availability and changes to the service',
            'paragraphes' => [
                'The platform is provided as is, with no guarantee of uninterrupted availability. Interruptions may occur for maintenance, updates or external causes.',
                'Features may change. The removal of a feature materially affecting a paid service is announced in advance.',
            ],
        ],
        [
            'titre' => '13. Liability',
            'paragraphes' => [
                'The platform is bound by an obligation of means. It cannot be held liable for decisions taken by a member or a third party on the strength of a report, for content published by a member, for the content of third-party sites it links to, or for unavailability beyond its control.',
                'Nothing in these terms limits the publisher\'s liability in the event of gross negligence, wilful misconduct, or where the law forbids such limitation.',
            ],
        ],
        [
            'titre' => '14. Ending the relationship',
            'paragraphes' => [
                'A member may close their account at any time, without giving a reason, from their settings.',
                'The platform may suspend or close an account in the event of a serious or repeated breach of these terms. Save in an emergency or where the law requires otherwise, the decision is preceded by a warning and the member may submit their observations.',
            ],
        ],
        [
            'titre' => '15. Changes to these terms',
            'paragraphes' => [
                'These terms may be amended. Any substantial change is brought to members\' attention before it takes effect. Continued use after that date constitutes acceptance; failing that, the member may close their account.',
            ],
        ],
        [
            'titre' => '16. Governing law and disputes',
            'paragraphes' => [
                'These terms are governed by the law of the Republic of Benin.',
                'In the event of a dispute, the member first contacts the platform, which undertakes to reply. Failing an amicable solution, the dispute falls to the competent courts of the Republic of Benin, without prejudice to the protective rules from which a consumer benefits.',
            ],
        ],
    ],
    'edite_par' => 'Platform published by',
    'point' => '.',
    'nous_ecrire' => 'Contact us',
    'pour_toute_question' => 'For any question about these terms:',
    'formulaire_de_contact' => 'contact form',
    'ou_par_courriel' => ', or by e-mail at',
    'voir_aussi' => 'See also the',
    'politique_de_confidentialite' => 'privacy policy',
    'et' => 'and the',
    'mentions_legales' => 'legal notice',
];
