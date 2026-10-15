<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Phase 12 — étendre aux FIXTURES le garde-fou qui ne portait que sur les prompts
|--------------------------------------------------------------------------
| `PertinenceEtProvenanceTest::'n’inscrit aucun nom de titulaire dans les
| consignes'` existe depuis l'audit d'origine, mais ne scanne QUE
| `resources/prompts/factcheck/*.md` — jamais les fixtures de test elles-
| mêmes. En traçant le contrat V-8 (phase 2), un test de CE dépôt (ajouté
| cette même phase) nommait « Patrice Talon » comme texte de claim — trouvé
| en écrivant CE garde-fou, exactement le genre d'oubli qu'il existe pour
| attraper.
|
| Pourquoi seulement `tests/Feature/{Citizen,Public,Api}` : c'est la
| chaîne de VÉRIFICATION DE CLAIMS, où nommer un titulaire risque de figer
| un fait politique dans un fixture de fact-checking (V-3). `tests/Fixtures/
| ancienne-base.sql` est une donnée d'IMPORT — un extrait réaliste d'export
| CMS pour tester un analyseur de colonnes, pas un claim que la plateforme
| vérifie — et reste hors du champ de cette règle.
|
| Les commentaires PHP sont retirés avant la recherche : une docstring qui
| RACONTE un incident réel pour expliquer pourquoi un code existe n'est pas
| le défaut que cette règle attrape — c'est le contenu qu'un TEST EXÉCUTE
| qui compte.
*/

/** Les mêmes noms que `PertinenceEtProvenanceTest`, une seule liste. */
const NOMS_DE_TITULAIRES = ['Talon', 'Wadagni'];

/**
 * Dette connue, à ne jamais laisser grandir — même discipline que
 * `ClassesTailwindPresentesTest::detteConnueCitoyen()`. Un fichier qui en
 * sort ne doit pas y revenir.
 *
 * @var list<string>
 */
const FICHIERS_TOLERES = [
    'tests/Feature/Citizen/PertinenceEtProvenanceTest.php',
];

function sansCommentairesPhp(string $code): string
{
    $tokens = token_get_all($code);
    $sans = '';

    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $sans .= is_array($token) ? $token[1] : $token;
    }

    return $sans;
}

it('ne laisse AUCUN nouveau fichier nommer un titulaire de fonction publique', function (): void {
    $fichiers = array_merge(
        glob(base_path('tests/Feature/Citizen/*.php')) ?: [],
        glob(base_path('tests/Feature/Public/*.php')) ?: [],
        glob(base_path('tests/Feature/Api/*.php')) ?: [],
    );

    expect($fichiers)->not->toBeEmpty();

    $motif = '/\b('.implode('|', NOMS_DE_TITULAIRES).')\b/u';
    $fautifs = [];

    $base = str_replace('\\', '/', base_path()).'/';

    foreach ($fichiers as $chemin) {
        $relatif = str_replace($base, '', str_replace('\\', '/', $chemin));
        $code = sansCommentairesPhp((string) file_get_contents($chemin));

        if (preg_match($motif, $code) === 1 && ! in_array($relatif, FICHIERS_TOLERES, true)) {
            $fautifs[] = $relatif;
        }
    }

    expect($fautifs)->toBe(
        [],
        'Un nom de titulaire figure dans un fixture : '.implode(', ', $fautifs)
        .'. Un claim de test se vérifie par son COMPORTEMENT (recouvrement, négation, date), '
        .'jamais par une personne réelle qui peut quitter sa fonction.',
    );
});

it('la liste tolérée ne grandit jamais — elle ne fait que rétrécir', function (): void {
    expect(FICHIERS_TOLERES)->toHaveCount(1,
        'Un nouveau fichier est entré dans la tolérance : corrigez le fixture plutôt que de l’y ajouter.',
    );
});
