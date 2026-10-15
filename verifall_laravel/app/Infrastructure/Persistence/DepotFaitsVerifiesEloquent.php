<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotFaitsVerifies;
use App\Enums\VerdictFactCheck;
use App\Infrastructure\Persistence\DataTables\FiltreSearchBuilder;
use App\Models\VerifiedFact;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class DepotFaitsVerifiesEloquent implements DepotFaitsVerifies
{
    private const COLONNES_SQL = [
        'verdict' => 'verdict',
        'reutilisations' => 'hits',
        'misAJour' => 'updated_at',
        'statut' => 'is_published',
    ];

    private const TYPES_SEARCHBUILDER = [
        'motsCles' => 'string',
        'reutilisations' => 'num',
    ];

    private const COLONNES_SQL_SEARCHBUILDER = [
        'motsCles' => 'keywords',
        'reutilisations' => 'hits',
    ];

    /** @return PageDeResultats<VerifiedFact> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = VerifiedFact::query()->count();

        $filtree = VerifiedFact::query();
        $this->filtrerLesFaits($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
            $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
        }

        $lignes = $filtree
            ->with('author:id,name')
            ->latest('id')
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
     * @param  Builder<VerifiedFact>  $requete
     */
    private function filtrerLesFaits(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $terme = '%'.$criteres->recherche.'%';
            $requete->where(fn (Builder $sous) => $sous->where('title', 'like', $terme)
                ->orWhere('keywords', 'like', $terme));
        }

        if (isset($filtres['verdict'])) {
            $requete->where('verdict', $filtres['verdict']);
        }

        match ($filtres['statut'] ?? null) {
            'publie' => $requete->where('is_published', true),
            'brouillon' => $requete->where('is_published', false),
            default => null,
        };

        if (isset($filtres['maj_du'])) {
            $requete->where('updated_at', '>=', Carbon::createFromFormat('Y-m-d', $filtres['maj_du'])?->startOfDay());
        }

        if (isset($filtres['maj_au'])) {
            $requete->where('updated_at', '<=', Carbon::createFromFormat('Y-m-d', $filtres['maj_au'])?->endOfDay());
        }

        $colonnes = [];

        foreach (self::TYPES_SEARCHBUILDER as $cle => $type) {
            $colonnes[$cle] = ['colonne' => self::COLONNES_SQL_SEARCHBUILDER[$cle], 'type' => $type];
        }

        (new FiltreSearchBuilder($colonnes))->appliquer($requete, $criteres->searchBuilder);
    }

    /**
     * Le cache porte sur la LECTURE de rapprochement, pas sur les listes du
     * back-office : quelqu'un qui vient d'enregistrer un fait doit le voir.
     */
    private const CLE_PUBLIES = 'faits-verifies.publies';

    private const TTL_MINUTES = 10;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): VerifiedFact
    {
        $fait = VerifiedFact::create($attributs);
        $this->oublierLeCache();

        return $fait;
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(VerifiedFact $fait, array $attributs): VerifiedFact
    {
        $fait->fill($attributs)->save();
        $this->oublierLeCache();

        return $fait->refresh();
    }

    public function supprimer(VerifiedFact $fait): void
    {
        $fait->delete();
        $this->oublierLeCache();
    }

    public function parUuid(string $uuid): ?VerifiedFact
    {
        return VerifiedFact::where('uuid', $uuid)->first();
    }

    /**
     * @param  ''|'publie'|'brouillon'  $statut
     * @return LengthAwarePaginator<int, VerifiedFact>
     */
    public function lister(
        string $recherche = '',
        ?VerdictFactCheck $verdict = null,
        string $statut = '',
        int $parPage = 12,
    ): LengthAwarePaginator {
        return VerifiedFact::query()
            ->with('author:id,name')
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $terme = '%'.$recherche.'%';
                $q->where(fn (Builder $sous) => $sous->where('title', 'like', $terme)
                    ->orWhere('keywords', 'like', $terme));
            })
            ->when($verdict !== null, fn (Builder $q) => $q->where('verdict', $verdict->value))
            ->when($statut === 'publie', fn (Builder $q) => $q->where('is_published', true))
            ->when($statut === 'brouillon', fn (Builder $q) => $q->where('is_published', false))
            ->latest('id')
            ->paginate($parPage);
    }

    /**
     * @return Collection<int, VerifiedFact>
     */
    public function publies(): Collection
    {
        return Cache::remember(
            self::CLE_PUBLIES,
            now()->addMinutes(self::TTL_MINUTES),
            static fn (): Collection => VerifiedFact::where('is_published', true)->get(),
        );
    }

    public function parEmpreinte(string $empreinte): ?VerifiedFact
    {
        return $this->publies()->firstWhere('content_fingerprint', $empreinte);
    }

    /**
     * @return array{publies: int, dementis: int, sources_multiples: int, anciennete_jours: float}
     */
    public function indicateurs(): array
    {
        $publies = VerifiedFact::where('is_published', true)->count();

        /*
         | « Démentis » = faux ET trompeur. Du point de vue de quelqu'un qui a
         | failli partager, les deux sont des pièges — le compte les réunit,
         | comme côté citoyen.
         */
        $dementis = VerifiedFact::where('is_published', true)
            ->whereIn('verdict', [VerdictFactCheck::Faux->value, VerdictFactCheck::Trompeur->value])
            ->count();

        /*
         | Compté en PHP : « au moins deux entrées dans un tableau JSON » n'a
         | pas de syntaxe commune à SQLite et MySQL, et l'écrire pour l'un
         | casserait les tests sur l'autre. La table est curée à la main, elle
         | reste petite.
         */
        $multiples = VerifiedFact::where('is_published', true)
            ->get(['sources'])
            ->filter(static fn (VerifiedFact $fait): bool => $fait->nombreDeSources() >= 2)
            ->count();

        $moyenne = VerifiedFact::where('is_published', true)
            ->get(['created_at'])
            ->avg(static fn (VerifiedFact $fait): float => (float) $fait->created_at?->diffInDays(now()));

        return [
            'publies' => $publies,
            'dementis' => $dementis,
            'sources_multiples' => $multiples,
            'anciennete_jours' => round((float) $moyenne, 1),
        ];
    }

    public function oublierLeCache(): void
    {
        Cache::forget(self::CLE_PUBLIES);
    }
}
