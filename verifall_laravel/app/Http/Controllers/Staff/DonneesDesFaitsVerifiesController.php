<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotFaitsVerifies;
use App\Core\FactChecking\Services\TableauDesFaitsVerifies;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table DataTables « Fact checks ».
 *
 * Mince, comme celle du pilote : lecture de la requête à travers les listes
 * blanches, service, réponse. Même garde de route que l'écran
 * (`can:viewAny,VerifiedFact`). Contrat : `docs/DATATABLES_CONTRAT.md`.
 */
final class DonneesDesFaitsVerifiesController
{
    public function __invoke(Request $requete, TableauDesFaitsVerifies $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotFaitsVerifies::COLONNES_TRIABLES,
            TableauDesFaitsVerifies::filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            // Des brouillons non publiés : aucun cache intermédiaire n'a à en
            // garder une copie.
            ->header('Cache-Control', 'no-store, private');
    }
}
