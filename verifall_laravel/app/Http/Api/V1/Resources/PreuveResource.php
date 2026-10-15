<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\ReportEvidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une source du rapport — phase 11 : « Sources et preuves consultées »
 * n'existait sur AUCUNE route de l'API avant cette ressource. L'application
 * Flutter ne pouvait donc jamais afficher ce que l'écran web montre depuis le
 * début, et aucune exigence de provenance ne lui était opposable.
 *
 * NON EXPOSÉ : rien de plus que ce que l'écran web affiche déjà — la ligne
 * reste la même pour les deux lecteurs, exactement la discipline de
 * `components/rapport/corps.blade.php`.
 */
final class PreuveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReportEvidence $preuve */
        $preuve = $this->resource;

        return [
            'nom' => $preuve->source_name,
            'url' => $preuve->source_url,
            'domaine' => $preuve->domaineAffiche(),
            'editeur' => $preuve->editeur,
            'position' => $preuve->stance->value,
            'position_libelle' => $preuve->stance->libelle(),
            'fiabilite' => $preuve->reliability,
            'recuperee' => $preuve->aEteOuverte(),
            'publiee_le' => $preuve->published_at?->toIso8601String(),
        ];
    }
}
