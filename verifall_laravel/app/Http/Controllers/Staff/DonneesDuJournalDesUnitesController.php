<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Core\Economy\Services\TableauDuJournalDesUnites;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table DataTables « Historiques des Unités IA ».
 *
 * Même garde de route que l'écran (`can:journal-des-unites`). Contrat :
 * `docs/DATATABLES_CONTRAT.md`.
 */
final class DonneesDuJournalDesUnitesController
{
    public function __invoke(Request $requete, TableauDuJournalDesUnites $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            UnitLogRepositoryInterface::COLONNES_TRIABLES,
            TableauDuJournalDesUnites::filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            // Noms et adresses des titulaires : aucun cache intermédiaire.
            ->header('Cache-Control', 'no-store, private');
    }
}
