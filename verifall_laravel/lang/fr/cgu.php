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
    'conditions_generales' => 'Conditions générales d\'utilisation',

    'en_vigueur_depuis' => 'En vigueur depuis le :date',

    'version_fait_foi' => 'Ces conditions sont rédigées en français. En cas de divergence avec une traduction, la version française fait foi.',

    'sommaire' => 'Sommaire',

    'sections' => [
        [
            'titre' => '1. Objet et acceptation',
            'paragraphes' => [
                'Les présentes conditions régissent l\'accès et l\'usage de la plateforme Vérif\'All, éditée par l\'entité désignée ci-dessus, ci-après « la plateforme ». Elles s\'appliquent à toute personne qui crée un compte, ci-après « le membre », ainsi qu\'à toute personne qui consulte le site sans compte.',
                'La création d\'un compte suppose l\'acceptation expresse des présentes conditions. Cette acceptation est horodatée et conservée : le membre peut en demander une copie à tout moment.',
                'La consultation d\'une vérification publique, la soumission d\'une information sans compte et l\'usage de l\'outil public de contrôle d\'un certificat ne demandent pas de compte, mais restent soumis aux présentes conditions.',
            ],
        ],
        [
            'titre' => '2. Ce que fait la plateforme',
            'paragraphes' => [
                'Vérif\'All est un service de vérification de l\'information et d\'éducation aux médias. Il propose : la vérification d\'un contenu soumis par un membre, une académie de cours et d\'examens, un hub d\'actualités et d\'opportunités, et un forum citoyen.',
                'Une vérification produit un rapport comportant un verdict, un indice de fiabilité, les sources retrouvées et les signaux relevés. Les quatre verdicts possibles sont : VRAI, FAUX, TROMPEUR et NON VÉRIFIABLE.',
            ],
        ],
        [
            'titre' => '3. Portée et limites des vérifications',
            'paragraphes' => [
                'Un rapport de vérification est une ANALYSE, produite à une date donnée, à partir des sources accessibles à ce moment-là. Ce n\'est ni une décision de justice, ni une expertise judiciaire, ni une attestation officielle.',
                'La plateforme s\'engage sur les MOYENS employés — pluralité des sources, traçabilité des éléments retenus, publication de la méthode — et non sur un résultat. Une information peut être requalifiée si des éléments nouveaux apparaissent : les corrections sont publiées et datées.',
                'Le verdict NON VÉRIFIABLE signifie que les éléments disponibles ne permettent pas de trancher. Il ne signifie ni que l\'information est vraie, ni qu\'elle est fausse.',
                'Le membre reste seul responsable des décisions qu\'il prend, des contenus qu\'il partage et des propos qu\'il tient sur la foi d\'un rapport.',
            ],
        ],
        [
            'titre' => '4. Ouverture et tenue du compte',
            'paragraphes' => [
                'L\'ouverture d\'un compte est réservée aux personnes physiques âgées d\'au moins :age ans. Un mineur de plus de :age ans doit disposer de l\'autorisation du titulaire de l\'autorité parentale.',
                'Le membre fournit des informations exactes et les tient à jour. Un compte par personne : les comptes multiples ouverts par une même personne, notamment pour capter plusieurs fois une dotation ou une récompense de parrainage, peuvent être fermés sans préavis.',
                'Le compte n\'est actif qu\'après validation d\'un code envoyé à l\'adresse déclarée. Le membre est responsable de la confidentialité de son mot de passe et de toute activité menée depuis son compte. Il signale sans délai tout usage non autorisé.',
            ],
        ],
        [
            'titre' => '5. Les unités de vérification',
            'paragraphes' => [
                'Les vérifications sont décomptées en « unités ». Les unités sont une mesure d\'usage interne à la plateforme : elles ne constituent NI une monnaie, NI un moyen de paiement, NI une créance. Elles ne sont ni cessibles, ni échangeables entre membres, ni convertibles en argent.',
                'Des unités sont attribuées gratuitement à l\'ouverture du compte, chaque mois, et au titre du parrainage. D\'autres peuvent être acquises par paiement. Les montants en vigueur sont affichés sur la plateforme avant tout engagement.',
                'Une analyse qui échoue pour une cause technique imputable à la plateforme donne lieu au recréditement des unités consommées. Une analyse menée à son terme est due, quel que soit le verdict rendu : c\'est le travail qui est décompté, pas la réponse obtenue.',
                'En cas de fermeture du compte, les unités non consommées sont perdues et ne donnent lieu à aucun remboursement en argent.',
            ],
        ],
        [
            'titre' => '6. Paiements',
            'paragraphes' => [
                'Les paiements s\'effectuent par les moyens proposés sur la plateforme. Le membre reçoit une confirmation, et le crédit d\'unités intervient après confirmation de l\'opérateur.',
                'En cas de débit sans crédit d\'unités correspondant, le membre saisit la plateforme, qui procède au crédit ou au remboursement après vérification auprès de l\'opérateur.',
            ],
        ],
        [
            'titre' => '7. Académie, examens et certificats',
            'paragraphes' => [
                'Un certificat atteste l\'achèvement d\'un parcours de formation sur la plateforme. Il ne constitue ni un diplôme d\'État, ni une certification professionnelle reconnue.',
                'Chaque certificat porte un code permettant à quiconque d\'en contrôler l\'authenticité publiquement. La plateforme peut révoquer un certificat obtenu par fraude ; la révocation est motivée et visible sur la page de contrôle.',
            ],
        ],
        [
            'titre' => '8. Contenus publiés par les membres',
            'paragraphes' => [
                'Le membre reste titulaire des droits sur les contenus qu\'il publie. Il concède à la plateforme, pour la durée de leur mise en ligne, le droit de les héberger, afficher et modérer aux seules fins du service.',
                'Sont interdits : les propos haineux, discriminatoires ou appelant à la violence ; le harcèlement ; les contenus manifestement illicites ; la diffusion de données personnelles de tiers ; l\'usurpation d\'identité ; la publicité non sollicitée ; et la diffusion délibérée d\'informations fausses.',
                'La plateforme peut retirer un contenu contraire aux présentes conditions, suspendre ou fermer le compte concerné. Les décisions de modération sont motivées et peuvent être contestées à l\'adresse de contact.',
                'Toute personne peut signaler un contenu depuis la plateforme ou à l\'adresse de contact.',
            ],
        ],
        [
            'titre' => '9. Usages interdits du service',
            'paragraphes' => [
                'Sont notamment interdits : l\'accès automatisé non autorisé, la revente de l\'accès ou des unités, le contournement des limites d\'usage, l\'atteinte à la sécurité ou à la disponibilité du service, et l\'extraction massive des contenus.',
            ],
        ],
        [
            'titre' => '10. Propriété intellectuelle',
            'paragraphes' => [
                'La plateforme, sa charte, ses contenus pédagogiques et sa méthodologie sont protégés. Toute reproduction hors usage privé suppose l\'autorisation de l\'éditeur.',
                'Les rapports de vérification publiés peuvent être cités et partagés librement, à condition de mentionner Vérif\'All et de renvoyer au rapport d\'origine. Citer un verdict sans son contexte ni sa date en dénature le sens.',
            ],
        ],
        [
            'titre' => '11. Données personnelles',
            'paragraphes' => [
                'Le traitement des données personnelles est décrit dans la politique de confidentialité, qui fait partie intégrante des présentes conditions.',
                'Le membre peut à tout moment exporter ses données et demander la suppression de son compte depuis ses réglages. La suppression prend effet après un délai de rétractation de :retractation jours, pendant lequel elle peut être annulée.',
            ],
        ],
        [
            'titre' => '12. Disponibilité et évolution du service',
            'paragraphes' => [
                'La plateforme est fournie en l\'état, sans garantie de disponibilité ininterrompue. Des interruptions peuvent survenir pour maintenance, mise à jour ou cause extérieure.',
                'Les fonctionnalités peuvent évoluer. Une suppression de fonctionnalité affectant significativement un service payé est annoncée à l\'avance.',
            ],
        ],
        [
            'titre' => '13. Responsabilité',
            'paragraphes' => [
                'La plateforme est tenue d\'une obligation de moyens. Sa responsabilité ne saurait être engagée à raison des décisions prises par un membre ou par un tiers sur la foi d\'un rapport, d\'un contenu publié par un membre, du contenu des sites tiers vers lesquels elle renvoie, ni d\'une indisponibilité indépendante de sa volonté.',
                'Aucune stipulation des présentes ne limite la responsabilité de l\'éditeur en cas de faute lourde, de dol, ou dans les cas où la loi l\'interdit.',
            ],
        ],
        [
            'titre' => '14. Fin de la relation',
            'paragraphes' => [
                'Le membre peut fermer son compte à tout moment, sans motif, depuis ses réglages.',
                'La plateforme peut suspendre ou fermer un compte en cas de manquement grave ou répété aux présentes conditions. Sauf urgence ou obligation légale, la décision est précédée d\'un avertissement et le membre peut présenter ses observations.',
            ],
        ],
        [
            'titre' => '15. Modification des conditions',
            'paragraphes' => [
                'Les présentes conditions peuvent être modifiées. Toute modification substantielle est portée à la connaissance des membres avant son entrée en vigueur. La poursuite de l\'usage après cette date vaut acceptation ; à défaut, le membre peut fermer son compte.',
            ],
        ],
        [
            'titre' => '16. Droit applicable et différends',
            'paragraphes' => [
                'Les présentes conditions sont soumises au droit de la République du Bénin.',
                'En cas de différend, le membre s\'adresse d\'abord à la plateforme, qui s\'engage à répondre. À défaut de solution amiable, le litige relève des juridictions compétentes de la République du Bénin, sans préjudice des règles protectrices dont bénéficie le consommateur.',
            ],
        ],
    ],
    'edite_par' => 'Plateforme éditée par',
    'point' => '.',
    'nous_ecrire' => 'Nous écrire',
    'pour_toute_question' => 'Pour toute question relative aux présentes conditions :',
    'formulaire_de_contact' => 'formulaire de contact',
    'ou_par_courriel' => ', ou par courriel à',
    'voir_aussi' => 'Voir aussi la',
    'politique_de_confidentialite' => 'politique de confidentialité',
    'et' => 'et les',
    'mentions_legales' => 'mentions légales',
    'et_la_page_de_suppression' => 'et la',
    'supprimer_mon_compte' => 'page de suppression de compte',
];
