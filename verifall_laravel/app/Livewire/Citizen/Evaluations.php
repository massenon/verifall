<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Lms\Services\ProgressionAcademie;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Mes Évaluations » — transposition de compte-user/evaluations.html.
 *
 * La vue d'ensemble de ce qui a été passé, réussi, repris. Deux onglets, quiz
 * et exercices, et quatre indicateurs en tête.
 *
 * Le score moyen se lit « — » tant qu'aucun quiz n'a été passé. Un « 0 % »
 * dirait qu'on a tout raté ; ce n'est pas la même chose que n'avoir rien tenté.
 */
#[Layout('components.layouts.citizen', ['title' => "Mes Évaluations · Vérif'All"])]
final class Evaluations extends Component
{
    public function render(): View
    {
        $utilisateur = $this->utilisateur();
        $inscrits = Enrollment::where('user_id', $utilisateur->id)->pluck('course_id')->all();

        $quiz = $this->quiz($utilisateur, $inscrits);
        $exercices = $this->exercices($utilisateur, $inscrits);

        $scores = $quiz->pluck('score')->filter(fn (?int $s): bool => $s !== null);

        return view('livewire.citizen.evaluations', [
            'quiz' => $quiz,
            'exercices' => $exercices,
            'nombreReussis' => $quiz->where('passed', true)->count(),
            'scoreMoyen' => $scores->isEmpty() ? null : (int) round($scores->avg()),
            'nombreTentatives' => (int) $quiz->sum('attempts'),
            'nombreSoumis' => $exercices->whereNotNull('statut')->count(),
        ]);
    }

    /**
     * Chaque quiz des cours publiés, avec ce que la personne en a fait.
     *
     * @param  list<int>  $inscrits
     * @return Collection<int, array<string, mixed>>
     */
    private function quiz(User $utilisateur, array $inscrits): Collection
    {
        $tentatives = QuizAttempt::where('user_id', $utilisateur->id)->get()->groupBy('quiz_id');
        $progression = app(ProgressionAcademie::class);

        /*
         | Les cours dont TOUTES les leçons sont lues.
         |
         | Calculé une fois par cours et non une fois par quiz : un cours qui
         | porte cinq quiz posait cinq fois la même question à la base.
         |
         | Ce drapeau manquait, et c'est lui qui produisait l'impasse (B-05) :
         | `<x-evaluation.action-quiz>` n'avait que « inscrit » pour décider, si
         | bien qu'il offrait « Passer le quiz » à qui n'avait pas fini les
         | leçons — le clic menait droit au refus. Les deux autres appelants,
         | dans `cours.blade.php`, tenaient déjà ce compte.
         */
        $ouverts = [];

        return Quiz::with('course')
            ->whereHas('course', fn ($q) => $q->where('is_published', true))
            ->orderBy('course_id')->orderBy('position')
            ->get()
            ->map(function (Quiz $q) use ($tentatives, $inscrits, $progression, $utilisateur, &$ouverts): array {
                $siennes = $tentatives->get($q->id);
                $inscrit = in_array($q->course_id, $inscrits, true);

                $ouverts[$q->course_id] ??= $inscrit && $q->course !== null
                    && $progression->prochaineLecon($utilisateur, $q->course) === null;

                return [
                    'quiz' => $q,
                    // Le MEILLEUR score, pas le dernier : c'est celui qui
                    // décide de la réussite, donc celui qu'on montre.
                    'score' => $siennes?->max('score'),
                    'attempts' => $siennes?->count() ?? 0,
                    'passed' => (bool) $siennes?->contains('passed', true),
                    'inscrit' => $inscrit,
                    'ouvert' => $ouverts[$q->course_id],
                ];
            });
    }

    /**
     * @param  list<int>  $inscrits
     * @return Collection<int, array<string, mixed>>
     */
    private function exercices(User $utilisateur, array $inscrits): Collection
    {
        $remises = AssignmentSubmission::where('user_id', $utilisateur->id)
            ->get()->keyBy('assignment_id');

        return Assignment::with('course')
            ->whereHas('course', fn ($q) => $q->where('is_published', true))
            ->orderBy('course_id')->orderBy('position')
            ->get()
            ->map(fn (Assignment $e): array => [
                'exercice' => $e,
                'statut' => $remises->get($e->id)?->status,
                'inscrit' => in_array($e->course_id, $inscrits, true),
            ]);
    }

    private function utilisateur(): User
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        return $utilisateur;
    }
}
