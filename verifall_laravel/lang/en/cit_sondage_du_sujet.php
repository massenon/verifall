<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Citizen area: the poll on a thread
|--------------------------------------------------------------------------
|
| Results stay hidden until you have voted, and the page says why: seeing them
| first would sway your answer. Giving the reason turns what looks like a
| withheld feature into what it is, a safeguard against anchoring. The English
| keeps the reason attached to the rule.
|
*/

return [
    'sondage' => 'Poll',

    'les_resultats_s_afficheront_une_fois_votre' => 'The results will appear once your answer is recorded: seeing them first would sway your choice.',

    'connectez_vous_pour_repondre_a_ce_sondage' => 'Sign in to answer this poll and see its results.',

    'poser_un_sondage' => 'Ask a poll question',
    'une_question_deux_a_six_reponses_un' => 'One question, two to six answers. One vote per person.',

    'ajouter_un_sondage' => 'Add a poll',
    'votre_sondage' => 'Your poll',
    'question' => 'Question',
    'reponses_possibles' => 'Possible answers',

    'annuler' => 'Cancel',
    'publier_le_sondage' => 'Publish the poll',

    // Results. "participant" takes the same -s plural as the French, so the
    // conditional in the view stays correct once the stem is translated.
    'votre_reponse' => 'your answer',
    'pour_cent' => 'per cent',
    'participant' => 'participant|participants',
    'ce_sondage_est_clos' => 'This poll is closed.',
    'reponse' => 'Answer',
];
