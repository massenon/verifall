<?php

declare(strict_types=1);

use App\Core\Identity\Services\TwoFactorService;
use App\Enums\UserRole;
use App\Livewire\Auth\StaffLogin;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Second facteur exige PAR ROLE
|--------------------------------------------------------------------------
| Le back-office reclamait un code TOTP a tout le personnel, sans exception.
| C'est desormais un reglage : `verifall.securite.deux_facteurs.roles` dit
| pour QUI, et un role absent de la liste entre au mot de passe seul.
|
| Ce que ce fichier tient, dans les deux sens :
|
|  - un role dispense n'a ni ecran de code, ni secret prepare en base. Un
|    secret a moitie pose serait pire que pas de secret du tout : le compte
|    porterait une protection que personne n'a confirmee ;
|  - un role liste garde son second facteur, entier. La dispense doit rester
|    une decision explicite, jamais un effet de bord.
*/

const MDP_DISPENSE = 'Cotonou2026!Staff';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** Un compte du personnel, sans second facteur enrole. */
function staffSansSecondFacteur(UserRole $role): User
{
    $user = User::factory()->create([
        'email' => 'dispense@verifall.com',
        'password' => MDP_DISPENSE,
        'otp_verified_at' => now(),
    ]);
    $user->assignRole($role->value);

    return $user->fresh();
}

describe('un role dispense', function (): void {
    it('entre au mot de passe seul, sans ecran de code', function (UserRole $role): void {
        config()->set('verifall.securite.deux_facteurs.roles', [UserRole::Moderateur->value]);

        $utilisateur = staffSansSecondFacteur($role);

        Livewire::test(StaffLogin::class)
            ->set('email', 'dispense@verifall.com')
            ->set('motDePasse', MDP_DISPENSE)
            ->call('verifierIdentifiants')
            ->assertSet('attenteCode', false)
            ->assertSet('enrolement', false)
            ->assertRedirect(route('staff.dashboard'));

        expect(Auth::guard('staff')->id())->toBe($utilisateur->id);
    })->with([
        'Admin' => UserRole::Admin,
        'Mentor' => UserRole::Mentor,
    ]);

    it('ne prepare AUCUN secret en base', function (): void {
        config()->set('verifall.securite.deux_facteurs.roles', [UserRole::Moderateur->value]);

        $utilisateur = staffSansSecondFacteur(UserRole::Admin);

        Livewire::test(StaffLogin::class)
            ->set('email', 'dispense@verifall.com')
            ->set('motDePasse', MDP_DISPENSE)
            ->call('verifierIdentifiants');

        /*
         | Un secret prepare mais jamais confirme laisserait le compte dans un
         | entre-deux : la colonne est remplie, la protection ne s'applique pas,
         | et le jour ou l'on remet le role dans la liste, l'ecran reclamerait un
         | code d'une application que personne n'a configuree.
         */
        expect($utilisateur->fresh()->two_factor_secret)->toBeNull()
            ->and(app(TwoFactorService::class)->estActive($utilisateur->fresh()))->toBeFalse();
    });

    it('refuse quand meme un mot de passe faux', function (): void {
        config()->set('verifall.securite.deux_facteurs.roles', []);

        staffSansSecondFacteur(UserRole::Admin);

        Livewire::test(StaffLogin::class)
            ->set('email', 'dispense@verifall.com')
            ->set('motDePasse', 'ce-n-est-pas-le-bon')
            ->call('verifierIdentifiants')
            ->assertHasErrors('email');

        expect(Auth::guard('staff')->check())->toBeFalse();
    });
});

describe('un role toujours liste', function (): void {
    it('garde son second facteur', function (): void {
        config()->set('verifall.securite.deux_facteurs.roles', [UserRole::Moderateur->value]);

        staffSansSecondFacteur(UserRole::Moderateur);

        Livewire::test(StaffLogin::class)
            ->set('email', 'dispense@verifall.com')
            ->set('motDePasse', MDP_DISPENSE)
            ->call('verifierIdentifiants')
            ->assertSet('attenteCode', true)
            ->assertSet('enrolement', true);

        // Le mot de passe seul n'ouvre rien tant que le code n'est pas donne.
        expect(Auth::guard('staff')->check())->toBeFalse();
    });
});

describe('le service dit qui est concerne', function (): void {
    it('traite un compte a plusieurs roles par le plus exigeant', function (): void {
        config()->set('verifall.securite.deux_facteurs.roles', [UserRole::Moderateur->value]);

        $utilisateur = staffSansSecondFacteur(UserRole::Admin);
        $utilisateur->assignRole(UserRole::Moderateur->value);

        /*
         | Un administrateur a qui l'on confie en plus la moderation ne doit pas
         | voir sa protection BAISSER parce qu'on lui a ajoute une tache.
         */
        expect(app(TwoFactorService::class)->exigePour($utilisateur->fresh()))->toBeTrue();
    });

    it('ne demande rien quand la liste est vide', function (): void {
        config()->set('verifall.securite.deux_facteurs.roles', []);

        $utilisateur = staffSansSecondFacteur(UserRole::Moderateur);

        expect(app(TwoFactorService::class)->exigePour($utilisateur))->toBeFalse();
    });
});
