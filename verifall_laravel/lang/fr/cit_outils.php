<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Espace citoyen, outils
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
    'fil_d_ariane' => 'Fil d\'Ariane',
    'dashboard' => 'Dashboard',
    'outils' => 'Outils',
    'outils_osint_fact_checking' => 'Outils · OSINT · Fact-checking',
    'repertoire_des_outils' => 'Répertoire des outils',
    'les_bons_outils_pour_enqueter_verifier_et' => 'Les bons outils pour enquêter, vérifier et comprendre une information avant de la partager.',
    'outils_references' => ':count outil référencé|:count outils référencés',
    'selection_verif_all' => '● Sélection Vérif\'All',
    'rechercher_un_outil' => 'Rechercher un outil',
    'rechercher_image_domaine_metadonnees_ia' => 'Rechercher : image, domaine, métadonnées, IA…',
    'categories_d_outils' => 'Catégories d\'outils',
    'tous' => 'Tous',
    'outils_recommandes' => 'Outils recommandés',
    'tous_les_outils' => 'Tous les outils',
    'aucun_outil_trouve' => 'Aucun outil trouvé',
    'nous_n_avons_trouve_aucun_outil_correspondant' => 'Nous n\'avons trouvé aucun outil correspondant à votre recherche.<br>Essayez un autre mot-clé ou explorez une catégorie.',
    'effacer_la_recherche' => 'Effacer la recherche',
    'vous_connaissez_l_outil_maintenant_apprenez_a' => 'Vous connaissez l\'outil. Maintenant, apprenez à l\'utiliser correctement.',
    'le_cours' => 'Le cours',
    'vous_accompagne_etape_par_etape_dans' => 'vous accompagne étape par étape dans vos enquêtes.',
    'decouvrir_le_cours' => 'Découvrir le cours',
    'outils_externes_selectionnes_a_titre_informatif' => 'Outils externes sélectionnés à titre informatif. Vérif\'All ne garantit pas les résultats produits par ces services. Pour un verdict documenté,',
    'soumettez_le_contenu_au_laboratoire' => 'soumettez le contenu au laboratoire',

    // Libellés des pastilles de filtre. Les VALEURS de filtre, elles, vivent
    // dans `Outils::CATEGORIES` et ne se traduisent jamais : elles filtrent.
    'categorie' => [
        'recherche' => 'Recherche',
        'web_domaines' => 'Web & domaines',
        'archives' => 'Archives',
        'medias' => 'Métadonnées',
        'ia' => 'IA',
        'geolocalisation' => 'Géolocalisation',
        'fact_checking' => 'Fact-checking',
    ],
];
