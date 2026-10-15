<?php

declare(strict_types=1);

namespace App\Core\Gamification\Services;

use App\Core\Economy\Services\EconomyService;
use App\Core\Marketing\Emails\ExpediteurDeCourriels;
use App\Enums\MotifGainXp;
use App\Enums\MotifMouvementUnite;
use App\Enums\StatutVerification;
use App\Enums\VerdictFactCheck;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\Verification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Décide quels badges sont acquis, et les accorde.
 *
 * Les conditions sont RECALCULÉES depuis les faits — vérifications, leçons,
 * quiz, certificats — et jamais tenues par un compteur qu'on incrémente. Un
 * compteur dérive au premier événement rejoué, et un badge qui apparaît sans
 * raison décrédibilise tous les autres.
 *
 * Le revers : recalculer coûte quelques requêtes. C'est acceptable, parce que
 * cela n'arrive qu'après une action de la personne, jamais à l'affichage d'une
 * liste.
 */
final class AttributionDesBadges
{
    public function __construct(
        private readonly EconomyService $economie,
        private readonly JournalDXp $journalXp,
        private readonly ExpediteurDeCourriels $courriels,
    ) {}

    /**
     * Recalcule tout et accorde ce qui manque.
     *
     * @return Collection<int, Badge> les badges DÉBLOQUÉS À L'INSTANT
     */
    public function recalculer(User $utilisateur): Collection
    {
        $mesures = $this->mesures($utilisateur);
        $deja = UserBadge::where('user_id', $utilisateur->id)->pluck('badge_id')->all();

        $nouveaux = new Collection;

        foreach (Badge::where('is_active', true)->orderBy('position')->get() as $badge) {
            if (in_array($badge->id, $deja, true)) {
                continue;
            }

            if (! $badge->estAcquis($mesures)) {
                continue;
            }

            $this->accorder($utilisateur, $badge);
            $nouveaux->push($badge);
        }

        return $nouveaux;
    }

    /**
     * Ce qu'on sait de la personne, en une passe.
     *
     * @return array<string, int>
     */
    public function mesures(User $utilisateur): array
    {
        return [
            'verifications' => Verification::where('user_id', $utilisateur->id)->count(),

            /*
             | « Intox détectées » réunit FAUX et TROMPEUR — du point de vue de
             | quelqu'un qui a failli partager, les deux sont des pièges. Même
             | définition que dans « Mes vérifications » : deux définitions du
             | même mot finiraient par afficher deux chiffres.
             */
            'intox' => Verification::where('user_id', $utilisateur->id)
                ->where('status', StatutVerification::Terminee->value)
                ->whereHas('report', fn ($q) => $q->whereIn('verdict', [
                    VerdictFactCheck::Faux->value, VerdictFactCheck::Trompeur->value,
                ]))
                ->count(),

            'lecons' => LessonCompletion::where('user_id', $utilisateur->id)->count(),

            // Réussites DISTINCTES : repasser un quiz déjà validé ne compte pas
            // deux fois.
            'quiz' => QuizAttempt::where('user_id', $utilisateur->id)
                ->where('passed', true)
                ->distinct('quiz_id')
                ->count('quiz_id'),

            'certificats' => Certificate::where('user_id', $utilisateur->id)
                ->whereNull('revoked_at')
                ->count(),

            'serie' => (int) $utilisateur->streak_days,
            'xp' => (int) $utilisateur->xp,

            'filleuls' => Referral::where('referrer_id', $utilisateur->id)
                ->whereNotNull('qualified_at')
                ->count(),
        ];
    }

    private function accorder(User $utilisateur, Badge $badge): void
    {
        DB::transaction(function () use ($utilisateur, $badge): void {
            /*
             | `firstOrCreate` et non `create` : deux recalculs concurrents —
             | une vérification qui s'achève pendant qu'un quiz est corrigé —
             | tenteraient d'accorder le même badge, et la seconde lèverait une
             | exception au visage de quelqu'un qui vient de réussir.
             */
            $accorde = UserBadge::firstOrCreate(
                ['user_id' => $utilisateur->id, 'badge_id' => $badge->id],
                ['unlocked_at' => now()],
            );

            if (! $accorde->wasRecentlyCreated) {
                return;
            }

            if ($badge->xp_reward > 0) {
                $this->journalXp->crediter(
                    $utilisateur,
                    (int) $badge->xp_reward,
                    MotifGainXp::Badge,
                    $badge,
                );
            }

            /*
             | Livré ÉTEINT au catalogue : un courriel par badge est le premier
             | pas vers le filtre automatique, qui emportera ensuite les
             | messages qui comptent. Le câblage existe pour que
             | l'administration puisse en décider — pas pour que ce soit le
             | comportement par défaut.
             */
            $this->courriels->envoyer(
                $utilisateur,
                'badge_debloque',
                [
                    'prenom' => $utilisateur->name,
                    'badge' => $badge->name,
                    'xp' => (int) $badge->xp_reward,
                ],
                route('citizen.rewards'),
            );

            if ($badge->units_reward > 0) {
                $this->economie->crediter(
                    $utilisateur,
                    $badge->units_reward,
                    MotifMouvementUnite::Parrainage,
                    'Badge — '.$badge->name,
                    $badge,
                );
            }
        });
    }
}
