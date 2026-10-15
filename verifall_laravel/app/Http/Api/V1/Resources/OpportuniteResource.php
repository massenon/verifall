<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\HubJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une opportunité du Hub — emploi, bourse, appel à candidature.
 *
 * `jours_restants` et `expiree` viennent du serveur. Le prototype mobile est
 * formel : l'archivage est une commande planifiée Laravel, le client ne
 * l'applique jamais lui-même. Une horloge de téléphone mal réglée ferait
 * sinon disparaître une offre encore ouverte — ou pire, présenterait comme
 * candidatable une offre fermée.
 *
 * `verifiee` dit qu'un humain de l'équipe a contrôlé l'annonce. C'est la seule
 * chose qui distingue une offre d'une arnaque, et elle ne se déduit d'aucun
 * autre champ : le client ne doit surtout pas essayer de la deviner.
 */
final class OpportuniteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HubJob $offre */
        $offre = $this->resource;

        return [
            'slug' => $offre->slug,
            'titre' => $offre->title,
            'organisation' => $offre->organisation,
            'portee' => $offre->scope?->value,
            'portee_libelle' => $offre->scope?->libelle(),
            'type' => $offre->type?->value,
            'type_libelle' => $offre->type?->libelle(),
            'lieu' => $offre->location,
            'description' => $this->when(
                $request->routeIs('api.v1.hub.opportunities.show'),
                fn (): ?string => $offre->description,
            ),
            'candidature_url' => $offre->apply_url,
            'echeance' => $offre->deadline?->toIso8601String(),
            'jours_restants' => $offre->joursRestants(),
            'expiree' => $offre->estExpiree(),
            'verifiee' => $offre->is_verified,
            'publiee_le' => $offre->published_at?->toIso8601String(),
        ];
    }
}
