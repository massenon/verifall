<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Qui peut LIRE la matrice des rôles.
 *
 * Un seul verbe servi, `viewAny`, et l'absence des autres est le fait
 * marquant : cette Policy garde un écran en lecture seule.
 *
 * La matrice n'est pas modifiable depuis l'interface — elle vit dans
 * `MatricePermissions`, où chaque arbitrage porte sa raison en commentaire, et
 * où une revue de code peut le contester. Déclarer ici un `update` autorisé
 * alors qu'aucun écran ne l'exerce donnerait un droit fantôme : le genre qu'on
 * croit détenir jusqu'au jour où l'on en a besoin.
 *
 * `role.manage` existe bel et bien dans la matrice, et il sert : c'est le droit
 * d'ATTRIBUER un rôle à quelqu'un, exigé par `UserPolicy::create()`. Attribuer
 * un rôle et redéfinir ce qu'il recouvre sont deux gestes de nature
 * différente ; seul le premier passe par l'interface.
 */
final class RolePolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('role.view');
    }

    public function view(User $utilisateur, Role $role): bool
    {
        return $utilisateur->can('role.view');
    }
}
