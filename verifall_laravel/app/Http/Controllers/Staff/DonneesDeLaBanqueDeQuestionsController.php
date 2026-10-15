<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Core\Exams\Services\TableauDeLaBanqueDeQuestions;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table « Banque de questions ».
 *
 * Mince, comme celle du pilote : lire la requête à travers les listes
 * blanches, passer la main au service, répondre. Même garde de route que
 * l'écran (`can:viewAny,QuestionBankItem`).
 */
final class DonneesDeLaBanqueDeQuestionsController
{
    public function __invoke(Request $requete, TableauDeLaBanqueDeQuestions $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotBanqueQuestions::COLONNES_TRIABLES,
            $tableau->filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            ->header('Cache-Control', 'no-store, private');
    }
}
