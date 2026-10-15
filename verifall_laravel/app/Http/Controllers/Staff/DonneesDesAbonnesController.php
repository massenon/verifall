<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Marketing\Services\TableauDesAbonnes;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table DataTables de l'onglet « Abonnés » de l'infolettre.
 *
 * Mince par construction, comme le pilote `DonneesDesUtilisateursController`.
 * Même garde de route que l'écran (`can:viewAny,NewsletterSubscriber`).
 */
final class DonneesDesAbonnesController
{
    public function __invoke(Request $requete, TableauDesAbonnes $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotAbonnes::COLONNES_TRIABLES,
            TableauDesAbonnes::filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            // Un fichier d'adresses e-mail : aucune copie en cache.
            ->header('Cache-Control', 'no-store, private');
    }
}
