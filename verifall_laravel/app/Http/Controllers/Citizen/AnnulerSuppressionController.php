<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Core\Identity\Services\SuppressionCompteService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Rétractation — annule une suppression programmée.
 *
 * Accessible SANS session, et c'est le cœur du dispositif : le compte étant
 * fermé, il n'est plus possible de se connecter pour changer d'avis. La
 * signature de l'URL tient lieu de preuve, et sa durée est bornée à l'échéance
 * elle-même — au-delà, il n'y a plus rien à annuler.
 *
 * Le lien est envoyé à l'adresse du compte : c'est aussi ce qui alerte la vraie
 * personne quand la demande vient d'un écran resté ouvert.
 */
final class AnnulerSuppressionController
{
    public function __invoke(Request $request, string $uuid, SuppressionCompteService $suppressions): RedirectResponse
    {
        // withTrashed : le compte est précisément celui qui a été fermé.
        $utilisateur = User::withTrashed()->where('uuid', $uuid)->first();

        if ($utilisateur === null || $utilisateur->deletion_requested_at === null) {
            return redirect()->route('login')->with('suppression_annulation', 'introuvable');
        }

        if (! $suppressions->annuler($utilisateur)) {
            // L'échéance est passée : le dire franchement vaut mieux que de
            // rendre un compte vidé de tout ce qui le rendait utilisable.
            return redirect()->route('login')->with('suppression_annulation', 'trop_tard');
        }

        return redirect()->route('login')->with('suppression_annulation', 'reussie');
    }
}
