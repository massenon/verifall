<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen account messages
|--------------------------------------------------------------------------
|
| Rendered by the Livewire component AND by the v1 API. Writing them once
| keeps a single refusal from being worded two different ways depending on the
| channel.
|
| The word named here is DELETE, and the server accepts it: the English screen
| would otherwise ask for a word the check rejects. Both words are accepted in
| both languages, so someone who switches language between reading and typing
| is not locked out either.
|
*/

return [
    'suppression_mot_requis' => 'Type DELETE to confirm.',
    'suppression_mot_exact' => 'Type DELETE exactly.',
    'suppression_mot_de_passe_requis' => 'Enter your password.',
];
