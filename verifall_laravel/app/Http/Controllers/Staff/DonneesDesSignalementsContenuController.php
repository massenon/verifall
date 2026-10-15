<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotSignalementsContenu;
use App\Core\Community\Services\TableauDesSignalementsContenu;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table DataTables « Contenus signalés ».
 *
 * Mince par construction, comme le pilote `DonneesDesUtilisateursController` :
 * lire la requête à travers les listes blanches, passer la main au service,
 * répondre. Même garde de route que l'écran (`can:viewAny,ContentReport`).
 */
final class DonneesDesSignalementsContenuController
{
    public function __invoke(Request $requete, TableauDesSignalementsContenu $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotSignalementsContenu::COLONNES_TRIABLES,
            $tableau->filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            // Des noms de signaleurs et du texte saisi par le public.
            ->header('Cache-Control', 'no-store, private');
    }
}
