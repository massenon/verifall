<?php

declare(strict_types=1);

namespace App\Core\Gamification\Services;

use App\Core\Application\Contracts\XpLogRepositoryInterface;
use App\Enums\MotifGainXp;
use App\Models\User;
use App\Models\XpLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Le seul endroit qui écrit `users.xp`.
 *
 * ## Ce qu'il remplace
 *
 * Quatre `$utilisateur->increment('xp', ...)` dispersés — quiz de cours, quiz
 * d'accueil, badges, prime de fin de cours. Chacun était juste ; ensemble, ils
 * rendaient impossible de répondre à « d'où viennent mes points ? ».
 * `increment()` compose d'ailleurs mal avec un compteur qu'on veut journalisé :
 * il écrit en SQL sans relire, donc sans jamais connaître le solde obtenu — et
 * un journal sans `balance_after` ne se vérifie pas ligne à ligne.
 *
 * ## Le verrou n'est pas décoratif
 *
 * Finir un cours peut décrocher un badge dans la foulée : deux crédits sur le
 * même compte, dans la même requête, et bientôt depuis une file d'attente. Sans
 * `lockForUpdate`, les deux lisent le même solde de départ et le second écrase
 * le premier — le compteur perd des points que le journal, lui, aurait
 * enregistrés. Le désaccord entre `somme()` et `users.xp` est exactement ce que
 * ce service existe pour empêcher.
 */
final class JournalDXp
{
    public function __construct(private readonly XpLogRepositoryInterface $journal) {}

    /**
     * Crédite des points et journalise le mouvement, indissociablement.
     *
     * @param  int  $points  strictement positif
     */
    public function crediter(
        User $utilisateur,
        int $points,
        MotifGainXp $motif,
        ?Model $source = null,
        ?string $libelle = null,
    ): XpLog {
        if ($points <= 0) {
            throw new RuntimeException('Un gain d\'XP porte sur un nombre de points strictement positif.');
        }

        return $this->mouvement($utilisateur, $points, $motif, $source, $libelle);
    }

    /**
     * Retire des points — correction d'administrateur uniquement.
     *
     * Non exposé par un écran à ce jour. Il existe parce que la colonne
     * `balance_after` a été dimensionnée signée pour lui, et qu'un retrait qui
     * passerait hors journal ramènerait le problème que ce service corrige.
     *
     * @param  int  $points  strictement positif ; c'est le SENS qui est négatif
     */
    public function retirer(
        User $utilisateur,
        int $points,
        ?Model $source = null,
        ?string $libelle = null,
    ): XpLog {
        if ($points <= 0) {
            throw new RuntimeException('Un retrait d\'XP porte sur un nombre de points strictement positif.');
        }

        return $this->mouvement($utilisateur, -$points, MotifGainXp::AjustementAdmin, $source, $libelle);
    }

    /**
     * @param  int  $delta  signé
     */
    private function mouvement(
        User $utilisateur,
        int $delta,
        MotifGainXp $motif,
        ?Model $source,
        ?string $libelle,
    ): XpLog {
        return DB::transaction(function () use ($utilisateur, $delta, $motif, $source, $libelle): XpLog {
            $verrouille = User::whereKey($utilisateur->getKey())->lockForUpdate()->firstOrFail();

            /*
             | Le total ne descend pas sous zéro. Un retrait plus grand que le
             | solde le ramène à zéro plutôt que d'échouer : contrairement aux
             | unités, l'XP n'achète rien — un solde négatif n'y voudrait rien
             | dire, et refuser la correction laisserait le compte faux.
             |
             | `balance_after` enregistre alors le solde RÉEL, pas le delta
             | demandé : c'est le solde qui fait foi à la relecture.
             */
            $totalApres = max(0, $verrouille->xp + $delta);
            $mouvementReel = $totalApres - $verrouille->xp;

            $verrouille->forceFill(['xp' => $totalApres])->save();

            // L'instance de l'appelant est alignée : la laisser périmée ferait
            // afficher l'ancien total juste après le gain.
            $utilisateur->setAttribute('xp', $totalApres);

            return $this->journal->enregistrer([
                'uuid' => (string) Str::uuid(),
                'user_id' => $verrouille->id,
                'delta' => $mouvementReel,
                'balance_after' => $totalApres,
                'reason' => $motif->value,
                // Canonique : ce qui part en base ne dépend pas de la langue de
                // l'écran. Voir le bloc de documentation de l'enum.
                'label' => $libelle ?? $motif->libelleCanonique(),
                'source_type' => $source !== null ? $source::class : null,
                'source_id' => $source?->getKey(),
            ]);
        });
    }
}
