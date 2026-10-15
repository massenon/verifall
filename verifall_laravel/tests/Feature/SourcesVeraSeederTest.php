<?php

declare(strict_types=1);

use App\Models\SourceCategory;
use App\Models\TrustedSource;
use Database\Seeders\SourcesFiablesSeeder;
use Database\Seeders\SourcesVeraSeeder;

/*
|--------------------------------------------------------------------------
| Le référentiel élargi ne produit pas de doublon, et n'écrase rien
|--------------------------------------------------------------------------
|
| 395 sources reprises d'un répertoire tiers, dont neuf figuraient déjà dans le
| référentiel de départ. Deux choses pouvaient mal tourner, et aucune ne se
| serait vue à l'œil :
|
|   1. **Un doublon.** La source liste elle-même certaines rédactions deux fois
|      (BOOM, Factly, Taiwan FactCheck Center), et la capture a été relevée deux
|      fois. L'index unique de `trusted_sources.domain` refuserait un doublon
|      exact — mais il ne dit rien de deux fiches pour `www.x.org` et `x.org`,
|      que seule la NORMALISATION rapproche.
|
|   2. **Un écrasement.** Les crédibilités du référentiel de départ sont censées
|      être amendées depuis l'écran. Un `updateOrCreate` les remettrait à la
|      valeur d'usine à chaque semis, en silence.
|
| Ces tests tiennent les deux.
|
*/

it('ne pose aucun doublon, même semé deux fois', function (): void {
    $this->seed(SourcesVeraSeeder::class);
    $apresLePremier = TrustedSource::query()->count();

    $this->seed(SourcesVeraSeeder::class);

    expect(TrustedSource::query()->count())->toBe($apresLePremier);

    $domaines = TrustedSource::query()->pluck('domain');
    expect($domaines->count())->toBe($domaines->unique()->count());
});

it('normalise chaque domaine, pour qu\'aucune fiche ne double une autre', function (): void {
    $this->seed(SourcesVeraSeeder::class);

    TrustedSource::query()->pluck('domain')->each(function (string $domaine): void {
        expect($domaine)
            ->toBe(TrustedSource::normaliserLeDomaine($domaine))
            ->not->toStartWith('www.');
    });
});

it('laisse intacte une fiche que la rédaction a amendée', function (): void {
    // `africacheck.org` figure dans les DEUX listes : c'est le cas qui compte.
    $this->seed(SourcesFiablesSeeder::class);

    $avant = TrustedSource::query()->where('domain', 'africacheck.org')->firstOrFail();
    $avant->update(['credibility' => 61, 'name' => 'Africa Check — noté à la main']);

    $this->seed(SourcesVeraSeeder::class);

    $apres = TrustedSource::query()->where('domain', 'africacheck.org')->firstOrFail();

    expect($apres->credibility)->toBe(61)
        ->and($apres->name)->toBe('Africa Check — noté à la main')
        ->and($apres->id)->toBe($avant->id);
});

it('range chaque source dans une famille qui existe', function (): void {
    $this->seed(SourcesVeraSeeder::class);

    expect(TrustedSource::query()->whereNull('source_category_id')->count())->toBe(0);

    foreach (['publications-de-reference', 'sources-expertes'] as $slug) {
        expect(SourceCategory::query()->where('slug', $slug)->exists())->toBeTrue();
    }
});

it('n\'invente aucune note de crédibilité : chaque fiche porte celle de sa famille', function (): void {
    $this->seed(SourcesVeraSeeder::class);

    $defauts = SourceCategory::query()->pluck('default_credibility', 'id');

    TrustedSource::query()
        // Le référentiel de départ, lui, porte des notes individuelles : il
        // n'est pas concerné. On ne regarde que ce que CE seeder a posé.
        ->where('notes', 'like', 'Répertoire public de Vera%')
        ->get()
        ->each(function (TrustedSource $source) use ($defauts): void {
            expect($source->credibility)->toBe($defauts[$source->source_category_id]);
        });
});

it('ne pose aucun domaine dont le TLD trahit une troncature', function (): void {
    $this->seed(SourcesVeraSeeder::class);

    // La source coupe ses URL dans une colonne à largeur fixe. `.or` et `.con`
    // passent une validation naïve et donneraient une fiche au domaine FAUX —
    // qui crédite alors un domaine qui n'est pas celui qu'on croit.
    $tronques = ['or', 'cor', 'con', 'go', 'develo', 'develc', 'subs', 'africaunc'];

    TrustedSource::query()->pluck('domain')->each(function (string $domaine) use ($tronques): void {
        $tld = mb_strtolower((string) mb_strrchr($domaine, '.'));
        expect($tronques)->not->toContain(ltrim($tld, '.'));
    });
});
