<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Staff\Utilisateurs;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Lot 3 — ouvrir un accès au back-office
|--------------------------------------------------------------------------
| Les deux guards partagent la table `users` : un administrateur est un `User`
| portant le rôle `Admin`. Et aucun écran ne permettait d'en créer un — le seul
| chemin était `StaffSeeder`, c'est-à-dire un développeur avec un accès console.
|
| Sur un produit de modération, où l'équipe change, c'était un point de blocage
| permanent : le porteur du projet ne pouvait pas ouvrir l'accès d'un nouveau
| modérateur sans faire appel à quelqu'un d'autre.
|
| Les trois tests du bloc « les garde-fous » sont ceux qui comptent. Chacun
| couvre une manière de se verrouiller dehors, et AUCUNE ne lève d'erreur : la
| requête réussit, et l'on découvre le problème quand plus personne ne peut
| entrer.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create([
        'otp_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->admin->assignRole(UserRole::Admin->value);
});

/** Remplit le tiroir de création avec un jeu valide. */
function nouveauCompte(string $email = 'sandra@verifall.com'): object
{
    return Livewire::actingAs(test()->admin, 'staff')
        ->test(Utilisateurs::class)
        ->call('ouvrirCreation')
        ->set('prenom', 'Sandra')
        ->set('nom', 'Kponou')
        ->set('email', $email)
        ->set('profession', 'Modératrice')
        ->set('motDePasse', 'Cotonou2026!Staff');
}

describe('créer un compte du personnel', function (): void {
    it('crée le compte et lui donne son rôle', function (): void {
        nouveauCompte()
            ->set('roles', [UserRole::Moderateur->value])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $compte = User::query()->where('email', 'sandra@verifall.com')->firstOrFail();

        expect($compte->hasRole(UserRole::Moderateur->value))->toBeTrue()
            ->and($compte->name)->toBe('Sandra Kponou');
    });

    it('active le compte sans exiger de code OTP', function (): void {
        /*
         | C'est un accès ouvert délibérément par un administrateur, pas une
         | inscription publique à vérifier. Lui imposer un code envoyé à une
         | adresse professionnelle qui peut ne pas être relevée bloquerait
         | l'accès qu'on vient d'accorder.
         */
        nouveauCompte()->set('roles', [UserRole::Mentor->value])->call('enregistrer');

        expect(User::query()->where('email', 'sandra@verifall.com')->firstOrFail()->otp_verified_at)
            ->not->toBeNull();
    });

    it('ne pose PAS la double authentification à sa place', function (): void {
        /*
         | Le guard `staff` l'exige, et `StaffLogin` conduit à l'enrôlement à la
         | première connexion : la personne scanne son propre secret. Le poser
         | ici supposerait de le lui transmettre — par un canal qui annulerait
         | tout l'intérêt du second facteur.
         */
        nouveauCompte()->set('roles', [UserRole::Mentor->value])->call('enregistrer');

        $compte = User::query()->where('email', 'sandra@verifall.com')->firstOrFail();

        expect($compte->two_factor_confirmed_at)->toBeNull()
            ->and($compte->two_factor_secret)->toBeNull();
    });

    it('journalise la création, nominativement', function (): void {
        // Un compte du personnel créé sans trace est une brèche d'audit : c'est
        // l'accès le plus sensible du produit.
        nouveauCompte()->set('roles', [UserRole::Moderateur->value])->call('enregistrer');

        $trace = AuditLog::query()->where('action', 'user.create')->first();

        expect($trace)->not->toBeNull()
            ->and($trace->actor_id)->toBe($this->admin->id);
    });

    it('refuse une adresse déjà prise', function (): void {
        User::factory()->create(['email' => 'sandra@verifall.com']);

        nouveauCompte()->call('enregistrer')->assertHasErrors('email');
    });

    it('exige un mot de passe à la création', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(Utilisateurs::class)
            ->call('ouvrirCreation')
            ->set('prenom', 'Sandra')->set('nom', 'Kponou')
            ->set('email', 'sandra@verifall.com')
            ->call('enregistrer')
            ->assertHasErrors('motDePasse');
    });
});

describe('les garde-fous', function (): void {
    it('interdit de modifier ses PROPRES rôles', function (): void {
        /*
         | Se retirer `role.manage` d'un clic maladroit ferme la porte derrière
         | soi : plus aucun écran ne permet de la rouvrir, il faut retourner à
         | la console. La case est désactivée dans la vue ; ce test existe parce
         | qu'un champ désactivé se réactive dans un navigateur.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Utilisateurs::class)
            ->call('ouvrirEdition', $this->admin->uuid)
            ->set('roles', [])
            ->call('enregistrer');

        expect($this->admin->fresh()->hasRole(UserRole::Admin->value))->toBeTrue();
    });

    it('rétrograde un administrateur tant qu\'il en reste un autre', function (): void {
        // Le geste est légitime : c'est le cas limite, plus bas, qui ne l'est pas.
        $autre = User::factory()->create(['otp_verified_at' => now()]);
        $autre->assignRole(UserRole::Admin->value);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Utilisateurs::class)
            ->call('ouvrirEdition', $autre->uuid)
            ->set('roles', [UserRole::Mentor->value])
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($autre->fresh()->hasRole(UserRole::Admin->value))->toBeFalse();
    });

    it('refuse de retirer le rôle du DERNIER administrateur', function (): void {
        /*
         | Le défaut le plus silencieux des trois : la requête réussit, la ligne
         | s'écrit, et la plateforme se retrouve sans personne pour
         | l'administrer. Rien ne le signale avant que quelqu'un en ait besoin.
         |
         | L'auteur de l'action garde `role.manage` SANS être administrateur
         | lui-même — c'est ce qui fait de la cible le dernier, et c'est aussi le
         | seul montage où le garde-fou peut s'observer : si l'auteur était
         | Admin, la cible ne serait jamais la dernière.
         */
        $cible = User::factory()->create(['otp_verified_at' => now()]);
        $cible->assignRole(UserRole::Admin->value);

        $this->admin->syncRoles([]);
        $this->admin->givePermissionTo(['user.view', 'user.update', 'user.create', 'role.manage', 'role.view']);

        expect(User::query()->role(UserRole::Admin->value)->count())->toBe(1);

        Livewire::actingAs($this->admin->fresh(), 'staff')
            ->test(Utilisateurs::class)
            ->call('ouvrirEdition', $cible->uuid)
            ->set('roles', [UserRole::Mentor->value])
            ->call('enregistrer');

        expect($cible->fresh()->hasRole(UserRole::Admin->value))->toBeTrue();
    });
});

describe('les droits d\'accès aux deux écrans', function (): void {
    it('les ferme à un modérateur', function (): void {
        $moderateur = User::factory()->create([
            'otp_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $moderateur->assignRole(UserRole::Moderateur->value);

        $this->actingAs($moderateur, 'staff')->get(route('staff.users'))->assertForbidden();
        $this->actingAs($moderateur, 'staff')->get(route('staff.roles'))->assertForbidden();
    });

    it('sert la matrice des rôles à un administrateur', function (): void {
        /*
         | L'écran est en lecture seule, et il DIT pourquoi : sans cette phrase,
         | l'absence de bouton « Modifier » passe pour une fonctionnalité
         | oubliée plutôt que pour une décision.
         */
        $this->actingAs($this->admin, 'staff')
            ->get(route('staff.roles'))
            ->assertOk()
            ->assertSee('ne se modifie pas depuis l\'interface', escape: false)
            ->assertSee('Administrateur');
    });
});
