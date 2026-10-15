<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotSignalementsContenu;
use App\Core\Community\Services\SignalementDeContenu;
use App\Core\Community\Services\VitesseDePropagation;
use App\Enums\StatutSignalementContenu;
use App\Models\ContentReport;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class DepotSignalementsContenuEloquent implements DepotSignalementsContenu
{
    /** Clé publique → colonne SQL. Tout ce qui n'y figure pas n'atteint jamais `orderBy()`. */
    private const COLONNES_SQL = [
        'type' => 'reportable_type',
        'motif' => 'reason',
        'statut' => 'status',
        'recuLe' => 'content_reports.created_at',
    ];

    /** @return PageDeResultats<ContentReport> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = ContentReport::query()->count();

        $filtree = ContentReport::query();
        $this->filtrer($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        /*
         | Même règle de file que `paginer()` : la flambée d'abord, puis le
         | plus signalé, puis l'ancienneté — sauf si l'écran demande un AUTRE
         | tri explicite, auquel cas celui-ci prime.
         */
        $filtree
            ->select('content_reports.*')
            ->selectSub(
                ContentReport::query()
                    ->selectRaw('COUNT(*)')
                    ->from('content_reports as alertes')
                    ->whereColumn('alertes.reportable_type', 'content_reports.reportable_type')
                    ->whereColumn('alertes.reportable_id', 'content_reports.reportable_id'),
                'alertes_total',
            );

        if ($requete->tri === []) {
            // Aucun tri demandé : l'ancienneté seule départage. La priorité de
            // flambée est un ORDRE qu'on choisit (colonne « Propagation »), pas
            // le classement par défaut de cette source de données.
            $filtree->oldest('content_reports.created_at');
        } else {
            foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
                if ($cle === 'propagation') {
                    $filtree->orderBy('alertes_total', $sens);

                    continue;
                }

                if ($cle === 'signalePar') {
                    // Une sous-requête plutôt qu'un `leftJoin` : la jointure sur
                    // `users` entrait en collision avec celle, déjà posée par
                    // `filtrer()`, d'un éventuel `whereHas('reporter', …)`.
                    $filtree->orderBy(
                        User::query()->select('name')->whereColumn('id', 'content_reports.user_id'),
                        $sens,
                    );

                    continue;
                }

                $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
            }
        }

        $lignes = $filtree
            ->with(['reporter:id,name', 'reportable'])
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
     * @param  Builder<ContentReport>  $requete
     */
    private function filtrer(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $motif = '%'.$criteres->recherche.'%';

            // Les clés de motif dont le LIBELLÉ (pas la clé stockée) correspond.
            $clesDeMotif = array_keys(array_filter(
                SignalementDeContenu::motifs(),
                static fn (string $libelle): bool => mb_stripos($libelle, $criteres->recherche) !== false,
            ));

            $requete->where(static function (Builder $ou) use ($motif, $clesDeMotif): void {
                $ou->where('detail', 'like', $motif)
                    ->orWhereHas('reporter', static fn (Builder $r) => $r->where('name', 'like', $motif));

                if ($clesDeMotif !== []) {
                    $ou->orWhereIn('reason', $clesDeMotif);
                }
            });
        }

        if (isset($filtres['statut'])) {
            $requete->where('status', $filtres['statut']);
        }

        if (isset($filtres['motif'])) {
            $requete->where('reason', $filtres['motif']);
        }

        if (isset($filtres['type'])) {
            $requete->where('reportable_type', $filtres['type']);
        }

        if (isset($filtres['recu_du'])) {
            $requete->where('content_reports.created_at', '>=', Carbon::createFromFormat('Y-m-d', $filtres['recu_du'])?->startOfDay());
        }

        if (isset($filtres['recu_au'])) {
            $requete->where('content_reports.created_at', '<=', Carbon::createFromFormat('Y-m-d', $filtres['recu_au'])?->endOfDay());
        }
    }

    /** @return LengthAwarePaginator<int, ContentReport> */
    public function paginer(
        ?StatutSignalementContenu $statut,
        ?string $type,
        int $parPage,
    ): LengthAwarePaginator {
        return ContentReport::query()
            /*
             | `reportable` est chargé d'avance. Sans cela, la boucle de la vue
             | ferait une requête par ligne — et `preventLazyLoading` lèverait
             | en développement, ce qui est précisément le garde-fou voulu.
             */
            ->with(['reporter:id,name,email', 'reportable'])
            ->when($statut !== null, static fn (Builder $r): Builder => $r->where('status', $statut->value))
            ->when($type !== null && $type !== '', static fn (Builder $r): Builder => $r->where('reportable_type', $type))
            /*
            |------------------------------------------------------------------
            | CE QUI SE RÉPAND VITE PASSE DEVANT
            |------------------------------------------------------------------
            |
            | La file se vidait dans l'ordre d'arrivée. C'est équitable et c'est
            | mauvais : une rumeur signalée par quinze personnes en deux heures
            | attendait derrière un hors-sujet signalé la veille par une seule.
            | Entre les deux, c'est la première qui circule pendant qu'on traite
            | la seconde.
            |
            | Le tri se fait en TROIS temps :
            |
            |   1. les contenus en propagation, c'est-à-dire ceux qui portent
            |      plusieurs alertes dont la première est récente ;
            |   2. parmi eux, le plus signalé d'abord ;
            |   3. tout le reste par ancienneté, comme avant.
            |
            | Le calcul reste en SQL et n'emploie aucune fonction de date propre
            | à un moteur : la borne temporelle est un paramètre lié, calculé en
            | PHP. Une expression `TIMESTAMPDIFF` aurait marché en MySQL et
            | échoué en SQLite, donc en test — c'est-à-dire là où on l'aurait
            | découvert le plus tard.
            */
            ->select('content_reports.*')
            ->selectSub(
                ContentReport::query()
                    ->selectRaw('COUNT(*)')
                    ->from('content_reports as alertes')
                    ->whereColumn('alertes.reportable_type', 'content_reports.reportable_type')
                    ->whereColumn('alertes.reportable_id', 'content_reports.reportable_id'),
                'alertes_total',
            )
            ->selectSub(
                ContentReport::query()
                    ->selectRaw('MIN(created_at)')
                    ->from('content_reports as premiere')
                    ->whereColumn('premiere.reportable_type', 'content_reports.reportable_type')
                    ->whereColumn('premiere.reportable_id', 'content_reports.reportable_id'),
                'premiere_alerte',
            )
            ->orderByRaw(
                '(SELECT COUNT(*) FROM content_reports flambee'
                .' WHERE flambee.reportable_type = content_reports.reportable_type'
                .' AND flambee.reportable_id = content_reports.reportable_id'
                .' AND flambee.created_at >= ?) >= ? DESC',
                [
                    now()->subHours(VitesseDePropagation::FENETRE_HEURES),
                    VitesseDePropagation::ALERTES_MINIMUM,
                ],
            )
            ->orderByDesc('alertes_total')
            ->oldest('content_reports.created_at')
            ->paginate($parPage);
    }

    public function parUuid(string $uuid): ?ContentReport
    {
        return ContentReport::query()->with(['reporter:id,name,email', 'reportable'])
            ->where('uuid', $uuid)->first();
    }

    /** @return array<string, int> */
    public function compteurs(): array
    {
        $parStatut = ContentReport::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $compteurs = ['total' => array_sum($parStatut)];

        foreach (StatutSignalementContenu::cases() as $cas) {
            $compteurs[$cas->value] = (int) ($parStatut[$cas->value] ?? 0);
        }

        return $compteurs;
    }

    /** @return list<string> */
    public function typesPresents(): array
    {
        return ContentReport::query()
            ->select('reportable_type')
            ->distinct()
            ->pluck('reportable_type')
            ->filter()
            ->values()
            ->all();
    }

    public function trancher(
        ContentReport $signalement,
        StatutSignalementContenu $statut,
        User $acteur,
        ?string $note = null,
    ): ContentReport {
        $signalement->forceFill([
            'status' => $statut->value,
            'handled_by' => $acteur->id,
            'handled_at' => now(),
            'handling_note' => $note,
        ])->save();

        return $signalement->refresh();
    }
}
