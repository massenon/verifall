<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, mes-verifications
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
    'mes_verifications' => 'Mes Vérifications',
    'votre_activite_de_verification_expand_more' => '<span class="text-sm font-display font-bold text-ink">Votre activité de vérification</span> <span class="material-symbols-rounded transition-transform" aria-hidden="true">expand_more</span>',
    'contenus_soumis' => 'Contenus soumis',
    'en_cours' => 'En cours',
    'intox_detectees' => 'Intox détectées',
    'infos_fiables' => 'Infos fiables',
    'rechercher' => 'Rechercher',
    'contenu' => 'Contenu',
    'type' => 'Type',
    'verdict' => 'Verdict',
    'confiance' => 'Confiance',
    'date' => 'Date',
    'action' => 'Action',
    'aucun_resultat' => 'Aucun résultat',
    'aucune_verification_ne_correspond_a_vos_criteres' => 'Aucune vérification ne correspond à vos critères actuels.',
    'reinitialiser_les_filtres' => 'Réinitialiser les filtres',
    'votre_laboratoire_est_encore_vide' => 'Votre laboratoire est encore vide',
    /*
     | Second passage : les filtres et les liens d'action. Leur balise portait
     | un `wire:click` et une classe calculee, l'extraction les sautait.
     |
     | Les compteurs faisaient leur accord a la main, avec un ternaire par
     | marque du pluriel (`contenu{{ ... ? 's' : '' }}`). Cette technique ne
     | survit pas au changement de langue : `trans_choice` la remplace.
     */
    'fil_d_ariane' => 'Fil d\'Ariane',
    'votre_laboratoire_personnel_de_verification' => 'Votre laboratoire personnel de vérification —',
    'n_contenus_analyses' => ':count contenu analysé|:count contenus analysés',
    'derniere_verification' => 'Dernière vérification',
    'verifier' => 'Vérifier',
    'verifier_un_contenu' => 'Vérifier un contenu',
    'rechercher_dans_mes_verifications' => 'Rechercher dans mes vérifications…',
    'filtrer_par_type' => 'Filtrer par type',
    'filtrer_par_verdict' => 'Filtrer par verdict',
    'tous_les_types' => 'Tous les types',
    'tous_les_verdicts' => 'Tous les verdicts',
    'tous' => 'Tous',
    'vrai' => 'Vrai',
    'faux' => 'Faux',
    'trompeur' => 'Trompeur',
    'n_verifications' => ':count vérification|:count vérifications',
    'voir_le_rapport' => 'Voir le rapport',
    'suivre' => 'Suivre',
    'indisponible' => 'Indisponible',
    'de_fiabilite' => 'de fiabilité',

    'commencez_votre_premiere_verification_pour_construire_votre' => 'Commencez votre première vérification pour construire votre historique.',
];
