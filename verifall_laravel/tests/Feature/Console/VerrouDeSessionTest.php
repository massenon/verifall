<?php

declare(strict_types=1);

use App\Core\Identity\Services\VerrouDeSession;
use App\Enums\UserRole;
use App\Livewire\Auth\VerrouDeSession as Ecran;
use App\Models\HistoriqueConnexion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
| Arbitrage n° 180 — le verrouillage de session de la console est SERVEUR.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->membre = User::factory()->actif()->create(['password' => Hash::make('mot-de-passe-solide'), 'tour_done_at' => now()]);
    $this->membre->assignRole(UserRole::Admin->value);
});

function verrouiller(): TestResponse
{
    return test()->actingAs(test()->membre, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp])
        ->from(route('staff.users'))
        ->post(route('staff.lock'));
}

it('déconnecte la garde staff et mène à l\'écran de verrouillage', function (): void {
    verrouiller()->assertRedirect(route('staff.verrou'));

    expect(Auth::guard('staff')->check())->toBeFalse()
        ->and(session(VerrouDeSession::CLE)['user_id'])->toBe($this->membre->id);
});

it('renvoie toute page de la console vers le verrou, sans rien en servir', function (): void {
    verrouiller();

    $this->get(route('staff.users'))->assertRedirect(route('staff.verrou'));
    $this->get(route('staff.verrou'))->assertOk()->assertSee('Session verrouillée')->assertDontSee('Utilisateurs');
});

it('rend la console avec le bon mot de passe et revient à la page quittée', function (): void {
    verrouiller();

    Livewire::test(Ecran::class)
        ->set('motDePasse', 'mot-de-passe-solide')
        ->call('deverrouiller')
        ->assertRedirect(route('staff.users'));

    expect(Auth::guard('staff')->id())->toBe($this->membre->id)
        ->and(session()->has(VerrouDeSession::CLE))->toBeFalse()
        ->and(HistoriqueConnexion::query()->where('methode', 'deverrouillage')->count())->toBe(1);
});

it('refuse un mauvais mot de passe et ferme la session au cinquième', function (): void {
    verrouiller();

    $ecran = Livewire::test(Ecran::class);
    foreach (range(1, 4) as $essai) {
        $ecran->set('motDePasse', 'faux')->call('deverrouiller')->assertNoRedirect()->assertSet('erreur', fn ($e) => str_contains((string) $e, 'incorrect'));
    }
    $ecran->set('motDePasse', 'faux')->call('deverrouiller')->assertRedirect(route('staff.login'));

    expect(session()->has(VerrouDeSession::CLE))->toBeFalse()
        ->and(Auth::guard('staff')->check())->toBeFalse();
});

it('exige une connexion complète au-delà de 30 minutes de verrou', function (): void {
    verrouiller();
    $etat = session(VerrouDeSession::CLE);
    $etat['depuis'] = now()->subMinutes(31)->timestamp;
    session()->put(VerrouDeSession::CLE, $etat);

    $this->get(route('staff.verrou'))->assertRedirect(route('staff.login'));
});

it('n\'ouvre pas l\'écran de verrouillage sans session verrouillée', function (): void {
    $this->get(route('staff.verrou'))->assertRedirect(route('staff.login'));
});

it('n\'accepte le verrouillage qu\'en POST', function (): void {
    $this->actingAs($this->membre, 'staff')->get('/admin/verrouiller')->assertStatus(405);
});
