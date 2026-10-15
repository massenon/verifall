<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un certificat délivré.
 *
 * `code` est l'identifiant PUBLIC : c'est lui qu'un recruteur saisit sur la
 * page de vérification. Il est déjà conçu pour être lu à voix haute et recopié
 * (alphabet sans O/0, I/1, S/5), ce qui en fait la bonne clé d'URL — un uuid
 * de trente-six caractères ne se dicte pas au téléphone.
 *
 * `titulaire` recopie le nom au moment de la délivrance, et ce n'est pas une
 * dénormalisation paresseuse : un diplôme porte le nom sous lequel il a été
 * obtenu. C'est l'arbitrage RGPD déjà rendu — le certificat survit à
 * l'anonymisation du compte pour rester vérifiable.
 */
final class CertificatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Certificate $certificat */
        $certificat = $this->resource;

        return [
            'code' => $certificat->code,
            'titulaire' => $certificat->holder_name,
            'cours_titre' => $certificat->course_title,
            'cours_slug' => $this->whenLoaded('course', fn (): ?string => $certificat->course?->slug),
            'delivre_le' => $certificat->issued_at?->toIso8601String(),
            'revoque' => $certificat->revoked_at !== null,
            'revoque_le' => $certificat->revoked_at?->toIso8601String(),
        ];
    }
}
