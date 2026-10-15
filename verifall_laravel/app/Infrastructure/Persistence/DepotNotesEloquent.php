<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotNotes;
use App\Enums\StatutNoteCommunaute;
use App\Models\CommunityNote;
use App\Models\CommunityNoteVote;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class DepotNotesEloquent implements DepotNotes
{
    /**
     * Le cache porte sur les notes PUBLIÉES d'un sujet, et sur elles seules.
     *
     * C'est la lecture chaude : elle se fait à chaque ouverture d'un fil,
     * alors que son contenu ne change qu'au passage de la commande de pontage
     * ou à un vote. Les notes en attente, elles, ne sont lues que par leur
     * auteur — les mettre en cache ferait dix clés pour dix lecteurs uniques.
     */
    private const TTL_MINUTES = 10;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): CommunityNote
    {
        $note = CommunityNote::create($attributs);

        $this->oublierParIdentifiant((int) $note->thread_id);

        return $note;
    }

    public function parUuid(string $uuid): ?CommunityNote
    {
        return CommunityNote::where('uuid', $uuid)->first();
    }

    /**
     * @return Collection<int, CommunityNote>
     */
    public function publieesPourSujet(Thread $sujet): Collection
    {
        /** @var Collection<int, CommunityNote> */
        return Cache::remember(
            $this->cle((int) $sujet->id),
            now()->addMinutes(self::TTL_MINUTES),
            static fn (): Collection => CommunityNote::with('author:id,name')
                ->where('thread_id', $sujet->id)
                ->where('status', StatutNoteCommunaute::Publiee->value)
                /*
                 | Le score de pontage TRIE, il ne s'affiche pas. La note qui a
                 | rassemblé les camps les plus éloignés passe en tête, parce
                 | que c'est elle qui a le plus de chances d'être exacte.
                 */
                ->orderByDesc('bridging_score')
                ->orderBy('id')
                ->get(),
        );
    }

    /**
     * @return Collection<int, CommunityNote>
     */
    public function notesDeLAuteur(Thread $sujet, User $auteur): Collection
    {
        return CommunityNote::with('author:id,name')
            ->where('thread_id', $sujet->id)
            ->where('user_id', $auteur->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, CommunityNote>
     */
    public function enAttentePourSujet(Thread $sujet, User $lecteur): Collection
    {
        return CommunityNote::with('author:id,name')
            ->where('thread_id', $sujet->id)
            ->where('status', StatutNoteCommunaute::EnAttente->value)
            // Les siennes sont rendues à part, avec leur explication : les
            // mélanger à la file d'évaluation laisserait croire qu'on peut
            // voter pour soi.
            ->where(static function ($requete) use ($lecteur): void {
                $requete->whereNull('user_id')->orWhere('user_id', '!=', $lecteur->id);
            })
            // Les plus anciennes d'abord : une note qui attend depuis trois
            // jours doit passer devant celle écrite il y a une minute, sinon
            // les premières ne sont jamais évaluées.
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    public function compterNotesDeLAuteurSurSujet(Thread $sujet, User $auteur): int
    {
        return CommunityNote::where('thread_id', $sujet->id)
            ->where('user_id', $auteur->id)
            ->count();
    }

    public function voteDe(CommunityNote $note, User $votant): ?CommunityNoteVote
    {
        return CommunityNoteVote::where('community_note_id', $note->id)
            ->where('user_id', $votant->id)
            ->first();
    }

    public function enregistrerVote(CommunityNote $note, User $votant, bool $utile): CommunityNoteVote
    {
        return DB::transaction(function () use ($note, $votant, $utile): CommunityNoteVote {
            $existant = CommunityNoteVote::where('community_note_id', $note->id)
                ->where('user_id', $votant->id)
                ->lockForUpdate()
                ->first();

            if ($existant !== null) {
                // REMPLACÉ, jamais ajouté : deux lignes pour la même personne
                // feraient croire à l'algorithme qu'un votant acharné est une
                // communauté.
                $existant->forceFill(['helpful' => $utile])->save();
                $vote = $existant;
            } else {
                $vote = CommunityNoteVote::create([
                    'community_note_id' => $note->id,
                    'user_id' => $votant->id,
                    'helpful' => $utile,
                ]);
            }

            $this->recompter($note);

            return $vote;
        });
    }

    public function recompter(CommunityNote $note): CommunityNote
    {
        $utiles = CommunityNoteVote::where('community_note_id', $note->id)
            ->where('helpful', true)
            ->count();

        $total = CommunityNoteVote::where('community_note_id', $note->id)->count();

        // Recompté et jamais incrémenté : un incrément rejoué fait dériver le
        // compteur, et personne ne s'en aperçoit avant qu'il soit absurde.
        $note->forceFill([
            'helpful_count' => $utiles,
            'not_helpful_count' => $total - $utiles,
        ])->save();

        $this->oublierParIdentifiant((int) $note->thread_id);

        return $note;
    }

    /**
     * @return array<int, bool>
     */
    public function mesVotesSurSujet(Thread $sujet, User $votant): array
    {
        /** @var array<int, bool> $votes */
        $votes = CommunityNoteVote::query()
            ->join('community_notes', 'community_notes.id', '=', 'community_note_votes.community_note_id')
            ->where('community_notes.thread_id', $sujet->id)
            ->where('community_note_votes.user_id', $votant->id)
            ->pluck('community_note_votes.helpful', 'community_note_votes.community_note_id')
            ->map(static fn (mixed $utile): bool => (bool) $utile)
            ->all();

        return $votes;
    }

    /**
     * @return array<int, array<int, bool>>
     */
    public function matriceDesVotes(): array
    {
        $matrice = [];

        /*
         | Par PAQUETS. Cette table grossit avec le forum entier : la charger
         | d'un coup ferait tenir tous les votes de la plateforme en mémoire au
         | moment précis où la commande tourne, c'est-à-dire sur le serveur de
         | production, la nuit, sans personne pour le voir.
         */
        CommunityNoteVote::query()
            ->select(['id', 'community_note_id', 'user_id', 'helpful'])
            ->orderBy('id')
            ->chunk(2000, function (Collection $paquet) use (&$matrice): void {
                foreach ($paquet as $vote) {
                    $matrice[(int) $vote->community_note_id][(int) $vote->user_id] = (bool) $vote->helpful;
                }
            });

        return $matrice;
    }

    public function appliquerScore(CommunityNote $note, ?float $score, StatutNoteCommunaute $statut): void
    {
        $note->forceFill([
            'bridging_score' => $score,
            'status' => $statut->value,
            'scored_at' => now(),
        ])->save();

        $this->oublierParIdentifiant((int) $note->thread_id);
    }

    /**
     * @return Collection<int, CommunityNote>
     */
    public function notesAEvaluer(): Collection
    {
        return CommunityNote::query()
            ->whereIn('id', CommunityNoteVote::query()->select('community_note_id'))
            ->orderBy('id')
            ->get();
    }

    public function compterNotesPubliees(User $auteur): int
    {
        return CommunityNote::where('user_id', $auteur->id)
            ->where('status', StatutNoteCommunaute::Publiee->value)
            ->count();
    }

    public function oublierLeCache(Thread $sujet): void
    {
        $this->oublierParIdentifiant((int) $sujet->id);
    }

    private function oublierParIdentifiant(int $sujetId): void
    {
        Cache::forget($this->cle($sujetId));
    }

    private function cle(int $sujetId): string
    {
        return 'notes-communaute.publiees.'.$sujetId;
    }
}
