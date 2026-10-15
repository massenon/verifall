<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marque la visite guidée comme vue.
 *
 * Volontairement idempotent et silencieux : appelé plusieurs fois, il ne fait
 * rien de plus. La visite est un confort, pas une transaction — la rater ne
 * doit jamais bloquer un utilisateur.
 */
final class TourController
{
    public function __invoke(Request $request): JsonResponse
    {
        $utilisateur = $request->user() ?? $request->user('staff');

        if ($utilisateur !== null && $utilisateur->tour_done_at === null) {
            $utilisateur->forceFill(['tour_done_at' => now()])->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Relance depuis les réglages ou l'aide (UX_TRANSVERSE : « relançable »).
     */
    public function relancer(Request $request): JsonResponse
    {
        $utilisateur = $request->user() ?? $request->user('staff');

        $utilisateur?->forceFill(['tour_done_at' => null])->save();

        return response()->json(['ok' => true]);
    }
}
