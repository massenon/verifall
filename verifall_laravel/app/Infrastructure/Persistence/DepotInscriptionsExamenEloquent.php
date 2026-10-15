<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotInscriptionsExamen;
use App\Enums\StatutInscriptionExamen;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class DepotInscriptionsExamenEloquent implements DepotInscriptionsExamen
{
    private const COLONNES_SQL = [
        'statut' => 'status',
        'inscritLe' => 'created_at',
    ];

    /** @return PageDeResultats<ExamRegistration> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = ExamRegistration::query()->count();

        $filtree = ExamRegistration::query();
        $this->filtrerLesInscriptions($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
            if ($cle === 'session') {
                $filtree->orderBy(
                    ExamSession::query()->select('starts_at')->whereColumn('id', 'exam_registrations.exam_session_id'),
                    $sens,
                );

                continue;
            }

            $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
        }

        $lignes = $filtree
            ->with(['user', 'session.exam'])
            ->orderByDesc('created_at')
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
     * @param  Builder<ExamRegistration>  $requete
     */
    private function filtrerLesInscriptions(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $motif = '%'.$criteres->recherche.'%';
            $requete->whereHas('user', fn (Builder $u) => $u
                ->where('name', 'like', $motif)
                ->orWhere('email', 'like', $motif));
        }

        if (isset($filtres['session'])) {
            $requete->whereHas('session', fn (Builder $s) => $s->where('uuid', $filtres['session']));
        }

        if (isset($filtres['examen'])) {
            $requete->whereHas('session.exam', fn (Builder $e) => $e->where('uuid', $filtres['examen']));
        }

        if (isset($filtres['statut'])) {
            $requete->where('status', $filtres['statut']);
        }

        if (isset($filtres['session_du'])) {
            $date = Carbon::createFromFormat('Y-m-d', $filtres['session_du'])?->startOfDay();
            $requete->whereHas('session', fn (Builder $s) => $s->where('starts_at', '>=', $date));
        }

        if (isset($filtres['session_au'])) {
            $date = Carbon::createFromFormat('Y-m-d', $filtres['session_au'])?->endOfDay();
            $requete->whereHas('session', fn (Builder $s) => $s->where('starts_at', '<=', $date));
        }
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(ExamSession $session, User $candidat, array $attributs = []): ExamRegistration
    {
        return ExamRegistration::create($attributs + [
            'uuid' => (string) Str::uuid(),
            'exam_session_id' => $session->id,
            'user_id' => $candidat->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(ExamRegistration $inscription, array $attributs): ExamRegistration
    {
        $inscription->fill($attributs)->save();

        return $inscription->refresh();
    }

    public function parUuid(string $uuid): ?ExamRegistration
    {
        return ExamRegistration::where('uuid', $uuid)->first();
    }

    public function pour(User $candidat, ExamSession $session): ?ExamRegistration
    {
        return ExamRegistration::where('user_id', $candidat->id)
            ->where('exam_session_id', $session->id)
            ->first();
    }

    public function codeConvocationLibre(string $code): bool
    {
        return ! ExamRegistration::where('convocation_code', $code)->exists();
    }

    /**
     * @return LengthAwarePaginator<int, ExamRegistration>
     */
    public function lister(
        ?ExamSession $session = null,
        string $recherche = '',
        ?StatutInscriptionExamen $statut = null,
        int $parPage = 15,
    ): LengthAwarePaginator {
        return ExamRegistration::query()
            ->with(['user', 'session.exam'])
            ->when($session instanceof ExamSession, fn (Builder $q) => $q->where('exam_session_id', $session?->id))
            ->when($statut instanceof StatutInscriptionExamen, fn (Builder $q) => $q->where('status', $statut?->value))
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $motif = '%'.$recherche.'%';
                $q->whereHas('user', function (Builder $u) use ($motif): void {
                    $u->where('name', 'like', $motif)->orWhere('email', 'like', $motif);
                });
            })
            ->orderByDesc('created_at')
            ->paginate($parPage);
    }

    /**
     * @return array{inscrits: int, presence: ?int, convocations_a_envoyer: int, reports: int}
     */
    public function indicateurs(?ExamSession $session = null): array
    {
        $base = ExamRegistration::query()
            ->when($session instanceof ExamSession, fn (Builder $q) => $q->where('exam_session_id', $session?->id))
            ->where('status', '!=', StatutInscriptionExamen::Annule->value);

        $inscrits = (int) $base->clone()->count();
        $presents = (int) $base->clone()->whereNotNull('attended_at')->count();

        return [
            'inscrits' => $inscrits,
            // null et non zéro : « 0 % de présence » sur une session sans
            // inscrit est un chiffre inventé, et un chiffre inventé rassure.
            'presence' => $inscrits === 0 ? null : (int) round($presents / $inscrits * 100),
            'convocations_a_envoyer' => (int) $base->clone()->whereNull('convocation_sent_at')->count(),
            'reports' => (int) $base->clone()
                ->where('status', StatutInscriptionExamen::ReportDemande->value)
                ->count(),
        ];
    }
}
