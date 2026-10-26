<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotAnalytique;
use App\Enums\StatutInscription;
use App\Enums\StatutPaiement;
use App\Models\Certificate;
use App\Models\EngineRun;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\UnitLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class DepotAnalytiqueEloquent implements DepotAnalytique
{
    private const TTL_MINUTES = 10;

    public function academie(): array
    {
        return Cache::remember('analytique.academie', now()->addMinutes(self::TTL_MINUTES), function (): array {
            $inscrits = Enrollment::query()->count();
            $termines = Enrollment::query()->where('status', StatutInscription::Termine)->count();

            $cours = Enrollment::query()
                ->join('courses', 'courses.id', '=', 'enrollments.course_id')
                ->groupBy('courses.id', 'courses.title')
                ->orderByDesc(DB::raw('count(*)'))
                ->limit(8)
                ->selectRaw('courses.title as titre, count(*) as inscrits, sum(case when enrollments.status = ? then 1 else 0 end) as termines, coalesce(avg(enrollments.progress), 0) as progression', [StatutInscription::Termine->value])
                ->get()
                ->map(fn ($l): array => [
                    'titre' => (string) $l->titre,
                    'inscrits' => (int) $l->inscrits,
                    'termines' => (int) $l->termines,
                    'completion' => $l->inscrits > 0 ? (int) round($l->termines / $l->inscrits * 100) : 0,
                    'progression' => (int) round((float) $l->progression),
                ])->all();

            return [
                'entonnoir' => [
                    'inscrits' => $inscrits,
                    'en_cours' => Enrollment::query()->where('progress', '>', 0)->count(),
                    'termines' => $termines,
                    'certificats' => Certificate::query()->count(),
                ],
                'cours' => $cours,
                'inactifs' => Enrollment::query()->where('status', '!=', StatutInscription::Termine)->where('last_activity_at', '<', now()->subDays(14))->count(),
                'actifs_7j' => Enrollment::query()->where('last_activity_at', '>=', now()->subDays(7))->distinct()->count('user_id'),
            ];
        });
    }

    public function moteurs(int $jours = 30): array
    {
        return Cache::remember("analytique.moteurs.{$jours}", now()->addMinutes(self::TTL_MINUTES), function () use ($jours): array {
            return EngineRun::query()
                ->where('created_at', '>=', now()->subDays($jours))
                ->groupBy('engine')
                ->orderByDesc(DB::raw('count(*)'))
                ->selectRaw('engine, count(*) as analyses, sum(case when failed then 1 else 0 end) as echecs, coalesce(avg(latency_ms), 0) as latence, coalesce(avg(confidence), 0) as confiance')
                ->get()
                ->map(fn ($l): array => [
                    'moteur' => (string) $l->engine,
                    'analyses' => (int) $l->analyses,
                    'echecs' => (int) $l->echecs,
                    'taux_echec' => $l->analyses > 0 ? (int) round($l->echecs / $l->analyses * 100) : 0,
                    'latence_ms' => (int) round((float) $l->latence),
                    // Stockée de 0 à 1 ou de 0 à 100 selon l'adaptateur : on ramène à des pour cent.
                    'confiance' => (int) round((float) $l->confiance <= 1 ? (float) $l->confiance * 100 : (float) $l->confiance),
                ])->all();
        });
    }

    public function revenus(int $mois = 6): array
    {
        return Cache::remember("analytique.revenus.{$mois}", now()->addMinutes(self::TTL_MINUTES), function () use ($mois): array {
            $debut = Carbon::now()->startOfMonth()->subMonths($mois - 1);

            $parMois = Payment::query()
                ->where('status', StatutPaiement::Reussi)
                ->where('settled_at', '>=', $debut)
                ->get(['amount_fcfa', 'settled_at'])
                ->groupBy(fn (Payment $p): string => $p->settled_at->format('Y-m'));

            $serie = [];
            for ($i = 0; $i < $mois; $i++) {
                $cle = $debut->copy()->addMonths($i)->format('Y-m');
                $lignes = $parMois->get($cle, collect());
                $serie[] = ['mois' => $cle, 'fcfa' => (int) $lignes->sum('amount_fcfa'), 'paiements' => $lignes->count()];
            }

            return [
                'mois' => $serie,
                'total_fcfa' => array_sum(array_column($serie, 'fcfa')),
                'reussis' => Payment::query()->where('status', StatutPaiement::Reussi)->where('settled_at', '>=', $debut)->count(),
                'echoues' => Payment::query()->whereIn('status', [StatutPaiement::Echoue, StatutPaiement::Expire])->where('created_at', '>=', $debut)->count(),
                'credites' => (int) UnitLog::query()->where('delta', '>', 0)->where('created_at', '>=', $debut)->sum('delta'),
                'consommes' => (int) abs((int) UnitLog::query()->where('delta', '<', 0)->where('created_at', '>=', $debut)->sum('delta')),
                'motifs' => UnitLog::query()->where('delta', '<', 0)->where('created_at', '>=', $debut)
                    ->groupBy('reason')->orderByDesc(DB::raw('abs(sum(delta))'))->limit(6)
                    ->selectRaw('reason, abs(sum(delta)) as unites')->get()
                    ->map(fn ($l): array => ['motif' => (string) ($l->reason instanceof \BackedEnum ? $l->reason->value : $l->reason), 'unites' => (int) $l->unites])->all(),
            ];
        });
    }
}
