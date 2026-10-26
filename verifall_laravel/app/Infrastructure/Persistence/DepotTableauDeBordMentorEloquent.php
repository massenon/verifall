<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotTableauDeBordMentor;
use App\Core\Lms\Services\EspaceDuMentor;
use App\Enums\StatutDevoir;
use App\Enums\StatutInscription;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\Thread;
use App\Models\User;
use App\Models\Verification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class DepotTableauDeBordMentorEloquent implements DepotTableauDeBordMentor
{
    /** Objectif de traitement d'une vérification assignée, en minutes. */
    private const OBJECTIF_VERDICT = 360;

    public function __construct(private readonly EspaceDuMentor $espace) {}

    public function pour(User $mentor): array
    {
        // 5 minutes : un tableau de bord n'a pas à être à la seconde, et la
        // page est ouverte souvent.
        return Cache::remember("tableau-de-bord-mentor.{$mentor->id}", now()->addMinutes(5), fn (): array => $this->calculer($mentor));
    }

    /**
     * @return array<string, mixed> la forme décrite par le contrat
     */
    private function calculer(User $mentor): array
    {
        $ids = $this->espace->idsDesCours($mentor);
        $performance = $this->espace->performance($mentor);

        $copiesEnAttente = AssignmentSubmission::query()
            ->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $ids))
            ->where('status', StatutDevoir::Remis->value)
            ->with(['user:id,first_name,last_name,name', 'assignment:id,course_id,title', 'assignment.course:id,title'])
            ->oldest('created_at')
            ->limit(6)->get();

        $nombreACorriger = AssignmentSubmission::query()
            ->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $ids))
            ->where('status', StatutDevoir::Remis->value)->count();

        $assignees = Verification::query()
            ->where('assigned_to', $mentor->id)
            ->whereNotIn('status', ['completed'])
            ->latest('assigned_at')->limit(4)
            ->get(['id', 'uuid', 'claim', 'assigned_at', 'created_at']);

        $lives = LiveClass::query()
            ->where('host_id', $mentor->id)
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')->limit(6)
            ->withCount('registrations')
            ->get();

        $journee = [];
        foreach ($lives->filter(fn (LiveClass $l): bool => $l->starts_at->isToday()) as $live) {
            $journee[] = $this->creneau($live->starts_at, max(0.5, ($live->minutes ?? 60) / 60), 'video', 'Live — '.$live->title, $live->registrations_count.' inscrits', 'live', $this->route('staff.live-classes'));
        }
        foreach ($copiesEnAttente->take(2) as $copie) {
            $echeance = $copie->created_at->copy()->addDay();
            if ($echeance->isToday()) {
                $journee[] = $this->creneau($echeance, 0.75, 'pen-line', 'Échéance — copie de '.$this->nom($copie->user), 'Corriger avant '.$echeance->format('H\hi'), 'critique', $this->route('staff.exam-grading'));
            }
        }
        foreach ($assignees as $verif) {
            $echeance = ($verif->assigned_at ?? $verif->created_at)->copy()->addMinutes(self::OBJECTIF_VERDICT);
            if ($echeance->isToday()) {
                $journee[] = $this->creneau($echeance, 1, 'search-check', 'Vérification assignée', Str::limit((string) $verif->claim, 40), 'alerte', $this->route('staff.verifications'));
            }
        }
        usort($journee, fn (array $a, array $b): int => $a['debut'] <=> $b['debut']);

        $completions = array_column($performance, 'completion');
        $notes = array_filter(array_column($performance, 'note'));

        $tendance = $this->tendances($ids);

        return [
            'journee' => $journee,
            'chiffres' => [
                'actifs' => Enrollment::query()->whereIn('course_id', $ids)->where('last_activity_at', '>=', now()->subDays(30))->distinct('user_id')->count('user_id'),
                'aCorriger' => $nombreACorriger,
                'completion' => $completions === [] ? 0 : (int) round(array_sum($completions) / count($completions)),
                'note' => $notes === [] ? null : round(array_sum($notes) / count($notes), 1),
                'avis' => (int) array_sum(array_column($performance, 'avis')),
            ],
            'copies' => $copiesEnAttente->map(fn (AssignmentSubmission $c): array => [
                'apprenant' => $this->nom($c->user),
                'initiales' => $this->initiales($this->nom($c->user)),
                'devoir' => (string) $c->assignment?->title,
                'cours' => (string) $c->assignment?->course?->title,
                'soumis' => $c->created_at->locale('fr')->diffForHumans(),
                'urgent' => $c->created_at->lt(now()->subHours(20)),
            ])->all(),
            'assignees' => $assignees->map(fn (Verification $v): array => [
                'texte' => Str::limit((string) $v->claim, 110),
                'reste' => (int) now()->diffInMinutes(($v->assigned_at ?? $v->created_at)->copy()->addMinutes(self::OBJECTIF_VERDICT), false),
                'lien' => $this->route('staff.verifications'),
            ])->all(),
            'cours' => array_map(fn (array $ligne): array => [
                'titre' => (string) $ligne['cours']->title,
                'inscrits' => $ligne['apprenants'],
                'actifs' => Enrollment::query()->where('course_id', $ligne['cours']->id)->where('last_activity_at', '>=', now()->subDays(7))->count(),
                'completion' => $ligne['completion'],
                'note' => $ligne['note'],
                'tendance' => $tendance[$ligne['cours']->id] ?? array_fill(0, 12, 0),
            ], $performance),
            'aRisque' => Enrollment::query()->whereIn('course_id', $ids)
                ->where('status', '!=', StatutInscription::Termine->value)
                ->where('last_activity_at', '<', now()->subDays(14))
                ->with(['user:id,first_name,last_name,name', 'course:id,title'])
                ->oldest('last_activity_at')->limit(5)->get()
                ->map(fn (Enrollment $e): array => [
                    'nom' => $this->nom($e->user),
                    'initiales' => $this->initiales($this->nom($e->user)),
                    'cours' => (string) $e->course?->title,
                    'progression' => (int) $e->progress,
                    'derniere' => $e->last_activity_at?->locale('fr')->diffForHumans() ?? '',
                ])->all(),
            'sessions' => $lives->take(3)->map(fn (LiveClass $l): array => [
                'titre' => (string) $l->title,
                'quand' => $l->starts_at->locale('fr')->translatedFormat('l j M, H\hi'),
                'inscrits' => (int) $l->registrations_count,
            ])->values()->all(),
            'questions' => Thread::query()->where('replies_count', 0)->where('created_at', '>=', now()->subDays(7))
                ->with('author:id,first_name,last_name,name')->latest()->limit(3)->get()
                ->map(fn (Thread $t): array => [
                    'titre' => (string) $t->title,
                    'auteur' => $this->nom($t->author),
                    'quand' => $t->created_at->locale('fr')->diffForHumans(),
                    'lien' => $this->route('staff.forum'),
                ])->all(),
        ];
    }

    /**
     * Inscriptions par semaine sur 12 semaines, par cours.
     *
     * @param  list<int>  $ids
     * @return array<int, list<int>>
     */
    private function tendances(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $debut = now()->startOfWeek()->subWeeks(11);
        $lignes = Enrollment::query()->whereIn('course_id', $ids)->where('created_at', '>=', $debut)->get(['course_id', 'created_at']);
        $series = [];

        foreach ($ids as $id) {
            $series[$id] = array_fill(0, 12, 0);
        }
        foreach ($lignes as $ligne) {
            $semaine = min(11, (int) floor($debut->diffInDays($ligne->created_at) / 7));
            $series[$ligne->course_id][$semaine]++;
        }

        return $series;
    }

    /**
     * @return array{heure: string, debut: float, duree: float, icone: string, titre: string, detail: string, ton: string, lien: string}
     */
    private function creneau(CarbonInterface $debut, float $duree, string $icone, string $titre, string $detail, string $ton, string $lien): array
    {
        return [
            'heure' => $debut->format('H\hi'),
            'debut' => $debut->hour + $debut->minute / 60,
            'duree' => $duree,
            'icone' => $icone, 'titre' => $titre, 'detail' => $detail, 'ton' => $ton, 'lien' => $lien,
        ];
    }

    private function nom(?User $u): string
    {
        if ($u === null) {
            return 'Compte supprimé';
        }

        return trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: (string) $u->name;
    }

    private function initiales(string $nom): string
    {
        return collect(explode(' ', $nom))->filter()->take(2)->map(fn (string $m): string => mb_strtoupper(mb_substr($m, 0, 1)))->implode('');
    }

    private function route(string $nom): string
    {
        return Route::has($nom) ? route($nom) : '#';
    }
}
