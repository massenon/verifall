<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\LiveClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une séance en direct.
 *
 * `lien_visio` n'est renvoyé QUE lorsque la séance est joignable
 * (`lienDisponible()`). Un lien visio publié trois semaines à l'avance est un
 * lien qui circule : n'importe qui l'ouvre avant l'heure, et l'animateur
 * découvre une salle déjà pleine d'inconnus.
 *
 * L'état — à venir, en direct, terminée — se DÉDUIT de l'heure et n'est pas
 * une colonne. Une séance stockée « planifiée » resterait planifiée le
 * lendemain si personne ne repassait derrière.
 */
final class SeanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LiveClass $seance */
        $seance = $this->resource;

        return [
            'uuid' => $seance->uuid,
            'titre' => $seance->title,
            'description' => $seance->description,
            'etat' => $seance->etat(),
            'debute_le' => $seance->starts_at?->toIso8601String(),
            'minutes' => $seance->minutes,
            'places' => $seance->seats,
            'animateur' => AuteurResource::make($this->whenLoaded('host')),
            'cours_slug' => $this->whenLoaded('course', fn (): ?string => $seance->course?->slug),
            'lien_visio' => $seance->lienDisponible() ? $seance->join_url : null,
            'rappel_demande' => $this->whenHas('rappel_demande'),
        ];
    }
}
