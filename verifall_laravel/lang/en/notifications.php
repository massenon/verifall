<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Preferences and bell tabs
|--------------------------------------------------------------------------
|
| The keys are the enum values, the very ones written to the database and
| read by the notifiers. Translating the label does not touch them: a
| preference left off stays off, whatever language the screen is in.
|
| The newsletter is not here: it is a marketing consent, tracked in
| `consents`.
|
*/

return [

    'preference' => [
        'libelle' => [
            'verification_results' => 'Verification results',
            'learning_reminders' => 'Learning reminders',
            'forum_activity' => 'Forum activity',
            'login_alerts' => 'New sign-in alerts',
        ],

        'description' => [
            'verification_results' => 'Be told as soon as a report is ready or updated.',
            'learning_reminders' => 'Upcoming Live Class, streak at risk, course to resume.',
            'forum_activity' => 'Replies to your discussions, and mentions.',
            'login_alerts' => 'Email sent when a new device signs in.',
        ],
    ],

    /*
     | The bell's tabs. "All" is not one of them: it is the absence of a
     | filter, so it has no key here.
     */
    'categorie' => [
        'verif' => 'Verifications',
        'lms' => 'Learning',
        'community' => 'Community',
    ],
];
