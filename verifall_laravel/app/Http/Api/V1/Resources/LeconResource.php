<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une leçon.
 *
 * `ouverte` et `acquise` sont calculés PAR LE SERVEUR et transmis tels quels :
 * le déverrouillage séquentiel est une règle métier, pas une décision
 * d'affichage. Un client qui la recalculerait deviendrait une seconde source
 * de vérité, et la première fois qu'elles divergeraient, ce serait pour ouvrir
 * une leçon qui ne devait pas l'être.
 *
 * `contenu` est du HTML issu de Quill, PURIFIÉ AVANT PERSISTANCE. Le client le
 * rend malgré tout en liste blanche de balises : la purification à l'écriture
 * protège de ce qui est en base, pas de ce qui y entrerait un jour par une
 * autre porte.
 *
 * Le contenu n'accompagne que la fiche d'UNE leçon — jamais la liste des
 * leçons d'un cours, sans quoi c'est le corps entier du cours qui partirait à
 * chaque ouverture du catalogue.
 */
final class LeconResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Lesson $lecon */
        $lecon = $this->resource;

        return [
            'uuid' => $lecon->uuid,
            'position' => $lecon->position,
            'titre' => $lecon->title,
            'type' => $lecon->type?->value,
            'minutes' => $lecon->minutes,
            'video_url' => $lecon->video_url,
            'contenu' => $this->when(
                $request->routeIs('api.v1.courses.lesson'),
                fn (): ?string => $lecon->content,
            ),
            'ouverte' => $this->whenHas('ouverte'),
            'acquise' => $this->whenHas('acquise'),
        ];
    }
}
