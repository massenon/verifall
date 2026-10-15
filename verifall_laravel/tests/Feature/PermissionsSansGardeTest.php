<?php

declare(strict_types=1);

use App\Support\Rbac\MatricePermissions;

/*
|--------------------------------------------------------------------------
| Le cliquet des permissions orphelines
|--------------------------------------------------------------------------
|
| Une permission déclarée, attribuée à un rôle, et employée NULLE PART est une
| promesse que le code ne tient pas. Elle est pire qu'une permission absente :
| on la retire à quelqu'un en croyant lui retirer un accès, et on ne lui retire
| rien.
|
| Neuf existaient au 2 septembre 2026 ; sept ont été refermées le même jour.
| Cette liste est un CLIQUET, sur le modèle de `phpstan-baseline.neon` et de
| `ClassesTailwindPresentesTest` : **elle ne doit que rétrécir**. Régénérer le
| tableau pour absorber une entrée neuve viderait le dispositif de son sens —
| c'est le seul geste interdit ici.
|
*/

/**
 * Les permissions qu'aucun écran, aucune route et aucune Policy n'emploie.
 *
 * Chaque entrée porte SA raison. Une liste sans raisons se transforme en liste
 * qu'on allonge.
 *
 * @var array<string, string>
 */
const ORPHELINES_CONNUES = [
    /*
     | VIDE, et c'est l'état visé.
     |
     | Neuf orphelines existaient au matin du 2 septembre 2026. Sept se sont
     | refermées le jour même, en faisant adopter la permission par l'écran qui
     | la méritait. Les deux dernières — `signalement.publish` et
     | `signalement.delete` — attendaient un écran qui n'existait pas : la file
     | des vérifications citoyennes. Il a été écrit, elles sont employées.
     |
     | Les 127 permissions déclarées gardent donc toutes quelque chose. Ajouter
     | une ligne ici est possible, mais coûte une raison écrite ET une entrée au
     | journal des lots : c'est le prix voulu.
     */
];

it('n’emploie nulle part exactement les permissions déclarées orphelines', function (): void {
    /*
     | Le balayage porte sur `app/`, `routes/` et `resources/views/`, et cherche
     | la MENTION du nom, quelle que soit sa forme : `'p'`, `"p"`, `can:p`,
     | `@can('p')`. Chercher la seule forme entre apostrophes laisserait passer
     | les gardes de route — c'est l'erreur commise au premier relevé, qui avait
     | signalé douze orphelines là où il y en avait neuf.
     */
    $sources = '';

    foreach (['app', 'routes', 'resources/views'] as $dossier) {
        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($dossier), RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || $fichier->getExtension() !== 'php') {
                continue;
            }

            // La matrice elle-même ne compte pas : elle DÉCLARE, elle n'emploie pas.
            if (str_contains($fichier->getPathname(), 'MatricePermissions')) {
                continue;
            }

            $sources .= file_get_contents($fichier->getPathname())."\n";
        }
    }

    $orphelines = [];

    foreach (MatricePermissions::toutes() as $permission) {
        if (! str_contains($sources, $permission)) {
            $orphelines[] = $permission;
        }
    }

    sort($orphelines);
    $attendues = array_keys(ORPHELINES_CONNUES);
    sort($attendues);

    $nouvelles = array_values(array_diff($orphelines, $attendues));
    $refermees = array_values(array_diff($attendues, $orphelines));

    expect($nouvelles)->toBe([], sprintf(
        "Permissions DÉCLARÉES et employées nulle part :\n  %s\n".
        'Soit un écran les adopte, soit on les retire de la matrice. '.
        "Une permission qu'on ne peut pas retirer à quelqu'un ne protège rien.",
        implode("\n  ", $nouvelles),
    ));

    /*
     | Le cliquet mord dans les DEUX sens : une entrée refermée doit sortir de la
     | liste. Sans cela, la liste se fige à sa taille d'origine et cesse de dire
     | quoi que ce soit — c'est ce qui arrive à toutes les listes d'exemptions
     | qu'on n'oblige pas à maigrir.
     */
    expect($refermees)->toBe([], sprintf(
        "Ces permissions sont désormais employées — retirez-les de ORPHELINES_CONNUES :\n  %s",
        implode("\n  ", $refermees),
    ));
});

it('donne une RAISON à chaque orpheline tolérée', function (): void {
    /*
     | Une liste d'exemptions sans motifs se transforme en liste qu'on allonge :
     | ajouter une ligne ne coûte rien si l'on n'a pas à la justifier.
     |
     | L'assertion porte sur la LISTE DES FAUTIVES et non sur une boucle
     | d'assertions : sur une liste vide — l'état actuel — une boucle ne
     | vérifierait rien et PHPUnit signalerait à juste titre un test sans
     | assertion. Ici, le test dit quelque chose dans les deux cas.
     */
    $sansRaison = array_keys(array_filter(
        ORPHELINES_CONNUES,
        static fn (string $raison): bool => mb_strlen(trim($raison)) <= 20,
    ));

    expect($sansRaison)->toBe([], sprintf(
        "Ces tolérances n'ont pas de raison écrite :
  %s",
        implode('
  ', $sansRaison),
    ));
});

it('ne tolère que des permissions qui EXISTENT', function (): void {
    /*
     | Le piège symétrique : une entrée de la liste qui ne correspond plus à
     | aucune permission déclarée. Elle ne fait rien échouer, ne protège rien, et
     | donne l'illusion que le sujet est suivi.
     */
    $inconnues = array_values(array_diff(array_keys(ORPHELINES_CONNUES), MatricePermissions::toutes()));

    expect($inconnues)->toBe([], sprintf(
        "Ces entrées ne correspondent à aucune permission déclarée :\n  %s",
        implode("\n  ", $inconnues),
    ));
});
