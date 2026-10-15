<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Enums\MotifMouvementUnite;
use App\Infrastructure\Persistence\DataTables\FiltreSearchBuilder;
use App\Models\UnitLog;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentUnitLogRepository implements UnitLogRepositoryInterface
{
    /** Clé publique → colonne SQL. */
    private const COLONNES_SQL = [
        'date' => 'created_at',
        'motif' => 'reason',
        'mouvement' => 'delta',
        'soldeApres' => 'balance_after',
    ];

    private const TYPES_SEARCHBUILDER = [
        'libelle' => 'string',
    ];

    /** @return PageDeResultats<UnitLog> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = UnitLog::query()->count();

        $filtree = UnitLog::query();
        $this->filtrer($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
            $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
        }

        $lignes = $filtree
            ->with('user:id,uuid,name,email')
            // Départage stable, et ordre par défaut quand aucun tri n'est demandé.
            ->orderByDesc('id')
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
     * @param  Builder<UnitLog>  $requete
     */
    private function filtrer(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $terme = '%'.$criteres->recherche.'%';

            $requete->where(function (Builder $sous) use ($terme): void {
                $sous->where('label', 'like', $terme)
                    ->orWhere('uuid', 'like', $terme)
                    ->orWhereHas('user', fn (Builder $u) => $u
                        ->where('name', 'like', $terme)
                        ->orWhere('email', 'like', $terme));
            });
        }

        match ($filtres['sens'] ?? null) {
            'credit' => $requete->where('delta', '>', 0),
            'debit' => $requete->where('delta', '<', 0),
            default => null,
        };

        if (isset($filtres['motif'])) {
            $requete->where('reason', $filtres['motif']);
        }

        if (isset($filtres['periode_du'])) {
            $requete->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $filtres['periode_du'])?->startOfDay());
        }

        if (isset($filtres['periode_au'])) {
            $requete->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $filtres['periode_au'])?->endOfDay());
        }

        (new FiltreSearchBuilder(['libelle' => ['colonne' => 'label', 'type' => 'string']]))
            ->appliquer($requete, $criteres->searchBuilder);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function enregistrer(array $attributs): UnitLog
    {
        return UnitLog::create($attributs);
    }

    /**
     * @param  'credit'|'debit'|''  $sens
     * @return LengthAwarePaginator<int, UnitLog>
     */
    public function historique(
        User $utilisateur,
        string $recherche = '',
        string $sens = '',
        int $parPage = 10,
    ): LengthAwarePaginator {
        // Pas de cache ici : un solde et son journal sont ce qu'un utilisateur
        // relit précisément après avoir payé. Servir une version d'il y a cinq
        // minutes ferait croire à un paiement perdu.
        return $this->requete($utilisateur, $recherche, $sens)
            ->latest('id')
            ->paginate($parPage);
    }

    /**
     * @return CursorPaginator<int, UnitLog>
     */
    public function historiqueParCurseur(User $utilisateur, int $parPage = 20): CursorPaginator
    {
        // Tri sur `id` : le curseur exige une clé strictement ordonnée et
        // unique. Deux mouvements de la même seconde partageraient `created_at`
        // et l'un des deux disparaîtrait au passage de page.
        return $this->requete($utilisateur, '', '')
            ->orderByDesc('id')
            ->cursorPaginate($parPage);
    }

    /**
     * @param  'credit'|'debit'|''  $sens
     * @return Collection<int, UnitLog>
     */
    public function pourExport(
        User $utilisateur,
        string $recherche = '',
        string $sens = '',
        int $limite = 5000,
    ): Collection {
        return $this->requete($utilisateur, $recherche, $sens)
            ->latest('id')
            ->limit($limite)
            ->get();
    }

    public function somme(User $utilisateur): int
    {
        return (int) UnitLog::where('user_id', $utilisateur->id)->sum('delta');
    }

    /**
     * @param  'credit'|'debit'|''  $sens
     * @return LengthAwarePaginator<int, UnitLog>
     */
    public function journalGlobal(
        string $recherche = '',
        string $sens = '',
        string $motif = '',
        int $parPage = 25,
    ): LengthAwarePaginator {
        return UnitLog::query()
            /*
             | `with('user')` n'est pas une optimisation de confort : sans lui,
             | vingt-cinq lignes font vingt-six requêtes, et l'écran ralentit
             | proportionnellement à ce qu'on lui demande d'afficher.
             */
            ->with('user:id,uuid,name,email')
            ->when($sens === 'credit', fn (Builder $q) => $q->where('delta', '>', 0))
            ->when($sens === 'debit', fn (Builder $q) => $q->where('delta', '<', 0))
            ->when($motif !== '', fn (Builder $q) => $q->where('reason', $motif))
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $terme = '%'.$recherche.'%';

                /*
                 | La recherche du staff porte AUSSI sur le titulaire du compte.
                 | C'est la question réelle de cet écran — « qu'est-il arrivé
                 | aux unités de cette personne ? » — et la seule qu'on ne
                 | puisse pas poser sur l'écran citoyen, qui ne connaît qu'un
                 | compte.
                 */
                $q->where(function (Builder $sous) use ($terme): void {
                    $sous->where('label', 'like', $terme)
                        ->orWhere('uuid', 'like', $terme)
                        ->orWhereHas('user', fn (Builder $u) => $u
                            ->where('name', 'like', $terme)
                            ->orWhere('email', 'like', $terme));
                });
            })
            ->orderByDesc('id')
            ->paginate($parPage);
    }

    /**
     * @return array{mouvements: int, credits: int, debits: int, comptes: int}
     */
    public function totauxGlobaux(): array
    {
        /*
         | Une seule requête agrégée. Quatre `count()`/`sum()` séparés
         | balaieraient quatre fois une table qui grossit d'une ligne par
         | vérification.
         */
        $ligne = UnitLog::query()
            ->selectRaw('COUNT(*) as mouvements')
            ->selectRaw('COALESCE(SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END), 0) as credits')
            ->selectRaw('COALESCE(SUM(CASE WHEN delta < 0 THEN -delta ELSE 0 END), 0) as debits')
            ->selectRaw('COUNT(DISTINCT user_id) as comptes')
            ->first();

        return [
            'mouvements' => (int) ($ligne->mouvements ?? 0),
            'credits' => (int) ($ligne->credits ?? 0),
            // Rendus POSITIFS : « 4 210 unités dépensées » se lit, « -4 210 »
            // se relit deux fois pour savoir si le signe est celui du total ou
            // celui de l'affichage.
            'debits' => (int) ($ligne->debits ?? 0),
            'comptes' => (int) ($ligne->comptes ?? 0),
        ];
    }

    /**
     * @param  'credit'|'debit'|''  $sens
     * @return Builder<UnitLog>
     */
    private function requete(User $utilisateur, string $recherche, string $sens): Builder
    {
        return UnitLog::query()
            // Toujours borné au propriétaire : c'est ici, et non dans
            // l'appelant, que la fuite se produirait si on l'oubliait.
            ->where('user_id', $utilisateur->id)
            ->when($sens === 'credit', fn (Builder $q) => $q->where('delta', '>', 0))
            ->when($sens === 'debit', fn (Builder $q) => $q->where('delta', '<', 0))
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $terme = '%'.$recherche.'%';

                /*
                 | LA RECHERCHE DOIT TROUVER CE QUI EST AFFICHÉ, pas ce qui est
                 | stocké — et depuis que l'écran traduit, les deux diffèrent.
                 |
                 | `label` est écrit une fois pour toutes dans la langue de
                 | repli, pour que le journal reste cohérent et exportable.
                 | Un lecteur anglophone voit « Top-up » et tape « Top-up » :
                 | chercher la seule colonne stockée ne lui rendrait rien, et
                 | un journal comptable qui ne retrouve pas ce qu'il vient
                 | d'afficher passe pour un journal incomplet.
                 |
                 | On traduit donc la recherche À REBOURS : quels motifs, dans
                 | la langue courante, portent ce terme ? Leurs valeurs — jamais
                 | traduites, elles — rejoignent la requête.
                 */
                $motifsCorrespondants = array_values(array_map(
                    static fn (MotifMouvementUnite $motif): string => $motif->value,
                    array_filter(
                        MotifMouvementUnite::cases(),
                        static fn (MotifMouvementUnite $motif): bool => mb_stripos($motif->libelle(), $recherche) !== false,
                    ),
                ));

                $q->where(function (Builder $sous) use ($terme, $motifsCorrespondants): void {
                    $sous->where('label', 'like', $terme)
                        ->orWhere('uuid', 'like', $terme);

                    if ($motifsCorrespondants !== []) {
                        $sous->orWhereIn('reason', $motifsCorrespondants);
                    }
                });
            });
    }
}
