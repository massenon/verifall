<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Models\Reply;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Les gestes de modération sur le forum — verrouiller, épingler, retirer.
 *
 * ## Ce que ce service N'EST PAS
 *
 * Ce n'est pas la file de signalements : `SignalementDeContenu` reçoit ce que
 * les citoyens portent à la modération, et `Staff\SignalementsContenu` la
 * traite. Ici, il s'agit des gestes qu'un modérateur pose **de sa propre
 * initiative**, sur un sujet qu'il lit — souvent sans qu'aucun signalement
 * n'existe.
 *
 * ## Verrouiller n'est pas supprimer, et c'est la distinction qui compte
 *
 * Un sujet verrouillé reste LISIBLE : on voit ce qui a été dit, et qu'un terme
 * y a été mis. C'est le geste ordinaire d'un débat qui dérape. La suppression
 * fait disparaître la discussion, y compris les réponses de bonne foi et les
 * notes de communauté qui l'accompagnent.
 *
 * La matrice sépare d'ailleurs les deux verbes : `forum.moderate` couvre le
 * verrouillage et l'épinglage, `forum.delete` la suppression. Un modérateur
 * porte les deux, mais ce sont deux décisions.
 *
 * ## L'escalade est une demande, pas un verdict
 *
 * `Thread` distingue `escalated_by` de `verified_by` — le modèle le documente
 * lui-même : « escalader, c'est demander ; apposer un verdict, c'est répondre ».
 * Ce service ne fait qu'escalader. Le verdict revient au fact-checking.
 */
final class ModerationDuForum
{
    /**
     * Verrouille ou déverrouille un sujet.
     *
     * @return bool le nouvel état
     */
    public function basculerLeVerrou(Thread $sujet): bool
    {
        $verrouille = ! $sujet->is_locked;

        $sujet->forceFill(['is_locked' => $verrouille])->save();

        return $verrouille;
    }

    /**
     * Épingle ou désépingle un sujet.
     */
    public function basculerLEpingle(Thread $sujet): bool
    {
        $epingle = ! $sujet->is_pinned;

        $sujet->forceFill(['is_pinned' => $epingle])->save();

        return $epingle;
    }

    /**
     * Escalade un sujet vers le fact-checking.
     *
     * @throws RuntimeException si le sujet est déjà escaladé ou déjà tranché
     */
    public function escalader(Thread $sujet, User $demandeur): Thread
    {
        if ($sujet->official_verdict !== null) {
            throw new RuntimeException('Ce sujet porte déjà un verdict officiel.');
        }

        if ($sujet->escalated_at !== null) {
            /*
             | Escalader deux fois écraserait le nom du premier demandeur et la
             | date d'origine — c'est-à-dire l'ancienneté de la demande, seule
             | chose qui permette d'ordonner la file du fact-checking.
             */
            throw new RuntimeException('Ce sujet a déjà été transmis au fact-checking.');
        }

        $sujet->forceFill([
            'escalated_by' => $demandeur->id,
            'escalated_at' => now(),
        ])->save();

        return $sujet;
    }

    /**
     * Retire un sujet et tout ce qui s'y rattache.
     *
     * ## Le compte de ce qui disparaît, rendu à l'appelant
     *
     * L'écran doit pouvoir dire AVANT le geste combien de réponses partent
     * avec le sujet. Une discussion de quarante messages ne se supprime pas
     * comme un message isolé, et le nombre est la seule information qui fasse
     * la différence à l'instant du clic.
     *
     * @return int le nombre de réponses emportées
     */
    public function supprimerLeSujet(Thread $sujet): int
    {
        return DB::transaction(function () use ($sujet): int {
            $reponses = $sujet->replies()->count();

            // Les réponses partent explicitement : le schéma cascade, mais
            // compter d'abord permet de le dire, et de ne pas dépendre d'un
            // comportement de base pour une décision d'interface.
            $sujet->replies()->delete();
            $sujet->delete();

            return $reponses;
        });
    }

    /**
     * Retire une réponse, sans toucher au sujet.
     *
     * Une réponse enfant reste : la supprimer en chaîne ferait disparaître des
     * messages qui n'ont rien à se reprocher, et laisserait la discussion
     * incompréhensible.
     */
    public function supprimerLaReponse(Reply $reponse): void
    {
        $reponse->delete();
    }
}
