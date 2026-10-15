<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Resources;

use App\Models\HubArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une brève du Hub d'infos.
 *
 * Le corps entier n'accompagne que la fiche : dans le fil, le chapô suffit, et
 * envoyer vingt articles complets pour n'en afficher que l'accroche coûte au
 * citoyen un forfait de données qu'il paie au mégaoctet.
 */
final class ArticleHubResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HubArticle $article */
        $article = $this->resource;

        return [
            'slug' => $article->slug,
            'titre' => $article->title,
            'chapo' => $article->excerpt,
            'corps' => $this->when(
                $request->routeIs('api.v1.hub.articles.show'),
                fn (): ?string => $article->body,
            ),
            'categorie' => $article->category,
            'verdict' => $article->verdict?->value,
            'verdict_libelle' => $article->verdict?->libelle(),
            'source_nom' => $article->source_name,
            'source_url' => $article->source_url,
            'couverture_url' => $article->cover_path !== null ? asset($article->cover_path) : null,
            'publie_le' => $article->published_at?->toIso8601String(),
        ];
    }
}
