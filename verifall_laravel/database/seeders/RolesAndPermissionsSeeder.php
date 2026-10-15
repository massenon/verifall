<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Support\Rbac\MatricePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions — premier seeder de la chaîne.
 *
 * Idempotent : rejouable sur une base existante sans dupliquer ni perdre les
 * attributions. C'est indispensable, car la matrice évoluera à chaque sprint
 * qui ajoute un module.
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Le cache de permissions survit aux migrations : sans purge, les rôles
        // fraîchement créés paraissent dépourvus de droits.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->creerPermissions();
        $this->creerRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function creerPermissions(): void
    {
        foreach (MatricePermissions::toutes() as $nom) {
            Permission::findOrCreate($nom, 'web');
        }

        $this->command?->info(sprintf(
            '  %d permissions sur %d modules.',
            count(MatricePermissions::toutes()),
            count(MatricePermissions::parModule()),
        ));
    }

    private function creerRoles(): void
    {
        foreach (UserRole::cases() as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value, 'web');
            $permissions = MatricePermissions::pourRole($roleEnum);

            // syncPermissions et non givePermissionTo : un droit retiré de la
            // matrice doit disparaître au rejeu, sinon la base accumule des
            // permissions fantômes que plus aucun document ne justifie.
            $role->syncPermissions($permissions);

            $this->command?->info(sprintf(
                '  %-12s %3d permission(s)%s',
                $roleEnum->value,
                count($permissions),
                $roleEnum->porteeGlobale() ? ' — portée globale' : '',
            ));
        }
    }
}
