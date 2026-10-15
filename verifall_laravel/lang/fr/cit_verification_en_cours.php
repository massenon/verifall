<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, verification-en-cours
|--------------------------------------------------------------------------
|
| ENGENDRE depuis la vue, puis relu. La copie du prototype reste ainsi
| identique au caractere pres.
|
| Les valeurs qui portent du balisage en ligne sont rendues en syntaxe non
| echappee. Ce n'est PAS une entorse a la regle de securite : ces chaines sont
| ecrites par l'equipe, jamais par un visiteur.
|
*/

return [
    'analyse_en_cours' => 'Analyse en cours',
    'analyse_mise_en_attente' => 'Analyse mise en attente',
    'refresh_relancer_maintenant_relance' => '<span class="material-symbols-rounded !text-[18px]" aria-hidden="true">refresh</span> <span wire:loading.remove wire:target="relancer">Relancer maintenant</span> <span wire:loading wire:target="relancer">Relance…</span>',
    'l_analyse_prend_plus_de_temps_que' => 'L\'analyse prend plus de temps que prévu.',
    'un_moteur_d_analyse_ne_repond_peut' => 'Un moteur d\'analyse ne répond peut-être pas. Vous pouvez fermer cette page : le rapport apparaîtra dans « Mes vérifications » dès qu\'il sera prêt, et vos unités vous seront rendues si l\'analyse n\'aboutit pas.',

    // Restes en dur. Les phrases de remboursement sont decoupees en fragments
    // parce qu'un <strong> et une condition de pluriel les traversent.
    'fil_d_ariane' => 'Fil d\'Ariane',
    'verifier' => 'Vérifier',
    'analyse' => 'Analyse',
    'ne_fermez_pas_cette_page_le_rapport' => 'Ne fermez pas cette page — le rapport s\'ouvrira automatiquement.',
    'vous_pouvez_fermer_cette_page_sans_rien' => 'Vous pouvez fermer cette page sans rien perdre.',
    'reference' => 'Référence :',
    'avancement_de_l_analyse' => 'Avancement de l\'analyse',

    'plutot_que_de_vous_rendre_un_verdict' => 'Plutôt que de vous rendre un verdict que rien n\'appuie, nous reprenons l\'analyse',
    'des_maintenant' => 'dès maintenant.',
    'dans_n_minutes' => 'dans :nombre minute.|dans :nombre minutes.',

    'rien_n_est_perdu_la_reprise_est' => 'Rien n\'est perdu : la reprise est automatique, et si nous ne parvenons toujours pas à conclure,',
    'vos' => 'vos',
    'unites' => 'unités',
    'vous_seront_rendues' => 'vous seront rendues.',
    'votre' => 'votre',
    'unite' => 'unité',
    'vous_sera_rendue' => 'vous sera rendue.',

    'voir_mes_verifications' => 'Voir mes vérifications',
    'cette_page_se_met_a_jour_toute' => 'Cette page se met à jour toute seule. Chaque étape s\'allume quand elle est réellement franchie.',
    'nous_reprenons_l_analyse_sans_que_vous' => 'Nous reprenons l\'analyse sans que vous ayez à revenir. Le rapport apparaîtra dans « Mes vérifications ».',
];
