<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une personne VUE PAR QUELQU'UN D'AUTRE — auteur d'un sujet, d'une réponse,
 * d'un article, animateur d'une séance.
 *
 * Ressource distincte de `MoiResource`, et c'est tout l'enjeu : une seule
 * ressource « utilisateur » servant les deux cas finirait, le jour où
 * quelqu'un ajouterait un champ « pour le profil », par livrer une adresse
 * e-mail dans une liste de messages du forum. Ici l'adresse, le téléphone, le
 * solde et la ville n'existent tout simplement pas.
 *
 * L'auteur d'un contenu peut avoir été anonymisé (RGPD) : `nomAffichage()`
 * répond alors « Citoyen supprimé », et c'est une réponse, pas une erreur.
 */
final class AuteurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $auteur */
        $auteur = $this->resource;

        return [
            'uuid' => $auteur->uuid,
            'nom' => $auteur->nomAffichage(),
            'initiales' => $auteur->initiales(),
        ];
    }
}
