<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un cours du catalogue.
 *
 * `cout_unites` est le coût ACTUEL (`coutActuel()`), pas le prix en colonne :
 * un cours peut être offert jusqu'à une date. Renvoyer `cost_units` brut
 * ferait annoncer un prix là où le serveur ne débitera rien — et l'écran
 * mentirait dans le sens qui fâche.
 *
 * Le détail des leçons n'est joint que sur la fiche d'un cours, jamais dans la
 * liste : trente cours de quinze leçons, c'est une réponse de plusieurs
 * centaines de kilo-octets pour un écran qui n'en affiche aucune.
 */
final class CoursResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Course $cours */
        $cours = $this->resource;

        return [
            'slug' => $cours->slug,
            'titre' => $cours->title,
            'description' => $cours->description,
            'categorie' => $cours->category,
            'niveau' => $cours->level?->value,
            'niveau_libelle' => $cours->level?->libelle(),
            'duree' => $cours->duration_label,
            'couverture_url' => $cours->cover_path !== null ? asset($cours->cover_path) : null,
            'cout_unites' => $cours->coutActuel(),
            'est_offert' => $cours->estOffert(),
            'objectifs' => $cours->outcomes,
            'delivre_un_certificat' => $cours->delivers_certificate,
            'nombre_d_etapes' => $cours->nombreDEtapes(),
            'formateur' => AuteurResource::make($this->whenLoaded('instructor')),
            'lecons' => LeconResource::collection($this->whenLoaded('lessons')),
            'quiz' => QuizResource::collection($this->whenLoaded('quizzes')),
        ];
    }
}
