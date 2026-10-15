<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Reply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Une réponse dans un fil. */
final class ReponseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Reply $reponse */
        $reponse = $this->resource;

        return [
            'uuid' => $reponse->uuid,
            'corps' => $reponse->body,
            'auteur' => AuteurResource::make($this->whenLoaded('author')),
            /*
             | Une réponse « officielle » est celle d'un membre de l'équipe. Le
             | client l'affiche autrement, il ne le devine pas du rôle de
             | l'auteur — qui n'est d'ailleurs pas exposé : publier « untel est
             | modérateur » sur chaque message dresserait, à qui lit le forum,
             | la liste nominative de l'équipe de modération.
             */
            'officielle' => $reponse->is_official,
            'jaime_count' => $reponse->likes_count,
            'publie_le' => $reponse->created_at?->toIso8601String(),
        ];
    }
}
