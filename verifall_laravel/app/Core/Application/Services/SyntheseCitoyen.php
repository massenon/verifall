<?php

declare(strict_types=1);

namespace App\Core\Application\Services;

use App\Enums\TypeContenu;
use App\Models\Enrollment;
use App\Models\LessonCompletion;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;

/**
 * Ce qu'une personne a fait sur la plateforme, en chiffres.
 *
 * POURQUOI DANS `Application` ET NON DANS UN DOMAINE. Cette synthèse croise
 * quatre domaines — vérifications, académie, gamification, économie. La ranger
 * dans l'un d'eux le rendrait dépendant des trois autres, ce que la règle 6 du
 * chantier interdit précisément. La couche applicative est faite pour ça :
 * elle compose, elle ne décide rien.
 *
 * POURQUOI ELLE EXISTE. Ces agrégats vivaient dans les méthodes privées de
 * deux composants Livewire. L'écran mobile « Statistiques » et le tableau de
 * bord mobile affichent exactement les mêmes chiffres. Les recalculer dans un
 * contrôleur d'API aurait donné deux formules pour un même pourcentage — et le
 * jour où elles divergent, c'est le compteur qui ment en premier.
 *
 * Tous les chiffres sont RECALCULÉS à la lecture, jamais incrémentés à
 * l'aveugle : un compteur qui dérive est pire qu'un compteur absent, il ment
 * avec aplomb.
 */
final class SyntheseCitoyen
{
    /**
     * Les quatre tuiles du tableau de bord.
     *
     * @return array{verifications: int, cours_suivis: int, badges: int, unites: int}
     */
    public function compteurs(User $utilisateur): array
    {
        return [
            'verifications' => Verification::where('user_id', $utilisateur->id)->count(),
            'cours_suivis' => Enrollment::where('user_id', $utilisateur->id)->count(),
            'badges' => UserBadge::where('user_id', $utilisateur->id)->count(),
            'unites' => $utilisateur->units,
        ];
    }

    /**
     * Combien de vérifications de plus (ou de moins) que le mois dernier.
     *
     * Le mois dernier veut dire les 30 jours PRÉCÉDANT les 30 derniers, pas le
     * mois calendaire : comparer un 3 août à un mois de juillet complet
     * fabriquerait une chute tous les débuts de mois.
     */
    public function evolutionMensuelle(User $utilisateur): int
    {
        $recents = Verification::where('user_id', $utilisateur->id)
            ->where('created_at', '>=', now()->subDays(30))->count();

        $precedents = Verification::where('user_id', $utilisateur->id)
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();

        return $recents - $precedents;
    }

    /**
     * Répartition des verdicts rendus sur MES vérifications.
     *
     * @return array<string, int>
     */
    public function verdicts(User $utilisateur): array
    {
        return DB::table('verifications')
            ->join('analysis_reports', 'analysis_reports.verification_id', '=', 'verifications.id')
            ->where('verifications.user_id', $utilisateur->id)
            ->select('analysis_reports.verdict', DB::raw('COUNT(*) as total'))
            ->groupBy('analysis_reports.verdict')
            ->pluck('total', 'verdict')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * Répartition par type de contenu soumis.
     *
     * @return array<string, int>
     */
    public function types(User $utilisateur): array
    {
        $comptes = Verification::where('user_id', $utilisateur->id)
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        // Tous les types sont représentés, même à zéro : un histogramme dont
        // les colonnes changent de place d'une visite à l'autre ne se lit pas.
        $sortie = [];
        foreach (TypeContenu::cases() as $type) {
            $sortie[$type->value] = (int) ($comptes[$type->value] ?? 0);
        }

        return $sortie;
    }

    /**
     * Activité jour par jour sur une fenêtre.
     *
     * Les jours sans rien valent zéro et RESTENT dans la série : un graphique
     * qui saute les jours vides ment sur la régularité, qui est justement ce
     * qu'il montre.
     *
     * @return array{jours: list<string>, valeurs: list<int>}
     */
    public function activite(User $utilisateur, int $jours): array
    {
        $depuis = now()->subDays($jours - 1)->startOfDay();

        $parJour = Verification::where('user_id', $utilisateur->id)
            ->where('created_at', '>=', $depuis)
            ->get(['created_at'])
            ->countBy(fn (Verification $v): string => $v->created_at->toDateString());

        $etiquettes = [];
        $valeurs = [];

        for ($i = $jours - 1; $i >= 0; $i--) {
            $jour = now()->subDays($i);
            $etiquettes[] = $jour->format('j/n');
            $valeurs[] = (int) ($parJour[$jour->toDateString()] ?? 0);
        }

        return ['jours' => $etiquettes, 'valeurs' => $valeurs];
    }

    /**
     * Les sept jours de la semaine en cours, actifs ou non.
     *
     * @return list<array{lettre: string, actif: bool, aujourdhui: bool, futur: bool}>
     */
    public function semaine(User $utilisateur): array
    {
        $debut = now()->startOfWeek();

        $actifs = Verification::where('user_id', $utilisateur->id)
            ->where('created_at', '>=', $debut)
            ->get(['created_at'])
            ->map(fn (Verification $v): string => $v->created_at->toDateString())
            ->unique()
            ->all();

        $lettres = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
        $semaine = [];

        foreach ($lettres as $i => $lettre) {
            $jour = $debut->clone()->addDays($i);
            $semaine[] = [
                'lettre' => $lettre,
                'actif' => in_array($jour->toDateString(), $actifs, true),
                'aujourdhui' => $jour->isToday(),
                'futur' => $jour->isFuture(),
            ];
        }

        return $semaine;
    }

    /** Leçons achevées, en tout. */
    public function leconsTerminees(User $utilisateur): int
    {
        return LessonCompletion::where('user_id', $utilisateur->id)
            ->whereNotNull('completed_at')->count();
    }

    /** Leçons achevées depuis lundi. */
    public function leconsCetteSemaine(User $utilisateur): int
    {
        return LessonCompletion::where('user_id', $utilisateur->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();
    }
}
