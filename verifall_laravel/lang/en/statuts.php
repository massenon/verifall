<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Statuses
|--------------------------------------------------------------------------
|
| One sub-array per enum, and the key is ALWAYS the enum value — the one the
| database carries, never the case name. A missing translation then shows the
| raw key (`statuts.verification.queued`): a wrong word would go unnoticed, a
| raw key is seen at once.
|
| Two wordings must not drift, because each carries the whole point of its
| state. `retry_scheduled` is NOT a failure: the analysis is coming back on
| its own, and calling it "Failed" would tell someone their request is lost
| when it is not. `human_review` is not ordinary waiting either: the engines
| disagree and a human decides — "Pending" would drop that entirely.
|
*/

return [

    'verification' => [
        'queued' => 'Queued',
        'analyzing' => 'Analysis in progress',
        'retry_scheduled' => 'Retry scheduled',
        'completed' => 'Completed',
        'human_review' => 'Under arbitration',
        'failed' => 'Analysis interrupted',
    ],

    'paiement' => [
        'pending' => 'Awaiting confirmation',
        'succeeded' => 'Paid',
        'failed' => 'Failed',
        'expired' => 'Expired',
    ],

    'inscription' => [
        'enrolled' => 'Enrolled',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
    ],

    'devoir' => [
        'submitted' => 'Awaiting review',
        'passed' => 'Passed',
        'needs_work' => 'Needs work',
    ],

    'note_communaute' => [
        'libelle' => [
            'pending' => 'Awaiting ratings',
            'published' => 'Note published',
            'not_helpful' => 'Note not retained',
        ],

        /*
         | Said to the author, and to no one else. The waiting wording promises
         | nothing and accuses of nothing: "under review" would suggest an
         | imminent human decision, which does not exist here.
         */
        'explication' => [
            'pending' => 'Your note is waiting to be rated by enough people. For now it is visible to you alone.',
            'published' => 'Your note is visible to everyone: people who usually vote the opposite way found it helpful.',
            'not_helpful' => 'Your note was rated and was not retained. It stays visible to you alone.',
        ],
    ],

    'parrainage' => [
        'libelle' => [
            'registered' => 'Waiting',
            'qualified' => 'Action completed',
            'rewarded' => 'Reward granted',
        ],

        /* Said to the REFERRER: they are the one waiting. */
        'explication' => [
            'registered' => 'Your referral has signed up — they still have to run their first verification.',
            'qualified' => 'The action is done: the reward is on its way.',
            'rewarded' => 'Reward paid out.',
        ],

        /* Said to the REFERRED person: they still have something to do. */
        'explication_filleul' => [
            'registered' => 'Run your first verification to unlock your welcome units.',
            'qualified' => 'Your first verification is done: your units are on their way.',
            'rewarded' => 'Your welcome units have been paid out.',
        ],
    ],

    /*
     | Certification-exam states speak from the STAFF's point of view
     | (`examens.html`, `correction-examens.html`): on a paper, "grading"
     | means a review someone has started — a candidate still writing is
     | `in_progress`, "Being written".
     */
    'examen' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ],

    'copie_examen' => [
        'in_progress' => 'Being written',
        'pending' => 'Pending',
        'grading' => 'Being graded',
        'graded' => 'Graded',
        'abandoned' => 'Abandoned',
    ],

    'session_examen' => [
        'planned' => 'Planned',
        'open' => 'Open',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ],

    'inscription_examen' => [
        'registered' => 'Registered',
        'confirmed' => 'Confirmed',
        'deferral_requested' => 'Deferral requested',
        'cancelled' => 'Cancelled',
    ],

    'question' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'to_review' => 'To review',
        'archived' => 'Archived',
    ],

    'message_contact' => [
        'new' => 'New',
        'in_progress' => 'In progress',
        'handled' => 'Handled',
        'spam' => 'Spam',
    ],

    'signalement_contenu' => [
        'pending' => 'Pending',
        'upheld' => 'Upheld',
        'dismissed' => 'Dismissed',
    ],
];
