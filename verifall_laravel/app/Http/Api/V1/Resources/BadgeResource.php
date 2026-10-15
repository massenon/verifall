<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un badge, débloqué ou non.
 *
 * Les badges VERROUILLÉS sont renvoyés eux aussi, avec leur condition en
 * clair. Montrer la destination motive davantage que la masquer, et un cadenas
 * sans explication n'est qu'une frustration gratuite.
 */
final class BadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Badge $badge */
        $badge = $this->resource;

        return [
            'slug' => $badge->slug,
            'nom' => $badge->name,
            'description' => $badge->description,
            'icone' => $badge->icon,
            'condition' => $badge->requirement_label,
            'xp' => $badge->xp_reward,
            'unites' => $badge->units_reward,
            'debloque' => $this->whenHas('debloque'),
            'debloque_le' => $this->whenHas('debloque_le'),
        ];
    }
}
