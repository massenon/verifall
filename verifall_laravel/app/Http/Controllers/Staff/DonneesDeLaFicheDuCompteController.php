<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Contracts\DepotFicheDuCompte;
use App\Core\Identity\Services\FicheDuCompte;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La source JSON des onglets de la fiche d'un compte.
 *
 * Une route paramétrée par onglet plutôt que seize routes : `{onglet}` est
 * borné par `whereIn` à la liste fermée de `DepotFicheDuCompte`, et le service
 * le revérifie. Même garde de route que la fiche (`can:view,user`) ; l'onglet
 * Journal demande en plus `security.logs`, vérifié par le service.
 *
 * Mince par construction, comme la source de la liste des comptes.
 */
final class DonneesDeLaFicheDuCompteController
{
    public function __invoke(Request $requete, User $user, string $onglet, FicheDuCompte $fiche): JsonResponse
    {
        /** @var User|null $membre */
        $membre = Auth::guard('staff')->user();

        abort_if($membre === null, 403);
        abort_unless(array_key_exists($onglet, DepotFicheDuCompte::COLONNES_TRIABLES), 404);

        $criteres = RequeteDataTables::depuis(
            $requete,
            DepotFicheDuCompte::COLONNES_TRIABLES[$onglet],
            FicheDuCompte::filtresAutorises($onglet),
        );

        return response()
            ->json($fiche->donnees($onglet, $criteres, $membre, $user))
            // L'historique d'une personne : aucun cache n'a à en garder copie.
            ->header('Cache-Control', 'no-store, private');
    }
}
