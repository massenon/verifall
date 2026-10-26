<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le mentor a son propre tableau de bord (IMPLEMENTATION_PLAN, P4-2) : une
 * visite de `staff.dashboard` l'y envoie, au lieu de lui montrer celui de la
 * plateforme, dont les panneaux lui sont presque tous fermés.
 *
 * Un middleware de route et non un `mount()` du composant : le composant reste
 * testable tel quel, y compris par un mentor qui forgerait un appel (les tests
 * de sécurité de l'assignation en dépendent).
 */
final class EnvoyerLeMentorVersSonTableauDeBord
{
    public function handle(Request $requete, Closure $suite): Response
    {
        $membre = $requete->user('staff');

        if ($membre instanceof User && ! MenuDuBackOffice::estTableauDeBordDe($membre, 'staff.dashboard')) {
            return redirect()->route('staff.mentor.dashboard');
        }

        return $suite($requete);
    }
}
