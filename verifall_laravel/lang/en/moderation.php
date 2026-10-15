<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Forum moderation
|--------------------------------------------------------------------------
|
| Keys = enum values. "Publish and flag" is not a softer "Block": the
| distinction is the whole point of the device — the words of a rumour are
| precisely what people come to report — and both texts must keep it legible
| in each language.
|
*/

return [
    'action_mot_clef' => [
        'libelle' => [
            'block' => 'Block',
            'flag' => 'Publish and flag',
        ],

        'explication' => [
            'block' => 'The message is refused for publication. The author is notified.',
            'flag' => 'The message is published normally and joins the moderation queue.',
        ],
    ],
];
