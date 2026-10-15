<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Public front, a published fact-check
|--------------------------------------------------------------------------
|
| This is the page that circulates IN REPLY to the rumour: the verdict is the
| first thing read, and no account is needed to read it.
|
| `cette_verification_a_ete_corrigee` opens the block sitting directly under
| the analysis, before the call to action. That placement is the point:
| someone arriving from a share must learn the verdict changed BEFORE
| resharing. The English keeps it a plain past-tense statement — no hedging,
| no "may have been updated".
|
| French puts a space before a colon; English does not. "VERDICT:" is
| therefore tighter than its French counterpart on purpose.
|
*/

return [
    'verdict' => 'VERDICT:',
    'publie_le' => 'Published on',
    'par_l_equipe_verif_all' => 'By the Vérif\'All team',
    'source_officielle_de_preuve' => 'Official source of evidence:',
    'cette_verification_a_ete_corrigee' => 'This fact-check has been corrected',
    'voir_toutes_nos_corrections' => 'See all our corrections',
    'vous_avez_un_doute_sur_une_info' => 'Not sure about something you read?',
    'faites_verifier_gratuitement_un_lien_ou_un' => 'Have a link or a text checked for free, without creating an account.',
    'verifier_une_information_gratuitement' => 'Check a piece of information for free',
    'analyses_similaires' => 'Related analyses',
    'partager_cette_verification' => 'Share this fact check',
];
