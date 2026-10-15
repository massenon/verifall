<?php

declare(strict_types=1);

use App\Models\Tool;
use App\Models\TrustedSource;
use Database\Seeders\RedactionsDeFactCheckSeeder;
use Database\Seeders\SourcesVeraSeeder;

/*
|--------------------------------------------------------------------------
| Seize rédactions de fact-checking, offertes comme des OUTILS
|--------------------------------------------------------------------------
|
| `trusted_sources` porte 408 fiches : c'est ce dont le MOTEUR se sert pour
| créditer un domaine. `tools` est autre chose — le répertoire de ce qu'une
| personne ouvre pour vérifier elle-même. Y verser les 386 aurait noyé les
| quinze outils réels sous une liste de sites sans description ni cas d'usage.
|
| D'où une curation. Ce qui est éprouvé ici, ce sont ses trois règles.
|
*/

beforeEach(function (): void {
    $this->seed(SourcesVeraSeeder::class);
});

it('pose seize rédactions, et pas trois cent quatre-vingt-six', function (): void {
    $avant = Tool::query()->count();

    $this->seed(RedactionsDeFactCheckSeeder::class);

    expect(Tool::query()->count() - $avant)->toBe(16);
});

it('n’offre que des rédactions que le moteur crédite déjà', function (): void {
    $this->seed(RedactionsDeFactCheckSeeder::class);

    $curees = Tool::query()
        ->where('category', 'Fact-checking')
        ->whereNotNull('tag')
        ->get();

    /*
     | La règle qui compte : ce que nous recommandons de consulter et ce que le
     | moteur crédite sont la MÊME liste. Sans ce test, les deux dérivent — on
     | retire une source du référentiel, l'outil survit et continue d'envoyer
     | des citoyens vers une rédaction que la plateforme ne reconnaît plus.
     */
    foreach ($curees as $outil) {
        $domaine = (string) parse_url($outil->url, PHP_URL_HOST);

        expect(TrustedSource::query()->where('domain', $domaine)->exists())
            ->toBeTrue("« {$outil->title} » ({$domaine}) n’est pas au référentiel des sources.");
    }
    // Et il faut qu'il y en ait : une boucle sur une liste vide passe toujours.
    expect($curees)->toHaveCount(16);
});

it('refuse de poser une rédaction absente du référentiel', function (): void {
    // Le seeder LÈVE plutôt que de créer un outil qui pointerait dans le vide.
    TrustedSource::query()->where('domain', 'africacheck.org')->delete();

    expect(fn () => $this->seed(RedactionsDeFactCheckSeeder::class))
        ->toThrow(RuntimeException::class);
});

it('ouvre sur du fact-checking, jamais sur une page d’accueil de journal', function (): void {
    $this->seed(RedactionsDeFactCheckSeeder::class);

    /*
     | Deuxième règle de la curation. `lemonde.fr` ou `lanation.bj` mèneraient
     | à la une du jour, pas aux Décodeurs ni à leur rubrique de vérification :
     | ces rédactions ont été écartées, malgré leur qualité. Un outil qui
     | n'ouvre pas là où il promet est pire qu'un outil absent.
     */
    $generalistes = ['lemonde.fr', 'liberation.fr', 'lefigaro.fr', 'francetvinfo.fr',
        'tf1info.fr', '20minutes.fr', 'lanation.bj', 'ouestaf.com', 'benbere.org'];

    $poses = Tool::query()->pluck('url')->map(
        static fn (string $url): string => (string) parse_url($url, PHP_URL_HOST),
    );

    foreach ($generalistes as $domaine) {
        expect($poses)->not->toContain($domaine);
    }
});

it('met le Bénin et l’Afrique de l’Ouest en tête', function (): void {
    $this->seed(RedactionsDeFactCheckSeeder::class);

    // La proximité d'abord : une rumeur qui circule à Cotonou a plus de chances
    // d'avoir déjà été vérifiée à Cotonou, Abidjan ou Yaoundé qu'à Washington.
    $premiere = Tool::query()->where('category', 'Fact-checking')
        ->whereNotNull('tag')->orderBy('sort_order')->first();

    expect($premiere?->tag)->toBe('BÉNIN');
});

it('ne réécrit pas une fiche que la rédaction a amendée', function (): void {
    $this->seed(RedactionsDeFactCheckSeeder::class);

    $outil = Tool::query()->where('slug', 'africa-check')->firstOrFail();
    $outil->update(['description' => 'Description réécrite à la main.', 'is_published' => false]);

    $this->seed(RedactionsDeFactCheckSeeder::class);

    // Même règle que pour les sources : un semis ne remet rien à la valeur
    // d'usine, sans quoi le travail de la rédaction disparaît en silence.
    $apres = Tool::query()->where('slug', 'africa-check')->firstOrFail();

    expect($apres->description)->toBe('Description réécrite à la main.')
        ->and($apres->is_published)->toBeFalse();
});
