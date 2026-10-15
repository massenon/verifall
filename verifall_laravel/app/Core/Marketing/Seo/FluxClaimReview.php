<?php

declare(strict_types=1);

namespace App\Core\Marketing\Seo;

use App\Models\HubArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Le flux de vérifications, au format attendu par Google Fact Check Tools.
 *
 * ## Pourquoi un flux, alors que les pages portent déjà le balisage
 *
 * Parce que l'exploration ne garantit rien. Un moteur passe quand il veut, sur
 * les pages qu'il veut, et un éditeur récent est exploré rarement. Un flux
 * déclaré une fois est relu régulièrement et à une adresse connue : c'est la
 * différence entre espérer être vu et être lisible.
 *
 * Le flux ne REMPLACE pas le balisage des pages, il le double. Les deux disent
 * la même chose, produite par le même code — `Referencement::pourFaitVerifie()`
 * — précisément pour qu'ils ne puissent pas diverger. Deux générateurs
 * distincts auraient fini par publier deux verdicts différents pour la même
 * vérification.
 *
 * ## Ce qui n'y entre PAS
 *
 * Toute vérification dont l'affirmation examinée n'est pas consignée. C'est la
 * même règle que sur la page, et pour la même raison : sans elle, le balisage
 * énonce la conclusion à la place de la rumeur et affirme le contraire de son
 * propre verdict. Un flux est pire encore qu'une page à cet égard, puisqu'il
 * est fait pour être ingéré sans relecture humaine.
 *
 * ## L'accréditation reste la porte
 *
 * Ce flux est nécessaire, il n'est pas suffisant : les panneaux de
 * fact-checking de Google n'affichent que les organisations accréditées.
 * Vérif'All ne l'est pas à ce jour. Le flux prépare ce jour-là ; il ne
 * l'avance pas, et il serait malhonnête de laisser croire l'inverse.
 */
final class FluxClaimReview
{
    private const CLEF_CACHE = 'flux-claimreview';

    /**
     * Combien de vérifications le flux porte.
     *
     * Les plus récentes. Un flux sans limite grossit indéfiniment et finit par
     * dépasser ce qu'un consommateur accepte de télécharger ; les vérifications
     * anciennes restent accessibles par leurs pages et par le plan du site.
     */
    private const MAXIMUM = 200;

    public function __construct(private readonly Referencement $referencement) {}

    /**
     * @return array<string, mixed>
     */
    public function construire(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(self::CLEF_CACHE, now()->addHour(), function (): array {
            $elements = [];

            foreach ($this->eligibles() as $article) {
                $claimReview = $this->claimReviewDe($article);

                if ($claimReview === null) {
                    continue;
                }

                /*
                 | `@context` est retiré de chaque élément : il est déclaré une
                 | fois en tête du document. Le répéter deux cents fois alourdit
                 | le fichier sans rien ajouter.
                 */
                unset($claimReview['@context']);

                $elements[] = $claimReview;
            }

            return [
                '@context' => 'https://schema.org',
                '@type' => 'DataFeed',
                'dateModified' => now()->toIso8601String(),
                'dataFeedElement' => $elements,
            ];
        });
    }

    public function oublier(): void
    {
        Cache::forget(self::CLEF_CACHE);
    }

    /**
     * Les vérifications publiables au flux.
     *
     * @return Collection<int, HubArticle>
     */
    private function eligibles(): Collection
    {
        return HubArticle::query()
            ->where('is_published', true)
            ->whereNotNull('verdict')
            ->whereNotNull('published_at')
            // L'affirmation examinée est OBLIGATOIRE ici : voir l'en-tête.
            ->whereNotNull('claim')
            ->where('claim', '<>', '')
            ->latest('published_at')
            ->limit(self::MAXIMUM)
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function claimReviewDe(HubArticle $article): ?array
    {
        foreach ($this->referencement->pourFaitVerifie($article)->donneesStructurees as $bloc) {
            if (($bloc['@type'] ?? null) === 'ClaimReview') {
                return $bloc;
            }
        }

        return null;
    }
}
