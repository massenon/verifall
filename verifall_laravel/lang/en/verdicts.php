<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Verdicts
|--------------------------------------------------------------------------
|
| FOUR, and only four. They are named in the legal notice, in the ClaimReview
| markup and on the shareable denial cards: a fifth word appearing here would
| be a promise the platform keeps nowhere else.
|
| "Unverifiable" is NOT "half true". It means the available evidence does not
| allow a conclusion — a distinction the English wording must preserve as
| carefully as the French one, because it is the verdict most often
| misreported.
|
*/

return [
    'court' => [
        'true' => 'True',
        'false' => 'False',
        'misleading' => 'Misleading',
        'unverifiable' => 'Unverifiable',
    ],

    'explication' => [
        'true' => 'Concurring sources confirm this claim.',
        'false' => 'The sources consulted contradict this claim.',
        'misleading' => 'The claim holds some truth but is presented in a misleading way.',
        'unverifiable' => 'The available evidence does not allow a conclusion.',
    ],
    'rapport' => [
        'libelle' => [
            'true' => 'Reliable information',
            'false' => 'False information',
            'misleading' => 'Questionable content',
            'unverifiable' => 'Unverifiable',
        ],

        'phrase' => [
            'true' => 'This content has been confirmed as authentic.',
            'false' => 'This content has been identified as false information.',
            'misleading' => 'This content mixes true and false elements. Proceed with caution.',
            'unverifiable' => 'The available evidence does not allow a conclusion.',
        ],
    ],
];
