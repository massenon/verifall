<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotInscriptionsExamen;
use App\Core\Exams\Services\TableauDeLEnrolementAuxExamens;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table « Enrôlement aux examens ». Même garde que l'écran
 * (`can:enrolment.view`) ; le service la rejoue. Des noms et des adresses :
 * jamais en cache.
 */
final class DonneesDeLEnrolementAuxExamensController
{
    public function __invoke(Request $requete, TableauDeLEnrolementAuxExamens $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotInscriptionsExamen::COLONNES_TRIABLES,
            $tableau->filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            ->header('Cache-Control', 'no-store, private');
    }
}
