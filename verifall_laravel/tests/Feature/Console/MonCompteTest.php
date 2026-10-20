<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Staff\MonCompte;
use App\Models\AuditLog;
use App\Models\HistoriqueConnexion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
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
