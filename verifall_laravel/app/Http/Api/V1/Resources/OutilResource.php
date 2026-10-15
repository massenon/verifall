<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Un outil de l'annuaire — recherche inversée, analyse d'image, archives. */
final class OutilResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tool $outil */
        $outil = $this->resource;

        return [
            'slug' => $outil->slug,
            'titre' => $outil->title,
            'description' => $outil->description,
            'categorie' => $outil->category,
            'etiquette' => $outil->tag,
            'icone' => $outil->icon,
            'url' => $outil->url,
            'libelle_du_lien' => $outil->link_label,
            'cas_d_usage' => $outil->use_cases,
            'recommande' => $outil->is_recommended,
        ];
    }
}
