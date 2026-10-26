<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Core\Application\Services\ModeMaintenance;
use App\Core\Lms\Services\EspaceDuMentor;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\LiveClass;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Les événements du calendrier de la console, pour une fenêtre de dates.
 *
 * Trois familles, chacune soumise au droit de lister son écran : les lives, les
 * sessions d'examen, les maintenances. Un mentor ne voit que SES lives (ceux
 * qu'il anime ou qui portent sur ses cours) — sauf s'il peut tout lister.
 * La fenêtre est bornée à 100 jours : un calendrier n'a pas à charger l'année.
 */
final class DonneesDuCalendrierController extends Controller
{
    private const FENETRE_MAX_JOURS = 100;

    public function __invoke(Request $requete, ModeMaintenance $maintenance, EspaceDuMentor $espace): JsonResponse
    {
        /** @var User $membre */
        $membre = Auth::guard('staff')->user();

        try {
            $debut = CarbonImmutable::parse((string) $requete->query('start', 'now'))->startOfDay();
            $fin = CarbonImmutable::parse((string) $requete->query('end', $debut->addMonth()->toDateString()));
        } catch (Throwable) {
            return response()->json([], 422);
        }

        if ($fin->lessThan($debut)) {
            return response()->json([], 422);
        }

        $fin = $fin->min($debut->addDays(self::FENETRE_MAX_JOURS));
        $evenements = [];

        if ($membre->can('viewAny', LiveClass::class)) {
            LiveClass::query()
                ->whereBetween('starts_at', [$debut, $fin])
                ->when(! $membre->hasRole(UserRole::Admin->value), fn ($q) => $q->where(
                    fn ($r) => $r->where('host_id', $membre->id)->orWhereIn('course_id', $espace->idsDesCours($membre)),
                ))
                ->orderBy('starts_at')->limit(300)->get(['id', 'title', 'starts_at', 'minutes', 'host_id'])
                ->each(function (LiveClass $live) use (&$evenements): void {
                    $evenements[] = [
                        'title' => $live->title,
                        'start' => $live->starts_at->toIso8601String(),
                        'end' => $live->starts_at->addMinutes((int) ($live->minutes ?: 60))->toIso8601String(),
                        'classNames' => ['evt-live'],
                        'url' => route('staff.live-classes'),
                        'extendedProps' => ['type' => 'Live'],
                    ];
                });
        }

        if ($membre->can('viewAny', Exam::class)) {
            ExamSession::query()->with('exam:id,title')
                ->whereBetween('starts_at', [$debut, $fin])
                ->orderBy('starts_at')->limit(300)->get()
                ->each(function (ExamSession $session) use (&$evenements): void {
                    $evenements[] = [
                        'title' => 'Examen — '.($session->exam?->title ?? 'Session'),
                        'start' => $session->starts_at->toIso8601String(),
                        'classNames' => ['evt-examen'],
                        'url' => route('staff.exams'),
                        'extendedProps' => ['type' => 'Examen'],
                    ];
                });
        }

        if ($membre->can('maintenance.mode')) {
            foreach ($maintenance->historique(20) as $periode) {
                if ($periode['debut']->between($debut, $fin)) {
                    $evenements[] = [
                        'title' => 'Maintenance'.($periode['duree_minutes'] !== null ? ' ('.$periode['duree_minutes'].' min)' : ' en cours'),
                        'start' => $periode['debut']->toIso8601String(),
                        'end' => ($periode['fin'] ?? now())->toIso8601String(),
                        'classNames' => ['evt-maintenance'],
                        'url' => route('staff.maintenance-mode'),
                        'extendedProps' => ['type' => 'Maintenance'],
                    ];
                }
            }
        }

        return response()->json($evenements)->header('Cache-Control', 'no-store, private');
    }
}
