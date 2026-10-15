<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mon inscription à un cours — l'état de MA progression.
 *
 * `avancement` vient de la colonne recalculée par `ProgressionAcademie` après
 * chaque événement, jamais d'un comptage refait ici : deux formules de calcul
 * de progression finiraient par afficher deux pourcentages différents pour le
 * même cours sur deux écrans voisins.
 */
final class InscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Enrollment $inscription */
        $inscription = $this->resource;

        return [
            'uuid' => $inscription->uuid,
            'cours_slug' => $this->whenLoaded('course', fn (): string => $inscription->course->slug),
            'cours_titre' => $this->whenLoaded('course', fn (): string => $inscription->course->title),
            'statut' => $inscription->status->value,
            'statut_libelle' => $inscription->status->libelle(),
            'avancement' => $inscription->progress,
            'unites_payees' => $inscription->paid_units,
            'commencee_le' => $inscription->started_at?->toIso8601String(),
            'derniere_activite_le' => $inscription->last_activity_at?->toIso8601String(),
            'achevee_le' => $inscription->completed_at?->toIso8601String(),
        ];
    }
}
