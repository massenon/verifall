<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\LiveClass;
use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
    $this->mentor = User::factory()->actif()->create();
    $this->mentor->assignRole(UserRole::Mentor->value);
    $this->autre = User::factory()->actif()->create();
    $this->autre->assignRole(UserRole::Mentor->value);

    foreach ([['Live de Awa', $this->mentor], ['Live de Koffi', $this->autre]] as [$titre, $hote]) {
        LiveClass::query()->forceCreate(['uuid' => (string) Str::uuid(), 'title' => $titre, 'host_id' => $hote->id, 'starts_at' => now()->addDays(2), 'minutes' => 45]);
    }
    $this->fenetre = ['start' => now()->toDateString(), 'end' => now()->addDays(10)->toDateString()];
});

it('est fermé au public et aux comptes sans droit de lister les lives', function (): void {
    $this->getJson(route('staff.calendar.data', $this->fenetre))->assertUnauthorized();

    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);
    $this->actingAs($moderateur, 'staff')->getJson(route('staff.calendar.data', $this->fenetre))->assertForbidden();
});

it('montre à l’administrateur tous les lives', function (): void {
    $this->actingAs($this->admin, 'staff')->getJson(route('staff.calendar.data', $this->fenetre))
        ->assertOk()->assertJsonCount(2)->assertJsonFragment(['title' => 'Live de Koffi']);
});

it('ne montre à un mentor que ses lives', function (): void {
    $this->actingAs($this->mentor, 'staff')->getJson(route('staff.calendar.data', $this->fenetre))
        ->assertOk()->assertJsonCount(1)->assertJsonFragment(['title' => 'Live de Awa'])
        ->assertJsonMissing(['title' => 'Live de Koffi']);
});

it('refuse une fenêtre à l’envers et borne la durée', function (): void {
    $this->actingAs($this->admin, 'staff')->getJson(route('staff.calendar.data', ['start' => '2026-12-31', 'end' => '2026-01-01']))->assertStatus(422);
    $this->actingAs($this->admin, 'staff')->get(route('staff.calendar'))->assertOk()->assertSee('Calendrier');
});

it('place le calendrier dans le menu de qui peut le voir', function (): void {
    expect(array_column(MenuDuBackOffice::pour($this->mentor)[''], 'route'))->toContain('staff.calendar')
        ->and(array_column(MenuDuBackOffice::pour($this->admin)[''], 'route'))->toContain('staff.calendar');
});
