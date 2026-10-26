<?php

declare(strict_types=1);

use App\Enums\StatutDevoir;
use App\Enums\UserRole;
use App\Livewire\Staff\Mentor\TableauDeBord;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
| IMPLEMENTATION_PLAN, P4-2 — le tableau de bord du mentor, borné à ses cours.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();

    $this->mentor = User::factory()->actif()->create(['first_name' => 'Fifamè', 'last_name' => 'Houngbédji']);
    $this->mentor->assignRole(UserRole::Mentor->value);
    $this->collegue = User::factory()->actif()->create();
    $this->collegue->assignRole(UserRole::Mentor->value);
});

function tdbCoursDe(User $formateur, string $titre): Course
{
    return Course::create([
        'uuid' => (string) Str::uuid(), 'slug' => Str::slug($titre).'-'.Str::lower(Str::random(4)),
        'title' => $titre, 'description' => 'Un cours de test suffisamment décrit.', 'category' => 'OSINT',
        'level' => 'beginner', 'duration_label' => '1 h', 'cost_units' => 0,
        'instructor_id' => $formateur->id, 'is_published' => true,
    ]);
}

function tdbCopie(Course $cours, string $devoir): AssignmentSubmission
{
    $assignment = Assignment::create(['uuid' => (string) Str::uuid(), 'course_id' => $cours->id, 'position' => 1, 'title' => $devoir, 'description' => 'x', 'minutes' => 30]);
    $apprenant = User::factory()->actif()->create();

    return AssignmentSubmission::create(['uuid' => (string) Str::uuid(), 'user_id' => $apprenant->id, 'assignment_id' => $assignment->id, 'status' => StatutDevoir::Remis->value, 'note' => 'Ma copie']);
}

it('envoie le mentor vers son propre tableau de bord', function (): void {
    $this->actingAs($this->mentor, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp])
        ->get(route('staff.dashboard'))
        ->assertRedirect(route('staff.mentor.dashboard'));
});

it('affiche sa journée, ses chiffres et ses copies', function (): void {
    tdbCopie(tdbCoursDe($this->mentor, 'OSINT Fondamentaux'), 'Enquête guidée sur un domaine');

    Livewire::actingAs($this->mentor, 'staff')
        ->test(TableauDeBord::class)
        ->assertOk()
        ->assertSee('Bonjour Fifamè')
        ->assertSee('Ma journée')
        ->assertSee('Enquête guidée sur un domaine')
        ->assertSee('OSINT Fondamentaux');
});

it('ne montre jamais les copies ni les cours d’un autre mentor', function (): void {
    tdbCopie(tdbCoursDe($this->collegue, 'Cours du collègue'), 'Devoir du collègue');

    Livewire::actingAs($this->mentor, 'staff')
        ->test(TableauDeBord::class)
        ->assertDontSee('Devoir du collègue')
        ->assertDontSee('Cours du collègue')
        ->assertSee('Aucune copie n’attend votre correction.');
});

it('donne un seul « Tableau de bord » à chacun dans le menu', function (): void {
    $admin = User::factory()->actif()->create();
    $admin->assignRole(UserRole::Admin->value);

    // Le calendrier partage le groupe d'accueil ; seul le tableau de bord compte ici.
    $routes = fn (User $u): array => collect(MenuDuBackOffice::pour($u)[''] ?? [])->pluck('route')
        ->reject(fn (string $r): bool => $r === 'staff.calendar')->values()->all();

    expect($routes($this->mentor))->toBe(['staff.mentor.dashboard'])
        ->and($routes($admin))->toBe(['staff.dashboard']);
});

it('reste fermé à qui n’encadre pas d’apprenants', function (): void {
    // Un compte sans permission `student.view` : la garde de la route refuse.
    $sansDroit = User::factory()->actif()->create();

    $this->actingAs($sansDroit, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp])
        ->get(route('staff.mentor.dashboard'))
        ->assertForbidden();
});
