<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotExamens;
use App\Core\Exams\Services\TableauDesExamens;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table « Liste des examens ». Même garde que l'écran
 * (`can:viewAny,Exam`) ; aucune règle ici, tout est dans le service.
 */
final class DonneesDesExamensController
{
    public function __invoke(Request $requete, TableauDesExamens $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotExamens::COLONNES_TRIABLES,
            $tableau->filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            ->header('Cache-Control', 'no-store, private');
    }
}
