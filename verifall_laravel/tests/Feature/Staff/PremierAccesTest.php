<?php

declare(strict_types=1);

use App\Core\Identity\Services\TwoFactorService;
use App\Enums\UserRole;
use App\Livewire\Auth\StaffLogin;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| Premier accès au back-office
|--------------------------------------------------------------------------
| Deux défauts trouvés en ouvrant simplement une page dans un navigateur, que
| la suite ne voyait pas : elle vérifiait « la porte est fermée » sans jamais
| vérifier « on peut l'ouvrir la première fois », ni « on est renvoyé au bon
| endroit ». Ces tests referment les deux.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['otp_verified_at' => now()]);
    $this->admin->assignRole(UserRole::Admin->value);

    /*
     | Le second facteur s'exige desormais par role, et la configuration livree
     | en dispense l'administrateur. Le parcours decrit ici — « on peut ouvrir
     | la porte la premiere fois » — n'a de sens que lorsqu'il EST exige : on le
     | declare, plutot que de dependre d'un reglage d'exploitation.
     |
     | La dispense a son propre fichier :
     | tests/Feature/Auth/DeuxFacteursParRoleTest.php
     */
    config()->set('verifall.securite.deux_facteurs.roles', [UserRole::Admin->value]);
});

describe('redirection', function (): void {
    it('renvoie vers la connexion du BACK-OFFICE, pas celle des citoyens', function (string $route): void {
        // Un membre de l'équipe envoyé sur le formulaire citoyen y voit ses
        // identifiants refusés, sans rien qui le lui explique.
        $this->get(route($route))->assertRedirect(route('staff.login'));
    })->with(['staff.audit', 'staff.alerts', 'staff.unit-packs']);

    it('renvoie un citoyen vers SA connexion', function (): void {
        $this->get(route('citizen.dashboard'))->assertRedirect(route('login'));
    });
});

describe('enrôlement du second facteur', function (): void {
    it('propose l\'enrôlement quand le compte n\'a pas encore de second facteur', function (): void {
        // Sans cet écran, le compte restait dehors : le back-office réclamait
        // un code qu'aucune page ne permettait d'obtenir.
        Livewire::test(StaffLogin::class)
            ->set('email', $this->admin->email)
            ->set('motDePasse', 'password')
            ->call('verifierIdentifiants')
            ->assertSet('enrolement', true)
            ->assertSee('Activer la double authentification')
            ->assertSee('Vos codes de récupération');
    });

    it('n\'active la 2FA qu\'après un premier code valide', function (): void {
        $composant = Livewire::test(StaffLogin::class)
            ->set('email', $this->admin->email)
            ->set('motDePasse', 'password')
            ->call('verifierIdentifiants');

        // Le secret est préparé mais PAS confirmé : une erreur de scan ne doit
        // pas enfermer quelqu'un dehors sur un compte administrateur.
        expect($this->admin->fresh()->two_factor_secret)->not->toBeNull()
            ->and(app(TwoFactorService::class)->estActive($this->admin->fresh()))->toBeFalse();

        $code = (new Google2FA)->getCurrentOtp($this->admin->fresh()->two_factor_secret);

        $composant->set('code', $code)->call('verifierCode')->assertRedirect();

        expect(app(TwoFactorService::class)->estActive($this->admin->fresh()))->toBeTrue();
        $this->assertAuthenticatedAs($this->admin, 'staff');
    });

    it('refuse un code erroné et laisse la 2FA inactive', function (): void {
        Livewire::test(StaffLogin::class)
            ->set('email', $this->admin->email)
            ->set('motDePasse', 'password')
            ->call('verifierIdentifiants')
            ->set('code', '000000')
            ->call('verifierCode')
            ->assertHasErrors(['code']);

        expect(app(TwoFactorService::class)->estActive($this->admin->fresh()))->toBeFalse();
        $this->assertGuest('staff');
    });

    it('demande le code sans enrôlement quand la 2FA est déjà active', function (): void {
        app(TwoFactorService::class)->preparer($this->admin);
        $this->admin->forceFill(['two_factor_confirmed_at' => now()])->save();

        Livewire::test(StaffLogin::class)
            ->set('email', $this->admin->email)
            ->set('motDePasse', 'password')
            ->call('verifierIdentifiants')
            ->assertSet('enrolement', false)
            ->assertSee('Vérification en deux étapes')
            // Les codes de récupération ne se réaffichent jamais.
            ->assertDontSee('Vos codes de récupération');
    });
});
