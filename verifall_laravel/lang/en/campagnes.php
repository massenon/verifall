<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Campaign dialogues and banners
|--------------------------------------------------------------------------
|
| Only the secondary button and the close label live here. The title, text and
| call to action come from the DATABASE: they are written from the admin, and
| translating them is a product decision rather than a language file.
|
| The consequence is deliberate and worth knowing: on an English interface the
| chrome is translated, while the campaign message stays in the language it
| was written in.
|
*/

return [
    'plus_tard' => 'Later',
    'masquer_ce_message' => 'Hide this message',
];
