<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| Visite guidée — ancrage et déclenchement
|--------------------------------------------------------------------------
| Ces tests portent sur le CONTRAT entre le shell et guided-tour.js : le JS
| vise des sélecteurs `[data-tour="…"]`, posés par build_layouts.py sur des
| repères du prototype. Rien ne signale la rupture de ce contrat — la visite
| se contente alors de ne rien éclairer. C'est exactement ce qui s'était
| produit : le script existait, les ancres non.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** @return list<string> */
function ancres(string $html): array
{
    preg_match_all('/data-tour="([a-z-]+)"/', $html, $trouvees);

    return $trouvees[1];
}

it('porte les six ancres citoyennes attendues par le script', function (): void {
    $citoyen = User::factory()->create(['otp_verified_at' => now()]);
    $citoyen->assignRole(UserRole::Citoyen->value);

    $html = $this->actingAs($citoyen)->get(route('citizen.dashboard'))->content();

    expect(ancres($html))->toEqualCanonicalizing([
        'verifier', 'unites', 'academie', 'forum', 'statistiques', 'profil',
        // Bascule du menu : deux étapes n'y sont visibles qu'une fois déployé.
        'menu-utilisateur',
    ]);
});

it('porte les ancres staff disponibles dans le shell', function (): void {
    $admin = User::factory()->create(['otp_verified_at' => now()]);
    $admin->assignRole(UserRole::Admin->value);

    $html = $this->actingAs($admin, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp])
        ->get(route('staff.dashboard'))->content();

    // « a-traiter » n'appartient pas au shell mais au tableau de bord : il est
    // présent ici parce que cette page EST le tableau de bord. Le bloc « À
    // traiter maintenant » porte l'ancre depuis la refonte du 14 septembre 2026 ;
    // l'étape de la visite, filtrée jusque-là, se joue désormais.
    expect(ancres($html))->toEqualCanonicalizing([
        'sidebar', 'creer', 'notifications', 'audit', 'a-traiter',
    ]);
});

it('ne pose les ancres que sur les espaces authentifiés', function (): void {
    // Le site public n'a pas de visite : l'y déclarer chargerait un script et
    // des attributs pour rien.
    expect(ancres($this->get(route('public.home'))->content()))->toBeEmpty();
});

describe('déclenchement', function (): void {
    it('propose la visite tant qu\'elle n\'a pas été faite', function (): void {
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        $citoyen->assignRole(UserRole::Citoyen->value);

        $this->actingAs($citoyen)->get(route('citizen.dashboard'))
            ->assertSee('data-tour-auto="citizen"', escape: false);
    });

    it('ne la propose plus une fois terminée', function (): void {
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        $citoyen->assignRole(UserRole::Citoyen->value);
        $citoyen->forceFill(['tour_done_at' => now()])->save();

        $this->actingAs($citoyen)->get(route('citizen.dashboard'))
            ->assertDontSee('data-tour-auto', escape: false);
    });

    it('interroge le guard staff, pas le guard par défaut', function (): void {
        $admin = User::factory()->create(['otp_verified_at' => now()]);
        $admin->assignRole(UserRole::Admin->value);

        // auth() renverrait null ici : l'administrateur n'est connecté que sur
        // `staff`. Sans garde explicite, la visite ne se déclencherait jamais
        // dans le back-office.
        $this->actingAs($admin, 'staff')
            ->withSession(['staff.last_activity' => now()->timestamp])
            ->get(route('staff.dashboard'))
            ->assertSee('data-tour-auto="staff"', escape: false);
    });

    it('ne propose rien à un visiteur anonyme', function (): void {
        $this->get(route('public.home'))->assertDontSee('data-tour-auto', escape: false);
    });
});

it('écoute la navigation Livewire, seul déclencheur après connexion', function (): void {
    // La connexion redirige avec `navigate: true` : le <body> est échangé sans
    // rechargement, donc SANS DOMContentLoaded. S'en remettre à ce seul
    // événement privait la visite du moment même où elle existe.
    $script = file_get_contents(public_path('assets/js/guided-tour.js'));

    expect($script)->toContain("addEventListener('livewire:navigated'")
        ->and($script)->toContain("addEventListener('DOMContentLoaded'");
});
