<?php

declare(strict_types=1);

/*
 | Toute image déclarée par une vue existe-t-elle vraiment ?
 |
 | Ce test naît de deux logos de partenaires — EVZ et OFAJ — référencés par la
 | page d'accueil sans que leurs fichiers existent. Deux images cassées sur la
 | page la plus vue du site, pendant des semaines, sans qu'aucun test ne
 | s'en plaigne : une balise `<img>` dont le fichier manque ne produit ni
 | erreur PHP, ni erreur HTTP. Le navigateur affiche un cadre vide, et c'est
 | tout.
 |
 | Le coût d'un tel défaut est asymétrique : invisible au développement,
 | immédiatement visible par le visiteur.
 */

/**
 * Les chemins d'actifs déclarés par les vues.
 *
 * On ne lit QUE les appels `asset('...')` littéraux. Un chemin construit à
 * l'exécution — depuis la base, depuis une variable — ne peut pas être vérifié
 * statiquement, et prétendre le contraire donnerait une fausse assurance.
 *
 * @return array<string, list<string>> chemin d'actif => vues qui le déclarent
 */
function actifsDeclares(): array
{
    $trouves = [];

    $iterateur = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'))
    );

    foreach ($iterateur as $fichier) {
        if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
            continue;
        }

        $source = file_get_contents($fichier->getPathname());
        preg_match_all("/asset\(\s*'([^']+)'\s*\)/", $source, $correspondances);

        foreach ($correspondances[1] as $chemin) {
            $trouves[$chemin][] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $fichier->getPathname());
        }
    }

    return $trouves;
}

it('ne déclare aucune image, feuille de style ou script qui manque', function (): void {
    $manquants = [];

    foreach (actifsDeclares() as $chemin => $vues) {
        // On ignore ce qui n'est pas un fichier statique du dépôt.
        if (str_starts_with($chemin, 'http') || str_contains($chemin, '{')) {
            continue;
        }

        /*
         | Un DOSSIER existant est une déclaration légitime : c'est la base d'où
         | un script charge ses fichiers à la demande (`data-vdt-vendor` de
         | DataTables, qui ne tire JSZip et pdfmake qu'au clic sur l'export).
         | Un dossier absent reste un manquant.
         */
        if (! is_file(public_path($chemin)) && ! is_dir(public_path($chemin))) {
            $manquants[] = sprintf('%s — déclaré par %s', $chemin, implode(', ', array_unique($vues)));
        }
    }

    expect($manquants)->toBe([], "Actifs déclarés mais absents :\n  ".implode("\n  ", $manquants));
});

it('ne sert aucune image de plus de 1 Mo', function (): void {
    /*
     | Le seuil n'est pas arbitraire. Le produit revendique fonctionner « même
     | avec une connexion 3G » — c'est écrit dans son propre article de presse.
     | Une image d'un mégaoctet dément cette promesse à elle seule.
     |
     | Ce test existe parce que la page d'accueil a servi un PNG de 12 Mo dans
     | une carte de 240 pixels de haut.
     */
    $lourds = [];

    foreach (actifsDeclares() as $chemin => $vues) {
        if (str_starts_with($chemin, 'http') || str_contains($chemin, '{')) {
            continue;
        }

        $fichier = public_path($chemin);

        if (! is_file($fichier)) {
            continue;
        }

        $poids = filesize($fichier);

        // La police d'icônes est un cas connu et consigné : 5,35 Mo pour
        // 99 pictogrammes, deux tentatives de sous-ensemble mesurées et
        // rejetées. Voir docs/RESTE_A_FAIRE.md.
        if (str_contains($chemin, 'material-symbols')) {
            continue;
        }

        if ($poids > 1_000_000) {
            $lourds[] = sprintf('%s (%.1f Mo)', $chemin, $poids / 1e6);
        }
    }

    expect($lourds)->toBe([], "Actifs trop lourds :\n  ".implode("\n  ", $lourds));
});

it('donne un texte alternatif à chaque image des vues publiques', function (): void {
    /*
     | Une image sans `alt` est invisible pour un lecteur d'écran et pour Google
     | Images. Le cahier des charges l'exige explicitement ; on le vérifie plutôt
     | que de l'espérer.
     |
     | `alt=""` reste autorisé : c'est la façon CORRECTE de déclarer une image
     | purement décorative, et l'interdire pousserait à inventer une description
     | pour un trait de séparation.
     */
    $sansAlt = [];

    foreach (glob(resource_path('views/public/*.blade.php')) as $vue) {
        $source = (string) file_get_contents($vue);

        /*
         | LES EXPRESSIONS BLADE SONT RETIRÉES AVANT DE DÉCOUPER LES BALISES.
         |
         | `<img[^>]*>` s'arrête au premier `>` — y compris celui d'une flèche
         | d'objet. Une image dont la source vient de la base
         | (`{{ asset($partenaire->logo_path) }}`) était donc coupée AVANT son
         | `alt`, et déclarée sans texte alternatif alors qu'elle en a un.
         |
         | Le faux positif est apparu au lot 9, quand le carrousel de
         | partenaires a cessé d'être écrit en dur. Il se serait reproduit à
         | chaque image servie depuis une table — c'est-à-dire de plus en plus
         | souvent.
         |
         | Retirer les expressions plutôt qu'élargir le motif : la vérification
         | de `alt` reste identique, et aucune image ne peut s'y soustraire.
         */
        $sansExpressions = (string) preg_replace('/\{\{.*?\}\}/s', 'x', $source);

        preg_match_all('/<img\b[^>]*>/i', $sansExpressions, $balises);

        foreach ($balises[0] as $balise) {
            if (! preg_match('/\balt\s*=/i', $balise)) {
                $sansAlt[] = basename($vue).' : '.mb_substr($balise, 0, 90);
            }
        }
    }

    expect($sansAlt)->toBe([], "Images sans attribut alt :\n  ".implode("\n  ", $sansAlt));
});

it('ne laisse aucune feuille de page orpheline', function (): void {
    /*
     | Une feuille de `assets/css/pages/` est un bloc `<style>` extrait
     | VERBATIM du prototype. Non chargée, elle ne produit AUCUNE erreur : la
     | page sort sans ses styles, et les classes écrites dans le balisage ne
     | font rien.
     |
     | Trois feuilles étaient dans ce cas, dont celle qui porte
     | `.course-tab.is-active` — d'où des onglets « Aperçu / Curriculum /
     | Instructeur / Avis » sans onglet actif visible. Le symptôme se lit comme
     | un bug de JavaScript ; la cause était une balise `<link>` manquante.
     */
    $orphelines = [];

    foreach (glob(public_path('assets/css/pages/*.css')) as $feuille) {
        $nom = basename($feuille);
        $chargee = false;

        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            if (str_contains(file_get_contents($fichier->getPathname()), 'pages/'.$nom)) {
                $chargee = true;
                break;
            }
        }

        if (! $chargee) {
            $orphelines[] = $nom;
        }
    }

    expect($orphelines)->toBe([], 'Feuilles de page jamais chargées :
  '.implode('
  ', $orphelines));
});
