<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\EngineRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un moteur mobilisé — phase 11, même raison que {@see PreuveResource} :
 * l'écran « Moteurs d'analyse mobilisés » n'avait pas d'équivalent dans
 * l'API. Le raisonnement du moteur n'est PAS exposé ici — c'est la même
 * réserve que `RapportResource` observe déjà sur le détail des exécutions :
 * de la comptabilité interne, pas une donnée publique.
 */
final class MoteurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EngineRun $moteur */
        $moteur = $this->resource;

        return [
            'nom' => $moteur->engine->value,
            'libelle' => $moteur->engine->libelle(),
            'verdict' => $moteur->verdict?->value,
            'verdict_libelle' => $moteur->verdict?->libelle(),
            'confiance' => $moteur->failed ? null : $moteur->confidence,
            'echoue' => $moteur->failed,
        ];
    }
}
