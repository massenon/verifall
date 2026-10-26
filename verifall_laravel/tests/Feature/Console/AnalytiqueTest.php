<?php

declare(strict_types=1);

use App\Enums\StatutInscription;
use App\Enums\StatutPaiement;
use App\Enums\UserRole;
use App\Livewire\Staff\Analytique;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    Cache::flush();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

it('est réservé à l’administrateur', function (): void {
    $mentor = User::factory()->actif()->create();
    $mentor->assignRole(UserRole::Mentor->value);

    $this->get(route('staff.analytics'))->assertRedirect();
    $this->actingAs($mentor, 'staff')->get(route('staff.analytics'))->assertForbidden();
    $this->actingAs($this->admin, 'staff')->get(route('staff.analytics'))->assertOk()->assertSee('Entonnoir');
});

it('mesure l’entonnoir sans rien inventer quand la base est vide', function (): void {
    Livewire::actingAs($this->admin, 'staff')->test(Analytique::class)
        ->assertSee('Aucune inscription')->assertDontSee('NaN');
});

it('compte les inscriptions, les terminés et la complétion par cours', function (): void {
    $cours = Course::query()->forceCreate(['uuid' => (string) Str::uuid(), 'slug' => 'cours-a', 'title' => 'Cours Alpha', 'category' => 'x', 'description' => 'd', 'level' => 'beginner', 'duration_label' => '1 h', 'cost_units' => 0]);

    foreach ([StatutInscription::Termine, StatutInscription::EnCours, StatutInscription::EnCours, StatutInscription::EnCours] as $statut) {
        Enrollment::query()->forceCreate([
            'uuid' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id, 'course_id' => $cours->id,
            'status' => $statut, 'progress' => $statut === StatutInscription::Termine ? 100 : 40, 'last_activity_at' => now(),
        ]);
    }

    Livewire::actingAs($this->admin, 'staff')->test(Analytique::class)
        ->assertSee('Cours Alpha')->assertSee('25 %')->assertSee('4 inscrits');
});

it('additionne les paiements réussis par mois et ignore les échecs', function (): void {
    $citoyen = User::factory()->create();

    foreach ([[StatutPaiement::Reussi, 1000], [StatutPaiement::Reussi, 2500], [StatutPaiement::Echoue, 9999]] as $i => [$statut, $montant]) {
        Payment::query()->forceCreate([
            'uuid' => (string) Str::uuid(), 'user_id' => $citoyen->id, 'provider' => 'mtn', 'pack' => 'p', 'amount_fcfa' => $montant, 'units' => 10,
            'status' => $statut, 'provider_ref' => 'ref-'.$i, 'settled_at' => $statut === StatutPaiement::Reussi ? now() : null,
        ]);
    }

    Livewire::actingAs($this->admin, 'staff')->test(Analytique::class)->set('vue', 'revenus')
        ->assertSee('3 500 FCFA')->assertDontSee('9 999');
});

it('refuse une vue inconnue', function (): void {
    Livewire::actingAs($this->admin, 'staff')->test(Analytique::class, ['vue' => 'secret'])->assertSet('vue', 'academie');
});
