<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Les statuts
|--------------------------------------------------------------------------
|
| Un sous-tableau par énumération, et la clé est TOUJOURS la valeur de l'enum
| — celle que porte la base, jamais le nom du cas. Une traduction manquante
| affiche alors la clé brute (`statuts.verification.queued`) : un mot faux
| passerait inaperçu, une clé brute se voit.
|
*/

return [

    /*
     | `retry_scheduled` n'est PAS un échec, et `human_review` n'est pas une
     | attente ordinaire : les moteurs se contredisent et un humain tranche.
     | Ces deux nuances sont le sujet même de l'énumération — les aplatir en
     | « échec » et « en attente » perdrait l'information.
     */
    'verification' => [
        'queued' => "En file d'attente",
        'analyzing' => 'Analyse en cours',
        'retry_scheduled' => 'Reprise programmée',
        'completed' => 'Terminée',
        'human_review' => 'En arbitrage',
        'failed' => 'Analyse interrompue',
    ],

    'paiement' => [
        'pending' => 'En attente de validation',
        'succeeded' => 'Payé',
        'failed' => 'Échoué',
        'expired' => 'Expiré',
    ],

    'inscription' => [
        'enrolled' => 'Inscrit',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
    ],

    'devoir' => [
        'submitted' => 'En attente de correction',
        'passed' => 'Validé',
        'needs_work' => 'À revoir',
    ],

    'note_communaute' => [
        'libelle' => [
            'pending' => "En attente d'évaluation",
            'published' => 'Note publiée',
            'not_helpful' => 'Note non retenue',
        ],

        /*
         | Ce qu'on dit à l'auteur, et à lui seul. Le texte de l'attente ne
         | promet rien et n'accuse de rien : « en cours d'examen » laisserait
         | croire à une décision humaine imminente, qui n'existe pas ici.
         */
        'explication' => [
            'pending' => "Votre note attend d'être évaluée par assez de personnes. Elle n'est visible que de vous pour l'instant.",
            'published' => "Votre note est visible de tout le monde : des personnes qui votent habituellement à l'opposé l'ont jugée utile.",
            'not_helpful' => "Votre note a été évaluée et n'a pas été retenue. Elle reste visible de vous seul.",
        ],
    ],

    'parrainage' => [
        'libelle' => [
            'registered' => 'En attente',
            'qualified' => 'Action réalisée',
            'rewarded' => 'Récompense attribuée',
        ],

        /* Dit au PARRAIN : lui attend. */
        'explication' => [
            'registered' => "Votre filleul s'est inscrit — il doit encore réaliser sa première vérification.",
            'qualified' => "L'action est faite : la récompense arrive.",
            'rewarded' => 'Récompense versée.',
        ],

        /* Dit au FILLEUL : lui a encore un geste à faire. */
        'explication_filleul' => [
            'registered' => 'Lancez votre première vérification pour débloquer vos unités de bienvenue.',
            'qualified' => 'Votre première vérification est faite : vos unités arrivent.',
            'rewarded' => 'Vos unités de bienvenue ont été versées.',
        ],
    ],

    /*
     | Les états des examens certifiants parlent du point de vue du STAFF
     | (`examens.html`, `correction-examens.html`) : « En cours » sur une
     | copie désigne une correction commencée, pas un candidat en train de
     | composer — c'est « En composition » qui dit cela.
     */
    'examen' => [
        'draft' => 'Brouillon',
        'published' => 'Publié',
        'archived' => 'Archivé',
    ],

    'copie_examen' => [
        'in_progress' => 'En composition',
        'pending' => 'En attente',
        'grading' => 'En cours',
        'graded' => 'Corrigée',
        'abandoned' => 'Abandonnée',
    ],

    'session_examen' => [
        'planned' => 'Planifiée',
        'open' => 'Ouverte',
        'closed' => 'Fermée',
        'cancelled' => 'Annulée',
    ],

    'inscription_examen' => [
        'registered' => 'Inscrit',
        'confirmed' => 'Confirmé',
        'deferral_requested' => 'Report demandé',
        'cancelled' => 'Annulé',
    ],

    'question' => [
        'draft' => 'Brouillon',
        'published' => 'Publiée',
        'to_review' => 'À réviser',
        'archived' => 'Archivée',
    ],

    'message_contact' => [
        'new' => 'Nouveau',
        'in_progress' => 'En cours',
        'handled' => 'Traité',
        'spam' => 'Indésirable',
    ],

    'signalement_contenu' => [
        'pending' => 'En attente',
        'upheld' => 'Retenu',
        'dismissed' => 'Rejeté',
    ],
];
