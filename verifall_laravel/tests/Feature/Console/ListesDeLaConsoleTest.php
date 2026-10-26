<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Staff\AlertesIntrusion;
use App\Livewire\Staff\GabaritsEmails;
use App\Livewire\Staff\SauvegardesBaseDeDonnees;
use App\Livewire\Staff\TachesCron;
use App\Models\User;
use Database\Seeders\GabaritsDEmailSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(GabaritsDEmailSeeder::class);
    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

it('pagine les modèles de courriel et annonce le total', function (): void {
    $page = Livewire::actingAs($this->admin, 'staff')->test(GabaritsEmails::class);

    expect($page->viewData('gabarits')->perPage())->toBe(10)
        ->and($page->viewData('gabarits')->total())->toBeGreaterThan(10);

    $page->assertSee('Lignes 1 à 10 sur')->set('parPage', 50)->assertSee('Lignes 1 à');
});

it('cherche dans le libellé, le déclencheur, le sujet et les variables', function (): void {
    $page = Livewire::actingAs($this->admin, 'staff')->test(GabaritsEmails::class)->set('recherche', 'mot de passe');

    expect($page->viewData('gabarits')->total())->toBeGreaterThan(0)->toBeLessThan(10);

    Livewire::actingAs($this->admin, 'staff')->test(GabaritsEmails::class)->set('recherche', 'zzzz-introuvable')
        ->assertSee('Aucun modèle ne correspond');
});

it('filtre par état, par texte modifié et par variable', function (): void {
    $verrouilles = Livewire::actingAs($this->admin, 'staff')->test(GabaritsEmails::class)->set('filtreEtat', 'indispensable')->viewData('gabarits');
    expect($verrouilles->total())->toBeGreaterThan(0);

    foreach ($verrouilles->items() as $ligne) {
        expect($ligne['verrouille'])->toBeTrue();
    }

    $modifies = Livewire::actingAs($this->admin, 'staff')->test(GabaritsEmails::class)->set('filtreTexte', 'modifie')->viewData('gabarits');
    expect($modifies->total())->toBe(0);

    $avecCode = Livewire::actingAs($this->admin, 'staff')->test(GabaritsEmails::class)->set('filtreVariable', '{{ code }}')->viewData('gabarits');
    expect($avecCode->total())->toBeGreaterThan(0);
});

it('trie, ignore une colonne inconnue et se remet à zéro', function (): void {
    $page = Livewire::actingAs($this->admin, 'staff')->test(GabaritsEmails::class)
        ->call('trierPar', 'sujet')->assertSet('tri', 'sujet')->assertSet('sens', 'asc')
        ->call('trierPar', 'sujet')->assertSet('sens', 'desc')
        ->call('trierPar', 'password; DROP TABLE users')->assertSet('tri', 'sujet');

    $page->set('recherche', 'x')->set('filtreEtat', 'actif')->call('reinitialiserLaListe')
        ->assertSet('recherche', '')->assertSet('filtreEtat', '')->assertSet('tri', 'libelle')->assertSet('parPage', 10);
});

it('borne le nombre de lignes à ce que la barre propose', function (): void {
    Livewire::actingAs($this->admin, 'staff')->test(GabaritsEmails::class)->set('parPage', 100000)
        ->tap(fn ($p) => expect($p->viewData('gabarits')->perPage())->toBe(10));
});

it('donne la barre minimale aux listes construites en mémoire', function (): void {
    foreach ([TachesCron::class, SauvegardesBaseDeDonnees::class] as $ecran) {
        $page = Livewire::actingAs($this->admin, 'staff')->test($ecran)->assertSee('Lignes par page', false)->assertSee('Réinitialiser');
        expect($page->viewData('page')->perPage())->toBe(10);

        $page->set('parPage', 99999)->tap(fn ($p) => expect($p->viewData('page')->perPage())->toBe(10));
    }
});

it('cherche parmi les alertes et refuse une taille de page forgée', function (): void {
    Livewire::actingAs($this->admin, 'staff')->test(AlertesIntrusion::class)
        ->set('recherche', 'login')->set('parPage', 1000000)
        ->tap(fn ($p) => expect($p->viewData('alertes')->perPage())->toBe(10));
});
