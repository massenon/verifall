<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Enums\StatutCopieExamen;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DepotCopiesExamenEloquent implements DepotCopiesExamen
{
    private const COLONNES_SQL = [
        'soumiseLe' => 'submitted_at',
        'statut' => 'status',
    ];

    /** @return PageDeResultats<ExamAttempt> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $base = ExamAttempt::query()->whereIn('status', [
            StatutCopieExamen::EnAttente->value,
            StatutCopieExamen::EnCorrection->value,
            StatutCopieExamen::Corrigee->value,
        ]);

        $total = (clone $base)->count();

        $filtree = clone $base;
        $this->filtrerLesCopies($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        if ($requete->tri === []) {
            // La plus ancienne d'abord : c'est ce tri qui fait remonter les
            // copies oubliées, comme la file Livewire qu'il remplace.
            $filtree->orderBy('submitted_at');
        } else {
            foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
                $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
            }
        }

        $lignes = $filtree
            ->with(['user', 'exam', 'correcteur'])
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
     * @param  Builder<ExamAttempt>  $requete
     */
    private function filtrerLesCopies(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $motif = '%'.$criteres->recherche.'%';
            $requete->where(function (Builder $sous) use ($motif): void {
                $sous->where('reference', 'like', $motif)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $motif))
                    ->orWhereHas('exam', fn (Builder $e) => $e->where('title', 'like', $motif));
            });
        }

        if (isset($filtres['examen'])) {
            $requete->whereHas('exam', fn (Builder $e) => $e->where('uuid', $filtres['examen']));
        }

        if (isset($filtres['statut'])) {
            $requete->where('status', $filtres['statut']);
        }

        if (isset($filtres['correcteur'])) {
            $requete->whereHas('correcteur', fn (Builder $c) => $c->where('uuid', $filtres['correcteur']));
        }

        if (isset($filtres['soumise_du'])) {
            $requete->where('submitted_at', '>=', Carbon::createFromFormat('Y-m-d', $filtres['soumise_du'])?->startOfDay());
        }

        if (isset($filtres['soumise_au'])) {
            $requete->where('submitted_at', '<=', Carbon::createFromFormat('Y-m-d', $filtres['soumise_au'])?->endOfDay());
        }
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): ExamAttempt
    {
        return ExamAttempt::create($attributs);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(ExamAttempt $copie, array $attributs): ExamAttempt
    {
        $copie->fill($attributs)->save();

        return $copie->refresh();
    }

    public function parUuid(string $uuid): ?ExamAttempt
    {
        return ExamAttempt::where('uuid', $uuid)->first();
    }

    public function parReference(string $reference): ?ExamAttempt
    {
        return ExamAttempt::where('reference', $reference)->first();
    }

    public function referenceLibre(string $reference): bool
    {
        return ! ExamAttempt::where('reference', $reference)->exists();
    }

    public function copieEnCours(User $candidat, Exam $examen): ?ExamAttempt
    {
        return ExamAttempt::where('user_id', $candidat->id)
            ->where('exam_id', $examen->id)
            ->where('status', StatutCopieExamen::EnComposition->value)
            ->first();
    }

    public function nombreDeTentatives(User $candidat, Exam $examen): int
    {
        return (int) ExamAttempt::where('user_id', $candidat->id)
            ->where('exam_id', $examen->id)
            ->count();
    }

    /**
     * @param  list<array<string, mixed>>  $reponses
     */
    public function enregistrerLesReponses(ExamAttempt $copie, array $reponses): void
    {
        DB::transaction(function () use ($copie, $reponses): void {
            foreach ($reponses as $reponse) {
                ExamAnswer::create($reponse + ['exam_attempt_id' => $copie->id]);
            }
        });
    }

    /**
     * @return Collection<int, ExamAnswer>
     */
    public function reponsesDe(ExamAttempt $copie): Collection
    {
        return ExamAnswer::where('exam_attempt_id', $copie->id)
            ->orderBy('position')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJourUneReponse(ExamAnswer $reponse, array $attributs): ExamAnswer
    {
        $reponse->fill($attributs)->save();

        return $reponse->refresh();
    }

    /**
     * @return LengthAwarePaginator<int, ExamAttempt>
     */
    public function fileDeCorrection(
        string $recherche = '',
        ?StatutCopieExamen $statut = null,
        ?int $correcteurId = null,
        int $parPage = 15,
    ): LengthAwarePaginator {
        return ExamAttempt::query()
            ->with(['user', 'exam', 'correcteur'])
            ->whereIn('status', [
                StatutCopieExamen::EnAttente->value,
                StatutCopieExamen::EnCorrection->value,
                StatutCopieExamen::Corrigee->value,
            ])
            ->when($statut instanceof StatutCopieExamen, fn (Builder $q) => $q->where('status', $statut?->value))
            ->when($correcteurId !== null, fn (Builder $q) => $q->where('assigned_to', $correcteurId))
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $motif = '%'.$recherche.'%';
                $q->where(function (Builder $sous) use ($motif): void {
                    $sous->where('reference', 'like', $motif)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $motif))
                        ->orWhereHas('exam', fn (Builder $e) => $e->where('title', 'like', $motif));
                });
            })
            // La plus ancienne d'abord : c'est ce tri qui fait remonter les
            // copies oubliées, pas un tri par date de création décroissante.
            ->orderBy('submitted_at')
            ->paginate($parPage);
    }

    /**
     * @return array{en_attente: int, urgentes: int, corrigees_30j: int, delai_moyen_jours: ?float, correcteurs_actifs: int}
     */
    public function indicateurs(): array
    {
        $enFile = ExamAttempt::whereIn('status', [
            StatutCopieExamen::EnAttente->value,
            StatutCopieExamen::EnCorrection->value,
        ]);

        /*
         | Le délai moyen se calcule en PHP sur les copies des trente derniers
         | jours, et non par une fonction SQL de différence de dates : celles-ci
         | ne s'écrivent pas pareil sous MySQL et sous SQLite, et la suite de
         | tests tourne sur la seconde.
         */
        $recentes = ExamAttempt::where('status', StatutCopieExamen::Corrigee->value)
            ->where('graded_at', '>=', now()->subDays(30))
            ->whereNotNull('submitted_at')
            ->get(['submitted_at', 'graded_at', 'graded_by']);

        $delais = $recentes
            ->map(fn (ExamAttempt $copie): float => (float) $copie->submitted_at?->diffInDays($copie->graded_at, true))
            ->all();

        return [
            'en_attente' => (int) $enFile->clone()->count(),
            'urgentes' => (int) $enFile->clone()
                ->where('submitted_at', '<', now()->subHours(72))
                ->count(),
            'corrigees_30j' => $recentes->count(),
            // null tant que rien n'a été corrigé : une moyenne sur zéro copie
            // n'est pas zéro jour, elle n'existe pas.
            'delai_moyen_jours' => $delais === [] ? null : round(array_sum($delais) / count($delais), 1),
            'correcteurs_actifs' => $recentes->pluck('graded_by')->filter()->unique()->count(),
        ];
    }
}
