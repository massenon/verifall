<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| Déconnexion
|--------------------------------------------------------------------------
| Le bouton « Déconnexion » des deux shells pointait vers la PAGE de connexion
| par un simple lien GET. On atterrissait sur /connexion, la barre latérale
| disparaissait, tout disait qu'on était sorti — et la session n'avait jamais
| été détruite. Sur un poste partagé, le suivant récupérait le compte avec le
| bouton Précédent.
|
| Ces tests-là auraient dû exister avant l'écran.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

it('détruit réellement la session', function (): void {
    $this->actingAs($this->citoyen)
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();

    // Le vrai test : la page privée ne doit plus s'ouvrir.
    $this->get(route('citizen.units'))->assertRedirect(route('login'));
});

it('refuse un GET sur la déconnexion', function (): void {
    // POST exclusivement : un GET de déconnexion se déclencherait depuis une
    // balise <img> tierce ou un préchargeur de navigateur.
    $this->actingAs($this->citoyen)
        ->get('/deconnexion')
        ->assertStatus(405);

    $this->assertAuthenticated();
});

it('expose un formulaire de déconnexion, jamais un lien', function (): void {
    $html = $this->actingAs($this->citoyen)
        ->get(route('citizen.dashboard'))
        ->assertOk()
        ->content();

    expect($html)->toContain('action="'.route('logout').'"')
        // Un lien vers la page de connexion depuis un espace privé donnerait
        // l'illusion d'une déconnexion.
        ->and($html)->not->toContain('href="'.route('login').'"');
});

it('ne laisse pas un compte connecté rouvrir la page de connexion', function (): void {
    // Sinon l'écran de connexion s'affiche par-dessus une session vivante, et
    // l'on croit être sorti alors qu'on ne l'est pas.
    $this->actingAs($this->citoyen)
        ->get(route('login'))
        ->assertRedirect(route('citizen.dashboard'));
});
