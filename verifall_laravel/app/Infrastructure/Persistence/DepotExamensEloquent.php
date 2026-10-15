<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotExamens;
use App\Enums\StatutCopieExamen;
use App\Enums\StatutExamen;
use App\Enums\StatutInscriptionExamen;
use App\Enums\StatutSessionExamen;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\QuestionBankItem;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class DepotExamensEloquent implements DepotExamens
{
    private const COLONNES_SQL = [
        'titre' => 'title',
        'duree' => 'duration_minutes',
        'seuil' => 'pass_score',
        'statut' => 'status',
        'creeLe' => 'created_at',
    ];

    /** @return PageDeResultats<Exam> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = Exam::query()->count();

        $filtree = Exam::query()->withCount('sessions');
        $this->filtrerLesExamens($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
            $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
        }

        $lignes = $filtree
            ->with('course')
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
     * @param  Builder<Exam>  $requete
     */
    private function filtrerLesExamens(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $motif = '%'.$criteres->recherche.'%';
            $requete->where(function (Builder $sous) use ($motif): void {
                $sous->where('title', 'like', $motif)
                    ->orWhereHas('course', fn (Builder $c) => $c->where('title', 'like', $motif));
            });
        }

        if (isset($filtres['cours'])) {
            $requete->whereHas('course', fn (Builder $c) => $c->where('uuid', $filtres['cours']));
        }

        if (isset($filtres['statut'])) {
            $requete->where('status', $filtres['statut']);
        }

        if (isset($filtres['cree_du'])) {
            $requete->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $filtres['cree_du'])?->startOfDay());
        }

        if (isset($filtres['cree_au'])) {
            $requete->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $filtres['cree_au'])?->endOfDay());
        }
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): Exam
    {
        return Exam::create($attributs);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJour(Exam $examen, array $attributs): Exam
    {
        $examen->fill($attributs)->save();

        return $examen->refresh();
    }

    public function supprimer(Exam $examen): void
    {
        // Suppression douce : les copies et les relevés restent lisibles.
        $examen->delete();
    }

    public function parUuid(string $uuid): ?Exam
    {
        return Exam::where('uuid', $uuid)->first();
    }

    public function parSlug(string $slug): ?Exam
    {
        return Exam::where('slug', $slug)->first();
    }

    public function slugLibre(string $slug): bool
    {
        // withTrashed : un examen archivé puis supprimé garde son slug, et le
        // réattribuer ferait pointer une ancienne URL sur une autre épreuve.
        return ! Exam::withTrashed()->where('slug', $slug)->exists();
    }

    /**
     * @return LengthAwarePaginator<int, Exam>
     */
    public function lister(
        string $recherche = '',
        ?StatutExamen $statut = null,
        int $parPage = 15,
    ): LengthAwarePaginator {
        return Exam::query()
            ->with('course')
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $motif = '%'.$recherche.'%';
                $q->where(function (Builder $sous) use ($motif): void {
                    $sous->where('title', 'like', $motif)
                        ->orWhereHas('course', fn (Builder $c) => $c->where('title', 'like', $motif));
                });
            })
            ->when($statut instanceof StatutExamen, fn (Builder $q) => $q->where('status', $statut?->value))
            ->orderByDesc('created_at')
            ->paginate($parPage);
    }

    /**
     * @return array{actifs: int, sessions_ouvertes: int, candidats_30j: int, taux_reussite: ?int, a_corriger: int, abandons: int, certificats: int}
     */
    public function indicateurs(): array
    {
        $candidats = (int) ExamAttempt::where('status', StatutCopieExamen::Corrigee->value)
            ->where('graded_at', '>=', now()->subDays(30))
            ->count();

        $reussis = (int) ExamAttempt::where('status', StatutCopieExamen::Corrigee->value)
            ->where('graded_at', '>=', now()->subDays(30))
            ->where('passed', true)
            ->count();

        return [
            'actifs' => (int) Exam::where('status', StatutExamen::Publie->value)->count(),
            'sessions_ouvertes' => (int) ExamSession::where('status', StatutSessionExamen::Ouverte->value)->count(),
            'candidats_30j' => $candidats,
            // null et non zéro : sans copie corrigée, il n'y a pas de taux.
            'taux_reussite' => $candidats === 0 ? null : (int) round($reussis / $candidats * 100),
            'a_corriger' => (int) ExamAttempt::whereIn('status', [
                StatutCopieExamen::EnAttente->value,
                StatutCopieExamen::EnCorrection->value,
            ])->count(),
            // « Abandons en cours d'épreuve » de stats-globales.html. Compté à
            // part, jamais parmi les échecs : ne pas rendre sa copie n'est pas
            // la rater.
            'abandons' => (int) ExamAttempt::where('status', StatutCopieExamen::Abandonnee->value)->count(),
            /*
             | « Certificats issus d'examens » de `stats-globales.html`, et le
             | dernier chiffre qui manquait à cet écran.
             |
             | Compté sur les COPIES réussies, pas sur la table des certificats :
             | celle-ci mêle les diplômes de cours et ceux d'examen, et les
             | additionner donnerait un chiffre qui ne veut rien dire sur une
             | page consacrée aux épreuves.
             */
            'certificats' => (int) ExamAttempt::where('status', StatutCopieExamen::Corrigee->value)
                ->where('passed', true)
                ->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Exam>
     */
    public function statistiques(string $recherche = '', int $parPage = 15): LengthAwarePaginator
    {
        return Exam::query()
            ->withCount('sessions')
            /*
             | Candidats et réussis, comptés PAR épreuve — les deux colonnes que
             | `stats-globales.html` porte et qui manquaient. Sans le second, la
             | page affichait une note moyenne sans dire combien de gens
             | passaient réellement : une moyenne à 82 % sur trois copies et sur
             | trois cents ne se lisent pas de la même façon.
             */
            ->withCount([
                'copies as corrigees_count' => fn (Builder $q) => $q
                    ->where('status', StatutCopieExamen::Corrigee->value),
                'copies as reussies_count' => fn (Builder $q) => $q
                    ->where('status', StatutCopieExamen::Corrigee->value)
                    ->where('passed', true),
            ])
            ->withAvg(
                ['copies as average_score' => fn (Builder $q) => $q->where('status', StatutCopieExamen::Corrigee->value)],
                'score_percent',
            )
            ->when($recherche !== '', fn (Builder $q) => $q->where('title', 'like', '%'.$recherche.'%'))
            ->orderByDesc('candidates_count')
            ->paginate($parPage);
    }

    // ------------------------------------------------------------ SESSIONS

    public function sessionParUuid(string $uuid): ?ExamSession
    {
        return ExamSession::where('uuid', $uuid)->first();
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creerSession(Exam $examen, array $attributs): ExamSession
    {
        return ExamSession::create($attributs + ['exam_id' => $examen->id]);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function mettreAJourSession(ExamSession $session, array $attributs): ExamSession
    {
        $session->fill($attributs)->save();

        return $session->refresh();
    }

    public function sessionExisteA(Exam $examen, DateTimeInterface $debut): bool
    {
        return ExamSession::where('exam_id', $examen->id)
            ->where('starts_at', $debut)
            ->exists();
    }

    /**
     * @return LengthAwarePaginator<int, ExamSession>
     */
    public function listerSessions(
        ?Exam $examen = null,
        ?StatutSessionExamen $statut = null,
        int $parPage = 15,
    ): LengthAwarePaginator {
        return ExamSession::query()
            ->with('exam')
            ->when($examen instanceof Exam, fn (Builder $q) => $q->where('exam_id', $examen?->id))
            ->when($statut instanceof StatutSessionExamen, fn (Builder $q) => $q->where('status', $statut?->value))
            ->orderBy('starts_at')
            ->paginate($parPage);
    }

    /**
     * @return Collection<int, ExamSession>
     */
    public function sessionsAVenir(?Exam $examen = null): Collection
    {
        return ExamSession::query()
            ->with('exam')
            ->when($examen instanceof Exam, fn (Builder $q) => $q->where('exam_id', $examen?->id))
            ->whereIn('status', [
                StatutSessionExamen::Planifiee->value,
                StatutSessionExamen::Ouverte->value,
            ])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->get();
    }

    // ----------------------------------------------------------- QUESTIONS

    public function attacherUneQuestion(Exam $examen, QuestionBankItem $question, ?int $position = null): ExamQuestion
    {
        $rang = $position ?? ((int) ExamQuestion::where('exam_id', $examen->id)->max('position') + 1);

        return ExamQuestion::create([
            'exam_id' => $examen->id,
            'question_bank_item_id' => $question->id,
            'position' => $rang,
        ]);
    }

    public function detacherUneQuestion(Exam $examen, QuestionBankItem $question): void
    {
        ExamQuestion::where('exam_id', $examen->id)
            ->where('question_bank_item_id', $question->id)
            ->delete();
    }

    /**
     * @return Collection<int, QuestionBankItem>
     */
    public function questionsDe(Exam $examen): Collection
    {
        return QuestionBankItem::query()
            ->with('options')
            ->join('exam_questions', 'exam_questions.question_bank_item_id', '=', 'question_bank_items.id')
            ->where('exam_questions.exam_id', $examen->id)
            ->orderBy('exam_questions.position')
            ->select('question_bank_items.*')
            ->get();
    }

    // ---------------------------------------------------------- COMPTEURS

    public function recalculerLesCompteurs(Exam $examen): Exam
    {
        $corrigees = ExamAttempt::where('exam_id', $examen->id)
            ->where('status', StatutCopieExamen::Corrigee->value);

        $examen->forceFill([
            'candidates_count' => (int) $corrigees->clone()->count(),
            'passed_count' => (int) $corrigees->clone()->where('passed', true)->count(),
        ])->save();

        return $examen->refresh();
    }

    public function recalculerLesCompteursDeSession(ExamSession $session): ExamSession
    {
        $inscrits = ExamRegistration::where('exam_session_id', $session->id)
            ->where('status', '!=', StatutInscriptionExamen::Annule->value);

        $session->forceFill([
            // Une inscription annulée n'occupe plus de place, et ne doit pas
            // faire chuter le taux de présence de la session.
            'registrations_count' => (int) $inscrits->clone()->count(),
            'attended_count' => (int) $inscrits->clone()->whereNotNull('attended_at')->count(),
        ])->save();

        return $session->refresh();
    }
}
