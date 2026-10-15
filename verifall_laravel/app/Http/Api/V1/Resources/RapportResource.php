<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\AnalysisReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Le rapport d'analyse — ce que l'écran « Rapport » affiche, et rien de plus.
 *
 * `scores` porte la contribution de chaque moteur (les barres du prototype) et
 * `deroule` le fil de l'analyse. Les deux sont déjà des tableaux en base,
 * fabriqués par l'orchestrateur : les recomposer ici en dupliquerait la
 * logique, et les deux versions auraient fini par ne plus dire la même chose.
 *
 * Phase 11 : `preuves` et `moteurs` rendent désormais ce que l'écran web
 * montrait depuis l'origine et qu'AUCUNE route de l'API n'exposait — l'audit
 * l'avait relevé, l'application Flutter ne pouvait pas afficher « Sources et
 * preuves consultées ». Chaque champ rendu est un champ déjà PUBLIC sur la
 * page web ; voir {@see PreuveResource} et {@see MoteurResource} pour ce qui
 * reste volontairement absent — coûts, clés, réponses brutes des fournisseurs.
 */
final class RapportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AnalysisReport $rapport */
        $rapport = $this->resource;

        return [
            'uuid' => $rapport->uuid,
            // NULL dès que `composition` vaut `mixte` — jamais un
            // pseudo-verdict (contrat V-8). Un client qui ignore `composition`
            // verra un verdict absent sans explication : à migrer dès que
            // l'app mobile lit ce champ.
            'verdict' => $rapport->verdict?->value,
            'verdict_libelle' => $rapport->verdict?->libelle(),
            'composition' => $rapport->composition_status->value,
            'composition_libelle' => $rapport->composition_status->libelle(),
            'confiance' => $rapport->confidence,
            'synthese' => $rapport->summary,
            'reserve' => $rapport->caveat,
            'scores' => $rapport->scores,
            'deroule' => $rapport->timeline,
            'indice_haine' => $rapport->hate_speech_score,
            'indice_haine_motifs' => $rapport->hate_speech_tags,
            'indice_haine_note' => $rapport->hate_speech_note,
            'publie_le' => $rapport->published_at?->toIso8601String(),
            // Additif : un ancien client qui ignore ce champ continue de lire
            // `verdict` tel quel pour un rapport homogène à claim unique.
            'claims' => $rapport->claims()->map(static fn ($claim): array => [
                'position' => $claim->position,
                'texte' => $claim->texte,
                'acte_de_langage' => $claim->acte_de_langage->value,
                'verdict' => $claim->verdict?->value,
                'confiance' => $claim->confiance,
            ])->all(),
            // Les plus fiables d'abord — le même ordre que la carte web :
            // un client qui s'arrête tôt doit voir les meilleures.
            'preuves' => PreuveResource::collection(
                $rapport->verification->evidences()->orderByDesc('reliability')->get(),
            ),
            'moteurs' => MoteurResource::collection($rapport->verification->engineRuns),
        ];
    }
}
