<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, hub-infos
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
    'hub_opportunites' => 'Hub opportunités',
    'l_actualite_passee_au_crible_et_les' => 'L\'actualité passée au crible, et les opportunités dont l\'organisation a été vérifiée.',
    'rechercher_une_actualite_une_opportunite' => 'Rechercher une actualité, une opportunité',
    'a_la_une' => 'À la une',
    'l_analyse_du_jour' => 'L\'analyse du jour',
    'aucune_actualite_trouvee' => 'Aucune actualité trouvée',
    'reinitialiser_les_filtres' => 'Réinitialiser les filtres',
    'opportunites_verifiees' => 'Opportunités vérifiées',
    'opportunites_benin' => 'Opportunités Bénin',
    'organisation_confirmee_canal_officiel_coordonnees_verifiees_' => 'Organisation confirmée, canal officiel, coordonnées vérifiées — et jamais de frais de dossier.',
    'hors_du_benin' => 'Hors du Bénin',
    'opportunites_internationales' => 'Opportunités Internationales',
    'bourses_conferences_financements_et_postes_hors_du' => 'Bourses, conférences, financements et postes hors du Bénin, contrôlés de la même façon.',
    'une_information_vous_semble_douteuse' => 'Une information vous semble douteuse ?',
    'ne_la_partagez_pas_avant_de_l' => 'Ne la partagez pas avant de l\'avoir vérifiée. Le laboratoire Vérif\'All croise les sources et documente son verdict.',
    'pourquoi_faire_confiance_a_ces_informations' => 'Pourquoi faire confiance à ces informations ?',
    'les_contenus_presentes_dans_ce_hub_sont' => 'Les contenus présentés dans ce Hub sont sélectionnés, vérifiés ou référencés selon les critères de confiance de Vérif\'All : source primaire identifiable, canal officiel confirmé et recoupement documenté.',

    /*
     | Second passage : les onglets et les boutons de filtre, sautes par
     | l'extraction parce que leur balise porte un `wire:click` et une classe
     | calculee.
     |
     | Le champ de recherche a SA propre cle : le libelle pour lecteur d'ecran
     | se termine par un point, l'indice de saisie par des points de suspension.
     | Les confondre changerait le rendu du prototype.
     */
    'fil_d_ariane' => 'Fil d\'Ariane',
    'rechercher_une_actualite_une_opportunite_indice' => 'Rechercher une actualité, une opportunité…',
    'sections_du_hub' => 'Sections du Hub',
    'actualites' => 'Actualités',
    'categories_d_actualites' => 'Catégories d\'actualités',
    'nature_des_opportunites' => 'Nature des opportunités',
    'tout' => 'Tout',
    'toutes' => 'Toutes',
    'lire_l_analyse' => 'Lire l\'analyse',
    'lire_l_article' => 'Lire l\'article',
    'verifier_une_information' => 'Vérifier une information',
    'comprendre_notre_methode' => 'Comprendre notre méthode',

    /*
     | Les deux etats vides etaient un ternaire francais a l'interieur d'un
     | `{{ }}` : invisible pour une extraction qui ne lit que le texte entre
     | les balises. Le message change selon qu'un filtre est actif ou non —
     | dire « revenez plus tard » a quelqu'un qui vient de filtrer serait faux.
     */
    'essayez_une_autre_recherche_ou_modifiez_vos' => 'Essayez une autre recherche ou modifiez vos filtres.',
    'le_hub_s_enrichit_chaque_semaine' => 'Le Hub s\'enrichit chaque semaine.',
];
