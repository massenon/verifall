<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotMessagesContact;
use App\Core\Marketing\Services\TableauDesMessagesContact;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table DataTables « Messages reçus ».
 *
 * Mince par construction, comme le pilote `DonneesDesUtilisateursController`.
 * Même garde de route que l'écran (`can:viewAny,ContactMessage`).
 */
final class DonneesDesMessagesContactController
{
    public function __invoke(Request $requete, TableauDesMessagesContact $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotMessagesContact::COLONNES_TRIABLES,
            TableauDesMessagesContact::filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            // Des noms, des adresses et des messages privés.
            ->header('Cache-Control', 'no-store, private');
    }
}
