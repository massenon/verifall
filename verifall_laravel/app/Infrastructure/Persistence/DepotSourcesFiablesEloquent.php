<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Models\SourceCategory;
use App\Models\TrustedSource;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class DepotSourcesFiablesEloquent implements DepotSourcesFiables
{
    private const COLONNES_SQL = [
        'nom' => 'name',
        'domaine' => 'domain',
        'credibilite' => 'credibility',
        'effet' => 'is_active',
    ];

    /** @return list<string> */
    public function uuidsDesFamilles(): array
    {
        return SourceCategory::query()
            ->whereHas('sources')
            ->pluck('uuid')
            ->map(static fn (mixed $u): string => (string) $u)
            ->values()
            ->all();
    }

    /** @return PageDeResultats<TrustedSource> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = TrustedSource::query()->count();

        $filtree = TrustedSource::query();
        $this->filtrerLesSources($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        if ($requete->tri === []) {
            $filtree->orderByDesc('is_active')->orderBy('domain');
        } else {
            foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
                if ($cle === 'famille') {
                    /*
                     | Le nom de la famille vit sur `source_categories`, pas sur
                     | `trusted_sources` : `COLONNES_SQL` ne peut pas la
                     | désigner par un simple nom de colonne. `leftJoin` plutôt
                     | que `whereHas`/`with` : une source sans famille doit
                     | rester dans le tri, pas en disparaître.
                     |
                     | Second critère `domain` (unique) : `name` ne l'est pas sur
                     | `source_categories`, et un tri sans second critère sur une
                     | colonne à égalité n'est pas garanti stable par SQLite.
                     */
                    $filtree->leftJoin('source_categories', 'source_categories.id', '=', 'trusted_sources.source_category_id')
                        ->select('trusted_sources.*')
                        ->orderBy('source_categories.name', $sens)
                        ->orderBy('trusted_sources.domain');

                    continue;
                }

                $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
            }
        }

        $lignes = $filtree
            ->with('categorie')
            ->skip($requete->debut)
            ->take($requete->longueur)
            ->get();

        return new PageDeResultats(
            total: $total,
            filtres: $nombreFiltre,
            lignes: $lignes,
            tronque: $requete->export && $nombreFiltre > $requete->longueur,
        );
    }

    /**
     * @param  Builder<TrustedSource>  $requete
     */
    private function filtrerLesSources(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $terme = '%'.$criteres->recherche.'%';
            $requete->where(fn (Builder $q) => $q
                ->where('name', 'like', $terme)
                ->orWhere('domain', 'like', $terme)
                ->orWhere('notes', 'like', $terme));
        }

        if (isset($filtres['famille'])) {
            $filtres['famille'] === DepotSourcesFiables::SANS_FAMILLE
                ? $requete->whereNull('source_category_id')
                : $requete->whereHas('categorie', fn (Builder $c) => $c->where('uuid', $filtres['famille']));
        }

        match ($filtres['etat'] ?? null) {
            'actives' => $requete->where('is_active', true),
            'inactives' => $requete->where('is_active', false),
            default => null,
        };

        // Trois tranches affichées (80-100, 50-79, 0-49) : la colonne est un
        // entier, et une liste de cent valeurs ne serait pas un filtre rapide.
        match ($filtres['confiance'] ?? null) {
            'elevee' => $requete->whereBetween('credibility', [80, 100]),
            'moyenne' => $requete->whereBetween('credibility', [50, 79]),
            'faible' => $requete->whereBetween('credibility', [0, 49]),
            default => null,
        };
    }

    /**
     * Une SEULE clé, portant tout le référentiel indexé par domaine.
     *
     * Une clé par domaine interrogé semblerait plus fin ; ce serait un piège.
     * Le moteur interroge des domaines qui ne sont PAS dans le référentiel — la
     * grande majorité, puisque c'est justement ce qu'il cherche à savoir. On
     * accumulerait des milliers d'entrées « absent » sur une table qui en
     * compte quelques centaines, et `oublier()` n'aurait aucun moyen de les
     * retrouver toutes pour les purger.
     */
    private const CLE = 'sources-fiables.par-domaine';

    private const DUREE_MINUTES = 15;

    public function trouverParDomaine(string $domaine): ?TrustedSource
    {
        $normalise = TrustedSource::normaliserLeDomaine($domaine);

        if ($normalise === '') {
            return null;
        }

        $index = $this->index();

        if (isset($index[$normalise])) {
            return $index[$normalise];
        }

        /*
         | REMONTÉE AUX DOMAINES PARENTS.
         |
         | `edition.cnn.com` doit trouver `cnn.com` : sans cela, référencer une
         | source imposerait d'énumérer ses rubriques, et la fiche paraîtrait
         | inopérante à la première URL réelle.
         |
         | On retire une étiquette à la fois par la GAUCHE, et l'on s'arrête à
         | deux étiquettes. Descendre plus bas ferait correspondre `fr` ou `com`
         | si quelqu'un les référençait par erreur — une seule fiche mal saisie
         | rendrait alors tout le web « fiable ».
         */
        $etiquettes = explode('.', $normalise);

        while (count($etiquettes) > 2) {
            array_shift($etiquettes);
            $parent = implode('.', $etiquettes);

            if (isset($index[$parent])) {
                return $index[$parent];
            }
        }

        return null;
    }

    public function estFiable(string $domaine): bool
    {
        return $this->trouverParDomaine($domaine) instanceof TrustedSource;
    }

    public function oublier(): void
    {
        Cache::forget(self::CLE);
    }

    public function parFamille(): Collection
    {
        /*
         | Le MÊME index que le moteur, regroupé en mémoire.
         |
         | Quatre cents modèles déjà chargés et déjà en cache : les regrouper
         | coûte moins qu'une requête, et surtout cela garantit que la page
         | publique et le moteur disent la même chose. Deux lectures séparées
         | finiraient par diverger d'un quart d'heure, et c'est précisément sur
         | une page de transparence que l'écart se remarquerait.
         */
        return collect($this->index())
            ->groupBy(static fn (TrustedSource $source): string => $source->categorie?->name ?? 'Autres')
            ->map(static fn (Collection $sources): Collection => $sources
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values())
            ->sortKeys();
    }

    /**
     * Le référentiel actif, indexé par domaine.
     *
     * @return array<string, TrustedSource>
     */
    private function index(): array
    {
        /** @var array<string, TrustedSource> */
        return Cache::remember(
            self::CLE,
            now()->addMinutes(self::DUREE_MINUTES),
            static fn (): array => TrustedSource::query()
                ->actives()
                ->with('categorie')
                ->get()
                ->keyBy('domain')
                ->all(),
        );
    }
}
