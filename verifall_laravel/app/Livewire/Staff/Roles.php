<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Rbac\MatricePermissions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * « Rôles & permissions » — la matrice, telle qu'elle s'applique réellement.
 *
 * ## Pourquoi cet écran est en LECTURE SEULE
 *
 * C'est le choix qui demande le plus d'explication, parce qu'il ressemble à une
 * fonctionnalité qu'on aurait laissée de côté. Il n'en est pas une.
 *
 * `MatricePermissions` est du CODE, et c'est ce qui lui donne sa valeur : elle
 * se relit dans une revue, se compare entre deux versions, et porte en
 * commentaire la raison de chaque arbitrage — pourquoi le modérateur rédige
 * mais ne publie pas, pourquoi le mentor alimente la banque de questions sans
 * la publier. Rien de tout cela ne survit à une grille de cases à cocher.
 *
 * La rendre modifiable à l'écran ferait diverger deux vérités : celle du dépôt
 * et celle de la base. Au prochain semis, l'une écraserait l'autre — et l'on
 * découvrirait la perte le jour où quelqu'un ne peut plus faire son travail.
 *
 * Un accès particulier se donne donc en **attribuant un rôle**, à l'écran
 * « Utilisateurs ». Changer ce qu'un rôle recouvre est une décision
 * d'architecture, elle passe par une revue de code.
 *
 * ## Ce que l'écran apporte, alors
 *
 * Il rend la matrice LISIBLE par qui n'ouvre pas le dépôt. Avant lui, la seule
 * façon de répondre à « qu'est-ce qu'un modérateur peut faire, au juste ? »
 * était de lire un fichier PHP. C'est aussi le seul endroit où l'on voit d'un
 * coup d'œil ce qu'on s'apprête à accorder en cochant une case.
 */
#[Layout('components.layouts.staff', ['title' => "Rôles & permissions · Vérif'All"])]
final class Roles extends Component
{
    /** Le rôle détaillé ; vide = la vue d'ensemble. */
    public string $roleOuvert = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    public function ouvrir(string $role): void
    {
        $this->roleOuvert = $this->roleOuvert === $role ? '' : $role;
    }

    public function render(): View
    {
        return view('livewire.staff.roles', [
            'roles' => $this->roles(),
            'modules' => MatricePermissions::parModule(),
            'totalPermissions' => count(MatricePermissions::toutes()),
        ]);
    }

    /**
     * Chaque rôle, avec ses droits et le nombre de comptes qui le portent.
     *
     * Les permissions viennent de la MATRICE et non de la base : c'est la
     * matrice qui fait foi, et un écart entre les deux signalerait un semis
     * oublié — qu'on veut voir, pas masquer en lisant la base.
     *
     * @return list<array{role: UserRole, permissions: list<string>, comptes: int, seme: bool}>
     */
    private function roles(): array
    {
        $semes = Role::query()->pluck('name')->all();

        return array_map(
            static fn (UserRole $role): array => [
                'role' => $role,
                'permissions' => MatricePermissions::pourRole($role),
                'comptes' => User::query()->role($role->value)->count(),
                // Un rôle absent de la base n'est attribuable par personne :
                // l'écran doit le dire plutôt que de le présenter comme actif.
                'seme' => in_array($role->value, $semes, true),
            ],
            UserRole::cases(),
        );
    }
}
