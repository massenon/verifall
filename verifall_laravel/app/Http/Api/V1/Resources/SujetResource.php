<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un sujet du forum.
 *
 * `verdict_officiel` n'apparaît que si le serveur a réellement lié un verdict.
 * Le prototype est explicite : le bandeau officiel ne se déduit JAMAIS des
 * votes. Une communauté convaincue à 90 % qu'une rumeur est vraie n'en fait
 * pas une vérification, et laisser l'interface le suggérer serait l'exact
 * inverse de ce que ce produit existe pour faire.
 *
 * `corps` est du HTML Quill purifié avant persistance ; le client le rend en
 * liste blanche de balises.
 */
final class SujetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Thread $sujet */
        $sujet = $this->resource;

        return [
            'slug' => $sujet->slug,
            'titre' => $sujet->title,
            'corps' => $sujet->body,
            'lien_signale' => $sujet->source_url,
            'auteur' => AuteurResource::make($this->whenLoaded('author')),
            'categorie' => $this->whenLoaded('category', fn (): ?array => $sujet->category === null ? null : [
                'slug' => $sujet->category->slug,
                'nom' => $sujet->category->name,
            ]),
            'verdict_officiel' => $sujet->official_verdict?->value,
            'verdict_officiel_libelle' => $sujet->official_verdict?->libelle(),
            'verifie_le' => $sujet->verified_at?->toIso8601String(),
            'verrouille' => $sujet->is_locked,
            'epingle' => $sujet->is_pinned,
            'reponses_count' => $sujet->replies_count,
            'jaime_count' => $sujet->likes_count,
            'votes' => $sujet->repartitionDesAvis(),
            'votes_total' => $sujet->totalDesVotes(),
            'mon_vote' => $this->whenHas('mon_vote'),
            'publie_le' => $sujet->created_at?->toIso8601String(),
            'derniere_activite_le' => $sujet->last_activity_at?->toIso8601String(),
            'reponses' => ReponseResource::collection($this->whenLoaded('replies')),
        ];
    }
}
