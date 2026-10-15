<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Verification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une vérification — l'objet central du produit.
 *
 * Sert deux écrans : le suivi d'analyse (« Analyse en cours ») et la liste
 * « Mes vérifications ». Le rapport n'est joint que s'il a été chargé : un
 * client qui recevrait `rapport: {}` pour une analyse encore en file croirait
 * à un verdict vide, et c'est le pire malentendu possible sur une plateforme
 * de fact-checking.
 *
 * CE QUI N'EST PAS EXPOSÉ, et pourquoi :
 *   · `content_fingerprint` — sert au rapprochement interne d'analyses ;
 *     publié, il permettrait de tester si une rumeur a déjà été soumise ;
 *   · `guest_hash` — l'empreinte d'adresse d'un visiteur, donnée de sécurité ;
 *   · `file_path` — un chemin sur le disque privé. Le fichier ne se sert que
 *     par route signée, jamais par son chemin ;
 *   · l'identifiant entier et `reused_from_id` — mécanique interne.
 */
final class VerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Verification $verification */
        $verification = $this->resource;

        return [
            'uuid' => $verification->uuid,
            'reference' => $verification->reference(),
            'type' => $verification->type->value,
            'type_libelle' => $verification->type->libelle(),
            'statut' => $verification->status->value,
            'statut_libelle' => $verification->status->libelle(),
            'terminee' => $verification->estTerminee(),
            'affirmation' => $verification->claim,
            'apercu' => $verification->apercu(),
            'contexte' => $verification->context,
            'cout_unites' => $verification->cost_units,
            'avancement' => $verification->avancement(),
            'etape' => $verification->etape()?->value,
            'etape_libelle' => $verification->etape()?->libelle(),
            'est_une_reprise' => $verification->estUneReprise(),
            'soumise_le' => $verification->created_at?->toIso8601String(),

            // whenLoaded et non un accès direct : le chargement paresseux est
            // INTERDIT dans ce projet, et une liste de trente vérifications
            // produirait sinon trente requêtes de plus.
            'rapport' => RapportResource::make($this->whenLoaded('report')),
        ];
    }
}
