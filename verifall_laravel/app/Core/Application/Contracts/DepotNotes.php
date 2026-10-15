<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Enums\StatutNoteCommunaute;
use App\Models\CommunityNote;
use App\Models\CommunityNoteVote;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Accès aux notes de communauté et à leurs votes.
 *
 * Ce dépôt porte une particularité que les autres n'ont pas : il sert DEUX
 * lecteurs aux besoins opposés.
 *
 *   · L'écran d'un sujet, qui veut trois notes et les veut vite.
 *   · La commande planifiée de pontage, qui veut TOUS les votes du forum d'un
 *     coup — parce que déterminer qui vote habituellement avec qui n'a aucun
 *     sens sujet par sujet. Cette lecture-là est volumineuse et ne doit jamais
 *     être déclenchée depuis une requête web.
 *
 * Les deux sont nommées distinctement pour qu'on ne se trompe pas d'appel.
 */
interface DepotNotes
{
    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): CommunityNote;

    public function parUuid(string $uuid): ?CommunityNote;

    /**
     * Les notes VISIBLES DE TOUS sur un sujet.
     *
     * @return Collection<int, CommunityNote>
     */
    public function publieesPourSujet(Thread $sujet): Collection;

    /**
     * Les notes de quelqu'un sur un sujet, quel que soit leur état.
     *
     * Une note en attente reste visible de son auteur : lui cacher ce qu'il a
     * écrit lui laisserait croire à une perte de données, et il la réécrirait.
     *
     * @return Collection<int, CommunityNote>
     */
    public function notesDeLAuteur(Thread $sujet, User $auteur): Collection;

    /**
     * Les notes EN ATTENTE des autres, à soumettre à l'évaluation du lecteur.
     *
     * Sans cette file, le mécanisme serait mort-né : une note en attente que
     * personne ne peut voir ne reçoit aucun vote, donc ne franchit jamais le
     * pont, donc n'est jamais publiée. Voir `docs/ARBITRAGES.md`, entrée 162.
     *
     * @return Collection<int, CommunityNote>
     */
    public function enAttentePourSujet(Thread $sujet, User $lecteur): Collection;

    /** A-t-elle déjà été proposée par cette personne sur ce sujet ? */
    public function compterNotesDeLAuteurSurSujet(Thread $sujet, User $auteur): int;

    public function voteDe(CommunityNote $note, User $votant): ?CommunityNoteVote;

    /**
     * Pose ou REMPLACE le vote de quelqu'un sur une note.
     *
     * Remplace, jamais n'ajoute : deux lignes pour la même personne feraient
     * croire à l'algorithme qu'un votant acharné est une communauté.
     */
    public function enregistrerVote(CommunityNote $note, User $votant, bool $utile): CommunityNoteVote;

    /** Recalcule les compteurs dénormalisés d'une note. */
    public function recompter(CommunityNote $note): CommunityNote;

    /**
     * Les votes de la personne sur les notes d'un sujet.
     *
     * @return array<int, bool> identifiant de note => utile
     */
    public function mesVotesSurSujet(Thread $sujet, User $votant): array;

    /**
     * TOUS les votes du forum, pour la commande planifiée.
     *
     * Volumineux par nature — c'est le prix d'un calcul qui compare chaque
     * paire de votants. Jamais appelé depuis une requête web.
     *
     * @return array<int, array<int, bool>> identifiant de note => [identifiant de votant => utile]
     */
    public function matriceDesVotes(): array;

    /**
     * Écrit le résultat du calcul de pontage.
     *
     * Le score peut être NUL : cela veut dire « pas mesurable », ce qui n'est
     * pas la même chose que « nul ». Écrire 0 à la place effacerait la
     * différence entre une note qui attend et une note qui a échoué.
     */
    public function appliquerScore(CommunityNote $note, ?float $score, StatutNoteCommunaute $statut): void;

    /**
     * Les notes candidates au calcul : celles qui ont au moins un vote.
     *
     * @return Collection<int, CommunityNote>
     */
    public function notesAEvaluer(): Collection;

    /** Combien de notes de cette personne ont franchi le pont. */
    public function compterNotesPubliees(User $auteur): int;

    /** Vide le cache de lecture d'un sujet. */
    public function oublierLeCache(Thread $sujet): void;
}
