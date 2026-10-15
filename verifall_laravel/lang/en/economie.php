<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Units and course levels
|--------------------------------------------------------------------------
|
| The reasons are written into a ledger that will be re-read to explain a
| balance to someone disputing it. What identifies the entry is the VALUE
| (`unit_logs.reason`); these labels only serve to read it.
|
*/

return [

    'motif' => [
        'welcome' => 'Welcome bonus',
        'recharge' => 'Mobile Money top-up',
        'monthly_grant' => 'Monthly allowance',
        'verification' => 'Content verification',
        'premium_course' => 'Premium course',
        'referral' => 'Referral',
        'admin_adjustment' => 'Administrator adjustment',
        'refund' => 'Refund',
    ],

    /*
     | Stored in English, displayed in the language of the request: the
     | catalogue filter goes through the value (`?niveau=beginner`), never
     | through the word.
     */
    'niveau_cours' => [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
    ],
];
