<?php

declare(strict_types=1);

use App\Livewire\Citizen\Outils;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le répertoire d'outils OSINT
|--------------------------------------------------------------------------
|
| ── LE PIÈGE QUE CES TESTS GARDENT FERMÉ ─────────────────────────────────
|
| Les pastilles de filtre portaient une seule chaîne pour deux rôles : le mot
| affiché ET la valeur cherchée en base. Tant que l'interface était française,
| rien ne distinguait les deux.
|
| Traduire l'affichage aurait donc traduit le filtre — et un filtre qui
| interroge `category = 'Search'` dans une colonne remplie de « Recherche » ne
| renvoie rien, sans erreur, sans page blanche. Juste une liste vide, qu'on
| prendrait pour une absence d'outils.
|
| Les deux rôles sont désormais séparés : `Outils::CATEGORIES` garde la valeur
| stockée en clé, et sa valeur n'est plus qu'un suffixe de clé de langue.
| Les deux tests ci-dessous vérifient chacun un versant de cette séparation.
*/

function outil(string $categorie, string $titre): Tool
{
    return Tool::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => Str::slug($titre),
        'title' => $titre,
        'description' => 'Un outil de démonstration.',
        'category' => $categorie,
        'tag' => 'demo',
        'icon' => 'search',
        'url' => 'https://exemple.org',
        'link_label' => 'Ouvrir',
        'use_cases' => [],
        'keywords' => $titre,
        'is_recommended' => false,
        'is_published' => true,
        'sort_order' => 1,
    ]);
}

beforeEach(function (): void {
    $this->citoyen = User::factory()->create();
});

describe('les pastilles de catégorie', function (): void {
    it('affiche un libellé TRADUIT', function (): void {
        outil('Recherche', 'Recherche inversée');

        app()->setLocale('en');

        Livewire::actingAs($this->citoyen)->test(Outils::class)
            ->assertSee('Search')
            ->assertDontSee('Géolocalisation');
    });

    it('filtre sur la valeur STOCKÉE, jamais sur le mot affiché', function (): void {
        /*
         | Le test décisif. En anglais, la pastille dit « Search » alors que la
         | base contient « Recherche ». Si le filtre suivait l'affichage, il
         | chercherait « Search » et ne trouverait rien — une liste vide qui
         | passerait pour un répertoire sans outils.
         */
        outil('Recherche', 'Recherche inversée');
        outil('Archives', 'Machine à remonter le temps');

        app()->setLocale('en');

        $valeurDeFiltre = array_key_first(Outils::CATEGORIES);

        expect($valeurDeFiltre)->toBe('Recherche', 'La clé est la valeur en base : elle ne se traduit pas.');

        Livewire::actingAs($this->citoyen)->test(Outils::class)
            ->call('filtrer', $valeurDeFiltre)
            ->assertSee('Recherche inversée')
            ->assertDontSee('Machine à remonter le temps');
    });

    it('déclare un libellé pour CHAQUE catégorie, dans les deux langues', function (): void {
        // Une clé oubliée ne lève aucune erreur : la pastille afficherait
        // `cit_outils.categorie.medias` en toutes lettres.
        foreach (['fr', 'en'] as $langue) {
            foreach (Outils::CATEGORIES as $valeurStockee => $suffixe) {
                $cle = 'cit_outils.categorie.'.$suffixe;

                expect(__($cle, [], $langue))->not->toBe($cle,
                    "Libellé manquant en {$langue} pour « {$valeurStockee} ».");
            }
        }
    });
});
