<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Policies des journaux
|--------------------------------------------------------------------------
| Le middleware `permission:` sait dire qui entre. Il ne sait pas dire ce que
| PERSONNE ne peut faire — c'est le rôle de ces Policies, et la raison pour
| laquelle elles existent alors que les pages sont en lecture seule.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

it('interdit à quiconque de modifier ou supprimer une trace d\'audit', function (string $action): void {
    $trace = AuditLog::create([
        'action' => 'login', 'module' => 'auth', 'actor_label' => 'René',
    ]);

    // Y compris à l'administrateur, qui détient pourtant les 106 permissions :
    // un journal réécrivable ne prouve rien. Aucun Gate::before n'existe dans
    // l'application qui pourrait passer par-dessus.
    expect(Gate::forUser($this->admin)->allows($action, $trace))->toBeFalse();
})->with(['update', 'delete']);

it('interdit de fabriquer une trace à la main', function (): void {
    // Les traces naissent d'actions réelles, jamais d'une saisie.
    expect(Gate::forUser($this->admin)->allows('create', AuditLog::class))->toBeFalse();
});

it('applique la même règle au journal de sécurité', function (string $action): void {
    $evenement = SecurityLog::create(['event' => 'login_failed', 'severity' => 'info']);

    expect(Gate::forUser($this->admin)->allows($action, $evenement))->toBeFalse();
})->with(['update', 'delete']);

it('sépare la lecture de l\'audit de celle des alertes', function (): void {
    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);

    // Surveiller les intrusions et relire les actions d'équipe sont deux
    // besoins distincts, qui peuvent être confiés à deux personnes.
    expect(Gate::forUser($this->admin)->allows('viewAny', AuditLog::class))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('viewAny', SecurityLog::class))->toBeTrue()
        ->and(Gate::forUser($moderateur)->allows('viewAny', AuditLog::class))->toBeFalse()
        ->and(Gate::forUser($moderateur)->allows('viewAny', SecurityLog::class))->toBeFalse();
});

it('résout la Policy avec le guard staff, pas le guard par défaut', function (): void {
    // Sans GuardStaffParDefaut, Gate interroge le guard `web` — vide dans le
    // back-office — et refuse un administrateur pourtant connecté.
    $this->actingAs($this->admin, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp])
        ->get(route('staff.audit'))
        ->assertOk();
});
