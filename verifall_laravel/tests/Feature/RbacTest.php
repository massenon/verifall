<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Rbac\MatricePermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| RBAC — la matrice est vérifiée, pas seulement écrite
|--------------------------------------------------------------------------
| Ces tests portent surtout sur les FRONTIÈRES : ce que chaque rôle ne peut
| PAS faire. Un droit accordé par mégarde ne se voit pas à la relecture ; il
| se voit ici.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('crée les quatre rôles attendus', function (): void {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['Admin', 'Citoyen', 'Mentor', 'Modérateur']);
});

it('crée toutes les permissions de la matrice, sans doublon', function (): void {
    $attendues = MatricePermissions::toutes();

    expect(Permission::count())->toBe(count($attendues))
        ->and(count($attendues))->toBe(count(array_unique($attendues)));
});

it('est rejouable sans dupliquer', function (): void {
    $avant = Permission::count();

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::count())->toBe($avant);
});

it('retire un droit supprimé de la matrice au rejeu', function (): void {
    // syncPermissions et non givePermissionTo : sans cela, la base accumulerait
    // des permissions fantômes que plus aucun document ne justifie.
    $mentor = Role::findByName(UserRole::Mentor->value);
    $mentor->givePermissionTo('user.delete');

    expect($mentor->fresh()->hasPermissionTo('user.delete'))->toBeTrue();

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($mentor->fresh()->hasPermissionTo('user.delete'))->toBeFalse();
});

describe('Administrateur', function (): void {
    it('détient toutes les permissions', function (): void {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        foreach (MatricePermissions::toutes() as $permission) {
            expect($admin->can($permission))->toBeTrue("manque : {$permission}");
        }
    });
});

describe('Mentor', function (): void {
    it('produit du contenu pédagogique et corrige', function (string $permission): void {
        $mentor = User::factory()->create();
        $mentor->assignRole(UserRole::Mentor->value);

        expect($mentor->can($permission))->toBeTrue();
    })->with(['course.create', 'course.update', 'lesson.create', 'quiz.create',
        'exam.grade', 'certificate.issue', 'student.progress']);

    it('ne publie pas : engager la plateforme reste à l\'administrateur', function (): void {
        $mentor = User::factory()->create();
        $mentor->assignRole(UserRole::Mentor->value);

        expect($mentor->can('course.publish'))->toBeFalse()
            ->and($mentor->can('course.delete'))->toBeFalse()
            ->and($mentor->can('course.archive'))->toBeFalse();
    });

    it('n\'accède ni aux utilisateurs, ni à la sécurité, ni à la configuration', function (string $permission): void {
        $mentor = User::factory()->create();
        $mentor->assignRole(UserRole::Mentor->value);

        expect($mentor->can($permission))->toBeFalse();
    })->with(['user.view', 'user.suspend', 'role.manage', 'security.logs',
        'system.settings', 'maintenance.backup', 'kyc.review']);
});

describe('Modérateur', function (): void {
    it('modère le forum et rend des verdicts', function (string $permission): void {
        $moderateur = User::factory()->create();
        $moderateur->assignRole(UserRole::Moderateur->value);

        expect($moderateur->can($permission))->toBeTrue();
    })->with(['signalement.verdict', 'signalement.assign', 'forum.moderate', 'forum.delete']);

    it('ne supprime pas un signalement : effacer une trace reste tracé', function (): void {
        $moderateur = User::factory()->create();
        $moderateur->assignRole(UserRole::Moderateur->value);

        expect($moderateur->can('signalement.delete'))->toBeFalse()
            ->and($moderateur->can('signalement.publish'))->toBeFalse();
    });

    it('ne touche pas au contenu pédagogique', function (): void {
        $moderateur = User::factory()->create();
        $moderateur->assignRole(UserRole::Moderateur->value);

        expect($moderateur->can('course.create'))->toBeFalse()
            ->and($moderateur->can('exam.grade'))->toBeFalse();
    });
});

describe('Citoyen', function (): void {
    it('n\'a aucune permission de back-office', function (): void {
        $citoyen = User::factory()->create();
        $citoyen->assignRole(UserRole::Citoyen->value);

        $accordees = array_filter(
            MatricePermissions::toutes(),
            fn (string $p): bool => $citoyen->can($p),
        );

        expect($accordees)->toBeEmpty();
    });

    it('est le seul rôle exclu du back-office', function (): void {
        foreach (UserRole::cases() as $role) {
            expect($role->accedeAuBackOffice())
                ->toBe($role !== UserRole::Citoyen, "rôle {$role->value}");
        }
    });
});

it('réserve la portée globale au seul administrateur', function (): void {
    // Le Mentor est cantonné à ses ressources : la portée sera appliquée dans
    // les Repositories, jamais seulement dans l'interface.
    $globaux = array_filter(UserRole::cases(), fn (UserRole $r): bool => $r->porteeGlobale());

    expect($globaux)->toHaveCount(1)
        ->and(reset($globaux))->toBe(UserRole::Admin);
});

it('oriente chaque rôle vers le bon shell après connexion', function (): void {
    expect(UserRole::Citoyen->routeApresConnexion())->toBe('citizen.dashboard')
        ->and(UserRole::Admin->routeApresConnexion())->toBe('staff.dashboard')
        ->and(UserRole::Mentor->routeApresConnexion())->toBe('staff.dashboard');
});
