<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gamification — motifs de gain d'XP
|--------------------------------------------------------------------------
|
| Même discipline que `economie.motif` : ce qui identifie une ligne du journal
| est la VALEUR (`xp_logs.reason`), jamais le mot. Ces libellés ne servent qu'à
| la relire, et `libelleCanonique()` en écrit la version de repli en base.
|
*/

return [

    'motif_xp' => [
        'quiz' => 'Quiz réussi',
        'welcome_quiz' => 'Quiz de bienvenue',
        'badge' => 'Badge débloqué',
        'course' => 'Cours terminé',
        'admin_adjustment' => 'Ajustement administrateur',
    ],

];
