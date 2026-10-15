<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Messages du compte citoyen
|--------------------------------------------------------------------------
|
| Ces messages sont rendus par le composant Livewire ET par l'API v1. Les
| écrire une seule fois évite qu'un même refus se formule de deux façons selon
| le canal, ce qui est exactement le genre d'écart qui fait douter de la
| plateforme au pire moment.
|
| « SUPPRIMER » n'est pas cité en dur ici : le message nomme les deux mots
| acceptés, pour que l'anglophone qui lit ce refus sache quoi taper.
|
*/

return [
    'suppression_mot_requis' => 'Tapez SUPPRIMER pour confirmer.',
    'suppression_mot_exact' => 'Tapez exactement SUPPRIMER.',
    'suppression_mot_de_passe_requis' => 'Entrez votre mot de passe.',
];
