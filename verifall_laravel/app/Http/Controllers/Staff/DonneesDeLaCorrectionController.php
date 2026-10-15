<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Core\Exams\Services\TableauDeLaCorrection;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la file « Correction des examens ». Même garde que l'écran
 * (`can:exam.grade`) ; le service la rejoue. Des noms de candidats : jamais en
 * cache.
 */
final class DonneesDeLaCorrectionController
{
    public function __invoke(Request $requete, TableauDeLaCorrection $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotCopiesExamen::COLONNES_TRIABLES,
            $tableau->filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            ->header('Cache-Control', 'no-store, private');
    }
}
