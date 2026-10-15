<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Front public, une verification publiee
|--------------------------------------------------------------------------
|
| Transposition de facts-details.html. C'est la page qui circule EN REPONSE a
| la rumeur : le verdict s'y lit en premier, et sans compte.
|
| `cette_verification_a_ete_corrigee` ouvre le bloc pose juste sous l'analyse,
| avant l'appel a l'action. La place n'est pas decorative : quelqu'un qui
| arrive ici par un partage doit apprendre que le verdict a change AVANT de
| repartager. La traduction garde donc le constat au passe et sans detour.
|
| L'espace insecable avant les deux-points de « VERDICT : » suit l'usage
| francais ; l'anglais le supprime, c'est voulu.
|
*/

return [
    'verdict' => 'VERDICT :',
    'publie_le' => 'Publié le',
    'par_l_equipe_verif_all' => 'Par l\'équipe Vérif\'All',
    'source_officielle_de_preuve' => 'Source officielle de preuve :',
    'cette_verification_a_ete_corrigee' => 'Cette vérification a été corrigée',
    'voir_toutes_nos_corrections' => 'Voir toutes nos corrections',
    'vous_avez_un_doute_sur_une_info' => 'Vous avez un doute sur une info ?',
    'faites_verifier_gratuitement_un_lien_ou_un' => 'Faites vérifier gratuitement un lien ou un texte, sans créer de compte.',
    'verifier_une_information_gratuitement' => 'Vérifier une information gratuitement',
    'analyses_similaires' => 'Analyses similaires',
    'partager_cette_verification' => 'Partager cette vérification',
];
