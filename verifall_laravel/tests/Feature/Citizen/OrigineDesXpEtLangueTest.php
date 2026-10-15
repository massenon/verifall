<?php

declare(strict_types=1);

use App\Core\Gamification\Services\JournalDXp;
use App\Enums\MotifGainXp;
use App\Enums\UserRole;
use App\Livewire\Citizen\Catalogue;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Ce que les écrans font des deux manques du module Académie
|--------------------------------------------------------------------------
|
| Deux chaînes stockées que rien ne lisait :
|
| - le **journal d'XP**, qui n'existait pas du tout ;
| - `courses.language`, saisie par le mentor depuis le lot 14 et qui n'agissait
|   nulle part — on demandait de renseigner un champ inerte.
|
| Ces tests passent par la route HTTP et par le composant, pas par les services :
| c'est l'affichage qui manquait, et un test de service ne le voit pas.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['xp' => 0])->save();
});

/** Un cours publié minimal, dans la langue demandée. */
function coursPublie(string $titre, string $langue = 'fr'): Course
{
    return Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => Str::slug($titre),
        'title' => $titre,
        'description' => 'Un cours de test suffisamment décrit pour passer la validation.',
        'category' => 'OSINT',
        'level' => 'beginner',
        'language' => $langue,
        'duration_label' => '1 h',
        'cost_units' => 0,
        'is_published' => true,
    ]);
}

// ─────────────────────────────────────────────── d'où viennent les XP

it('montre d’où viennent les XP, motif par motif', function () {
    $journal = app(JournalDXp::class);
    $journal->crediter($this->citoyen, 250, MotifGainXp::Cours);
    $journal->crediter($this->citoyen, 50, MotifGainXp::Quiz);

    $this->actingAs($this->citoyen)
        ->get(route('citizen.rewards'))
        ->assertOk()
        ->assertSee('D’où viennent vos XP')
        ->assertSee('Cours terminé')
        ->assertSee('Quiz réussi');
});

it('avoue les points antérieurs au journal plutôt que de les taire', function () {
    /*
     | Le journal date du 2 septembre 2026. Un compte plus ancien affiche un
     | total qu'aucune ligne n'explique : sans cet aveu, quelqu'un qui a 1 780 XP
     | verrait une répartition qui en totalise 250 et conclurait à une perte.
     */
    $this->citoyen->forceFill(['xp' => 1000])->save();
    app(JournalDXp::class)->crediter($this->citoyen, 250, MotifGainXp::Cours);

    $this->actingAs($this->citoyen)
        ->get(route('citizen.rewards'))
        ->assertOk()
        ->assertSee('Points antérieurs au journal')
        // 1 250 au compteur, 250 au journal : l'écart affiché est 1 000.
        ->assertSee('+1 000 XP');
});

it('n’affiche pas le panneau quand il n’y a rien à expliquer', function () {
    // Un compte neuf, à zéro : un panneau vide vaut moins que pas de panneau.
    $this->actingAs($this->citoyen)
        ->get(route('citizen.rewards'))
        ->assertOk()
        ->assertDontSee('D’où viennent vos XP');
});

// ─────────────────────────────────────────────── le filtre de langue

it('cache le filtre de langue tant qu’il n’y a qu’une langue', function () {
    // Un menu déroulant dont tous les choix mènent au même résultat occupe une
    // place, invite à un clic, et ne change rien.
    coursPublie('Détecter un deepfake');
    coursPublie('Vérifier une image');

    Livewire::actingAs($this->citoyen)
        ->test(Catalogue::class)
        ->assertDontSee('Toutes les langues');
});

it('fait apparaître le filtre dès qu’une seconde langue est publiée', function () {
    coursPublie('Détecter un deepfake');
    coursPublie('Spotting a deepfake', 'en');

    Livewire::actingAs($this->citoyen)
        ->test(Catalogue::class)
        ->assertSee('Toutes les langues')
        ->assertSee('Anglais');
});

it('filtre réellement le catalogue sur la langue', function () {
    coursPublie('Détecter un deepfake');
    coursPublie('Spotting a deepfake', 'en');

    Livewire::actingAs($this->citoyen)
        ->test(Catalogue::class)
        ->set('filtreLangue', 'en')
        ->assertSee('Spotting a deepfake')
        ->assertDontSee('Détecter un deepfake');
});

it('porte la langue dans l’adresse, pour qu’un filtre se partage', function () {
    // Même contrat que les trois filtres voisins : l'état vit dans l'URL.
    coursPublie('Détecter un deepfake');
    coursPublie('Spotting a deepfake', 'en');

    Livewire::actingAs($this->citoyen)
        ->withQueryParams(['langue' => 'en'])
        ->test(Catalogue::class)
        ->assertSet('filtreLangue', 'en')
        ->assertDontSee('Détecter un deepfake');
});
