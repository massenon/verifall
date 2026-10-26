<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Staff\ArticlesDuBlog;
use App\Models\BlogPost;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
    $this->corps = '<p>'.str_repeat('Un texte de fond suffisamment long. ', 3).'</p>';
    $this->chapeau = str_repeat('Le chapeau de ce billet. ', 2);
});

it('enregistre un brouillon tout seul, puis le met à jour sans le dupliquer', function (): void {
    $editeur = Livewire::actingAs($this->admin, 'staff')->test(ArticlesDuBlog::class)
        ->call('ouvrirCreation')
        ->set('titre', 'Brouillon auto')->set('chapeau', $this->chapeau)->set('corps', $this->corps)
        ->call('autoEnregistrer')
        ->assertSet('formulaireOuvert', true);

    $billet = BlogPost::query()->firstOrFail();
    expect($billet->is_published)->toBeFalse()->and($editeur->get('enEdition'))->toBe($billet->uuid);

    $editeur->set('titre', 'Brouillon auto, corrigé')->call('autoEnregistrer');

    expect(BlogPost::count())->toBe(1)->and($billet->refresh()->title)->toBe('Brouillon auto, corrigé');
});

it('n’enregistre rien tant que le brouillon est vide ou invalide', function (): void {
    Livewire::actingAs($this->admin, 'staff')->test(ArticlesDuBlog::class)
        ->call('ouvrirCreation')->call('autoEnregistrer')
        ->set('titre', 'Titre seul')->call('autoEnregistrer')
        ->assertHasNoErrors();

    expect(BlogPost::count())->toBe(0);
});

it('ne touche JAMAIS à un billet publié sans clic sur Enregistrer', function (): void {
    $editeur = Livewire::actingAs($this->admin, 'staff')->test(ArticlesDuBlog::class)
        ->call('ouvrirCreation')
        ->set('titre', 'En ligne')->set('chapeau', $this->chapeau)->set('corps', $this->corps)
        ->call('enregistrer');

    $billet = BlogPost::query()->firstOrFail();
    $billet->forceFill(['is_published' => true, 'published_at' => now()])->save();

    Livewire::actingAs($this->admin, 'staff')->test(ArticlesDuBlog::class)
        ->call('ouvrirEdition', $billet->uuid)
        ->set('titre', 'Modifié sans clic')
        ->call('autoEnregistrer');

    expect($billet->refresh()->title)->toBe('En ligne');
});

it('montre l’aperçu de recherche avec le repli titre et chapeau', function (): void {
    Livewire::actingAs($this->admin, 'staff')->test(ArticlesDuBlog::class)
        ->call('ouvrirCreation')
        ->set('titre', 'Mon titre')->set('chapeau', 'Mon chapeau')
        ->assertSee('Mon titre')->assertSee('Mon chapeau')->assertSee('Aperçu dans les résultats de recherche');
});
