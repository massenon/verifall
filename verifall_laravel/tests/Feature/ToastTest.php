<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| Toasts — variante par espace
|--------------------------------------------------------------------------
| La POSITION du toast diffère selon l'espace, par arbitrage : haut-droite
| côté citoyen, bas-droite côté staff, comme le prototype. Elle est portée
| par la CSS via `data-toast-variant` — si l'attribut se trompe d'espace, le
| toast s'affiche au mauvais coin sans qu'aucune erreur ne le signale.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('monte l\'hôte des toasts une seule fois par page', function (): void {
    $html = $this->get(route('public.home'))->content();

    // Deux hôtes = deux fois le même id, et le script n'en pilote qu'un.
    expect(substr_count($html, 'id="vf-toast-host"'))->toBe(1);
});

it('déclare la variante citoyenne sur les espaces citoyen et public', function (string $route): void {
    $citoyen = User::factory()->create(['otp_verified_at' => now()]);
    $citoyen->assignRole(UserRole::Citoyen->value);

    $this->actingAs($citoyen)->get(route($route))
        ->assertSee('data-toast-variant="citizen"', escape: false);
})->with(['public.home', 'citizen.dashboard']);

it('déclare la variante citoyenne sur la page de connexion', function (): void {
    // En VISITEUR, et non connecté : depuis que `guest` protège cet écran, un
    // compte déjà authentifié est renvoyé vers son espace. C'était le but —
    // servir le formulaire de connexion à quelqu'un de connecté lui faisait
    // croire qu'il était sorti.
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-toast-variant="citizen"', escape: false);
});

it('déclare la variante staff sur le back-office', function (): void {
    $admin = User::factory()->create(['otp_verified_at' => now()]);
    $admin->assignRole(UserRole::Admin->value);

    $this->actingAs($admin, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp])
        ->get(route('staff.dashboard'))
        ->assertSee('data-toast-variant="staff"', escape: false);
});

it('positionne chaque variante au coin du prototype', function (): void {
    $css = file_get_contents(public_path('assets/css/verifall-app.css'));

    // Citoyen en HAUT à droite (.vf-toast du prototype), staff en BAS à droite
    // (.st-toast). Aucune règle de position ne doit rester sur l'hôte nu, qui
    // s'appliquerait alors aux deux.
    expect($css)->toContain('.vf-toast-host[data-toast-variant="citizen"]')
        ->and($css)->toContain('.vf-toast-host[data-toast-variant="staff"]');

    $hoteNu = substr($css, strpos($css, '.vf-toast-host {'));
    $hoteNu = substr($hoteNu, 0, strpos($hoteNu, '}'));

    expect($hoteNu)->not->toContain('top:')
        ->and($hoteNu)->not->toContain('bottom:');
});
