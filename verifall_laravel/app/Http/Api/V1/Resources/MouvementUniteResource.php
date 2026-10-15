<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\UnitLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une ligne du journal des unités.
 *
 * `solde_apres` est stocké, pas recalculé : c'est le relevé de compte du
 * citoyen, et un relevé se lit comme il a été écrit. Recomposer les soldes à
 * l'affichage donnerait, au premier écart de comptabilité, une histoire
 * cohérente et fausse plutôt qu'une incohérence visible.
 */
final class MouvementUniteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UnitLog $mouvement */
        $mouvement = $this->resource;

        return [
            'uuid' => $mouvement->uuid,
            'delta' => $mouvement->delta,
            'solde_apres' => $mouvement->balance_after,
            'motif' => $mouvement->reason?->value,
            'motif_libelle' => $mouvement->reason?->libelle(),
            'libelle' => $mouvement->label,
            'date' => $mouvement->created_at?->toIso8601String(),
        ];
    }
}
