<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MON compte — et jamais celui de quelqu'un d'autre.
 *
 * Cette ressource porte des données personnelles : adresse e-mail, téléphone,
 * ville. Elle n'est donc employée que là où le sujet de la requête est aussi
 * son propriétaire — `GET /me`, `PATCH /me`. Toute autre exposition d'un
 * compte passe par `AuteurResource`, qui n'en connaît rien.
 *
 * `suppression_demandee_le` est exposé délibérément : c'est ce qui permet à
 * l'application d'afficher « votre compte sera effacé le … » au lieu de
 * heurter un mur de 403 sans explication.
 */
final class MoiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $moi */
        $moi = $this->resource;

        return [
            'uuid' => $moi->uuid,
            'prenom' => $moi->first_name,
            'nom' => $moi->last_name,
            'nom_affichage' => $moi->nomAffichage(),
            'initiales' => $moi->initiales(),
            'email' => $moi->email,
            'telephone' => $moi->phone,
            'profession' => $moi->profession,
            'ville' => $moi->city,
            'bio' => $moi->bio,
            'unites' => $moi->units,
            'xp' => $moi->xp,
            'serie_jours' => $moi->streak_days,
            'inscrit_le' => $moi->created_at?->toIso8601String(),
            'suppression_demandee_le' => $moi->deletion_requested_at?->toIso8601String(),
        ];
    }
}
