<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotUtilisateurs;
use App\Core\Identity\Services\TableauDesUtilisateurs;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON de la table DataTables « Utilisateurs » (pilote du 14 sept. 2026).
 *
 * Mince par construction : il lit la requête à travers les listes blanches,
 * passe la main au service, et répond. Aucune règle ici — ni sur qui voit, ni
 * sur qui exporte, ni sur ce qui sort. Le contrat complet est dans
 * `docs/DATATABLES_CONTRAT.md`.
 *
 * Même garde de route que l'écran (`can:viewAny,User`) : une source de données
 * moins gardée que la page qui l'affiche serait la vraie porte d'entrée.
 */
final class DonneesDesUtilisateursController
{
    public function __invoke(Request $requete, TableauDesUtilisateurs $tableau): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotUtilisateurs::COLONNES_TRIABLES,
            TableauDesUtilisateurs::filtresAutorises(),
        );

        return response()
            ->json($tableau->donnees($criteres, $membre))
            // Des noms et des adresses : ni proxy d'entreprise ni cache du
            // navigateur n'ont à en garder une copie.
            ->header('Cache-Control', 'no-store, private');
    }
}
