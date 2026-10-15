<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Core\FactChecking\Services\TableauDesSourcesVerifiees;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table DataTables « Sources vérifiées ».
 *
 * Même garde de route que l'écran (`can:viewAny,TrustedSource`). Contrat :
 * `docs/DATATABLES_CONTRAT.md`.
 */
final class DonneesDesSourcesVerifieesController
{
    public function __invoke(Request $requete, TableauDesSourcesVerifiees $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotSourcesFiables::COLONNES_TRIABLES,
            $tableau->filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            ->header('Cache-Control', 'no-store, private');
    }
}
