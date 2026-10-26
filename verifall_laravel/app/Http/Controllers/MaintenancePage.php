<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Application\Services\ModeMaintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * La page de maintenance, joignable même hors maintenance.
 *
 * Volontairement : c'est ainsi qu'on la relit et qu'on la corrige AVANT de
 * fermer le site, plutôt que de la découvrir au moment où le public la voit.
 * Le middleware rend la même vue quand la maintenance est ouverte.
 */
final class MaintenancePage extends Controller
{
    public function __invoke(ModeMaintenance $maintenance): View
    {
        return view('maintenance', [
            'message' => $maintenance->message(),
            'finAnnoncee' => $maintenance->finAnnoncee(),
            'enRetard' => $maintenance->enRetard(),
            'debut' => $maintenance->debut(),
        ]);
    }

    /**
     * L'état, pour la page déjà ouverte.
     *
     * `no-store` : un état mis en cache par un proxy annoncerait la réouverture
     * en retard, ou la fermeture longtemps après la réouverture.
     */
    public function etat(ModeMaintenance $maintenance): JsonResponse
    {
        return response()->json([
            'actif' => $maintenance->estActif(),
            'fin_annoncee' => $maintenance->finAnnoncee()?->toIso8601String(),
            'en_retard' => $maintenance->enRetard(),
        ])->header('Cache-Control', 'no-store');
    }
}
