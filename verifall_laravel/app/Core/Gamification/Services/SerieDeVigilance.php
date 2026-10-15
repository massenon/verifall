<?php

declare(strict_types=1);

namespace App\Core\Gamification\Services;

use App\Core\Economy\Services\EconomyService;
use App\Enums\MotifMouvementUnite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * La série — « 🔥 7 jours consécutifs ».
 *
 * Elle récompense la RÉGULARITÉ, pas le volume : revenir chaque jour vaut mieux
 * que tout faire d'un coup, parce que la vigilance est une habitude.
 *
 * Trois règles, chacune corrige une manière de tricher ou de perdre injustement
 * sa série :
 *
 *   · Plusieurs passages LE MÊME JOUR ne comptent qu'une fois. Sinon la série
 *     se gonfle en rafraîchissant une page.
 *   · Un jour manqué remet à UN, pas à zéro : la personne est là aujourd'hui,
 *     lui afficher zéro serait faux.
 *   · La prime de sept jours est versée une fois par palier atteint, jamais à
 *     chaque visite d'un septième jour.
 */
final class SerieDeVigilance
{
    /** Tous les sept jours, le prototype accorde cinq unités. */
    public const PALIER = 7;

    public const UNITES_DE_PALIER = 5;

    public function __construct(private readonly EconomyService $economie) {}

    /**
     * Enregistre une journée d'activité.
     *
     * @return bool vrai si un palier vient d'être franchi
     */
    public function enregistrerUnPassage(User $utilisateur): bool
    {
        $aujourdhui = now()->toDateString();
        $dernier = $utilisateur->streak_last_day?->toDateString();

        // Déjà compté aujourd'hui : rien à faire, et surtout pas de prime.
        if ($dernier === $aujourdhui) {
            return false;
        }

        $hier = now()->subDay()->toDateString();
        $serie = $dernier === $hier ? $utilisateur->streak_days + 1 : 1;

        return DB::transaction(function () use ($utilisateur, $serie): bool {
            $utilisateur->forceFill([
                'streak_days' => $serie,
                'streak_last_day' => now()->toDateString(),
                // Le record ne redescend jamais : c'est ce qui le distingue de
                // la série en cours, laquelle repart à 1 au premier jour manqué.
                'streak_best' => max((int) $utilisateur->streak_best, $serie),
            ])->save();

            if ($serie === 0 || $serie % self::PALIER !== 0) {
                return false;
            }

            $this->economie->crediter(
                $utilisateur,
                self::UNITES_DE_PALIER,
                MotifMouvementUnite::Parrainage,
                sprintf('Série de %d jours consécutifs', $serie),
            );

            return true;
        });
    }

    /**
     * La série est-elle encore vivante ?
     *
     * Une série interrompue reste AFFICHÉE à sa valeur, avec la mention qu'elle
     * est rompue : effacer le chiffre effacerait aussi ce qui avait été
     * accompli.
     */
    public function estVivante(User $utilisateur): bool
    {
        $dernier = $utilisateur->streak_last_day;

        if ($dernier === null) {
            return false;
        }

        return $dernier->isToday() || $dernier->isYesterday();
    }

    /** Jours restants avant la prochaine prime. */
    public function joursAvantLaPrime(User $utilisateur): int
    {
        $reste = self::PALIER - ($utilisateur->streak_days % self::PALIER);

        return $reste === 0 ? self::PALIER : $reste;
    }
}
