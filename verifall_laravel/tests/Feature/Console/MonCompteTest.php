<?php

declare(strict_types=1);

use App\Core\Application\Services\ModeMaintenance;
use App\Enums\UserRole;
use App\Livewire\Auth\StaffForgotPassword;
use App\Livewire\Staff\MonCompte;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\HistoriqueConnexion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->membre = User::factory()->actif()->create(['password' => 'Ancien-mot-de-passe-12']);
    $this->membre->assignRole(UserRole::Mentor->value);
});

it('est réservée à la personne connectée à la console', function (): void {
    $this->get(route('staff.account'))->assertRedirect();
    $this->actingAs($this->membre, 'staff')->get(route('staff.account'))->assertOk()->assertSee($this->membre->email);
});

it('enregistre le profil et le journalise', function (): void {
    Livewire::actingAs($this->membre, 'staff')->test(MonCompte::class)
        ->set('prenom', 'Awa')->set('nom', 'Kpoton')->set('ville', 'Cotonou')
        ->call('enregistrerLeProfil')->assertHasNoErrors();

    expect($this->membre->fresh()->name)->toBe('Awa Kpoton')
        ->and(AuditLog::where('action', 'account.profile_update')->exists())->toBeTrue();
});

it('exige le mot de passe actuel pour en changer', function (): void {
    Livewire::actingAs($this->membre, 'staff')->test(MonCompte::class)
        ->set('onglet', 'securite')
        ->set('motDePasseActuel', 'faux')
        ->set('nouveauMotDePasse', 'Nouveau-mot-de-passe-34!')->set('nouveauMotDePasse_confirmation', 'Nouveau-mot-de-passe-34!')
        ->call('changerLeMotDePasse')->assertHasErrors('motDePasseActuel');

    expect(Hash::check('Ancien-mot-de-passe-12', $this->membre->fresh()->password))->toBeTrue();
});

it('change le mot de passe avec le bon actuel', function (): void {
    Livewire::actingAs($this->membre, 'staff')->test(MonCompte::class)
        ->set('motDePasseActuel', 'Ancien-mot-de-passe-12')
        ->set('nouveauMotDePasse', 'Nouveau-mot-de-passe-34!')->set('nouveauMotDePasse_confirmation', 'Nouveau-mot-de-passe-34!')
        ->call('changerLeMotDePasse')->assertHasNoErrors();

    expect(Hash::check('Nouveau-mot-de-passe-34!', $this->membre->fresh()->password))->toBeTrue();
});

it('montre l’historique des connexions de la personne seulement', function (): void {
    $autre = User::factory()->actif()->create();
    HistoriqueConnexion::create(['user_id' => $autre->id, 'espace' => 'console', 'resultat' => 'reussie', 'methode' => 'mot_de_passe', 'ip_tronquee' => '9.9.9.0']);
    HistoriqueConnexion::create(['user_id' => $this->membre->id, 'espace' => 'console', 'resultat' => 'reussie', 'methode' => 'mot_de_passe', 'ip_tronquee' => '1.2.3.0']);

    Livewire::actingAs($this->membre, 'staff')->test(MonCompte::class)->set('onglet', 'connexions')
        ->assertSee('1.2.3.0')->assertDontSee('9.9.9.0');
});

it('réinitialise le mot de passe de la console sans connecter la personne', function (): void {
    Mail::fake();

    Livewire::test(StaffForgotPassword::class)
        ->set('email', $this->membre->email)->call('demander')->assertSet('etape', 2);

    expect(Auth::guard('staff')->check())->toBeFalse();
});

it('laisse la réinitialisation joignable pendant une maintenance', function (): void {
    app(ModeMaintenance::class)->activer($this->membre);

    $this->get(route('staff.password.request'))->assertOk();
});

it('garde le thème choisi avec le compte', function (): void {
    $this->actingAs($this->membre, 'staff')->postJson(route('staff.theme'), ['theme' => 'dark'])->assertOk();
    expect($this->membre->fresh()->console_theme)->toBe('dark');

    $this->actingAs($this->membre, 'staff')->postJson(route('staff.theme'), ['theme' => 'rose'])->assertStatus(422);
});

it('cherche dans les données seulement ce que le droit permet de lister', function (): void {
    User::factory()->actif()->create(['name' => 'Kossi Zinsou', 'email' => 'kossi@example.test']);

    $this->actingAs($this->membre, 'staff')->getJson(route('staff.search', ['q' => 'Zinsou']))->assertOk()->assertJsonCount(0, 'resultats');

    $admin = User::factory()->actif()->create();
    $admin->assignRole(UserRole::Admin->value);
    $this->actingAs($admin, 'staff')->getJson(route('staff.search', ['q' => 'Zinsou']))->assertJsonPath('resultats.0.libelle', 'Kossi Zinsou');
    $this->actingAs($admin, 'staff')->getJson(route('staff.search', ['q' => 'Z']))->assertJsonCount(0, 'resultats');
});

it('affiche l’onglet des sessions', function (): void {
    Livewire::actingAs($this->membre, 'staff')->test(MonCompte::class)->set('onglet', 'sessions')
        ->assertSee('Appareils connectés')->call('fermerLesAutres')->assertHasNoErrors();
});

it('laisse un mentor ouvrir sa propre fiche, et seulement la sienne', function (): void {
    $this->actingAs($this->membre, 'staff')->get(route('staff.users.show', $this->membre))->assertOk();

    $autre = User::factory()->actif()->create();
    $this->actingAs($this->membre, 'staff')->get(route('staff.users.show', $autre))->assertForbidden();
    $this->actingAs($this->membre, 'staff')->getJson(route('staff.users.show.data', ['user' => $autre->uuid, 'onglet' => 'verifications']))->assertForbidden();
});

it('ouvre au mentor la fiche de son apprenant, réduite à sa progression', function (): void {
    $cours = Course::query()->forceCreate(['uuid' => (string) Str::uuid(), 'slug' => 'c-mentor', 'title' => 'Cours du mentor', 'category' => 'x', 'description' => 'd', 'level' => 'beginner', 'duration_label' => '1 h', 'cost_units' => 0, 'instructor_id' => $this->membre->id]);
    $apprenant = User::factory()->actif()->create(['email' => 'apprenant-prive@example.test', 'name' => 'Apprenant Visible']);
    Enrollment::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $apprenant->id, 'course_id' => $cours->id, 'status' => 'in_progress', 'progress' => 40, 'last_activity_at' => now()]);

    $page = $this->actingAs($this->membre, 'staff')->get(route('staff.users.show', $apprenant))->assertOk();
    $page->assertSee('Apprenant Visible')->assertSee('Cours du mentor')->assertSee('40 %');
    expect($page->getContent())->not->toContain('apprenant-prive@example.test');

    // Pas d'onglets de données : ni connexions, ni paiements.
    $this->actingAs($this->membre, 'staff')->getJson(route('staff.users.show.data', ['user' => $apprenant->uuid, 'onglet' => 'paiements']))->assertForbidden();
    $this->actingAs($this->membre, 'staff')->getJson(route('staff.users.show.data', ['user' => $apprenant->uuid, 'onglet' => 'connexions']))->assertForbidden();

    // Un citoyen inscrit ailleurs : refus.
    $etranger = User::factory()->actif()->create();
    $this->actingAs($this->membre, 'staff')->get(route('staff.users.show', $etranger))->assertForbidden();
});
