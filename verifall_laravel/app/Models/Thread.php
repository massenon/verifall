<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AvisCommunaute;
use App\Enums\VerdictFactCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un sujet du forum citoyen.
 *
 * Deux jugements y coexistent sans jamais se confondre : l'AVIS de la
 * communauté, qui est un sondage, et le VERDICT de Vérif'All, qui est une
 * vérification. Les mélanger reviendrait à faire dire à la plateforme ce que
 * pense la majorité — exactement ce contre quoi elle existe.
 */
final class Thread extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'slug', 'user_id', 'forum_category_id',
        'title', 'body', 'source_url',
        'official_verdict', 'verified_by', 'verified_at', 'verification_id',
        // Qui a escaladé ce sujet en fact-check, et quand. Distinct de
        // `verified_by`, qui désigne celui qui a TRANCHÉ : escalader, c'est
        // demander ; apposer un verdict, c'est répondre.
        'escalated_by', 'escalated_at',
        'is_locked', 'is_pinned', 'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Hors $fillable : le repos entre deux courriels de réaction est
            // posé par le service qui envoie, jamais par un formulaire.
            'reaction_notified_at' => 'datetime',
            'official_verdict' => VerdictFactCheck::class,
            'verified_at' => 'datetime',
            'escalated_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_locked' => 'boolean',
            'is_pinned' => 'boolean',
            'replies_count' => 'integer',
            'likes_count' => 'integer',
            'votes_true' => 'integer',
            'votes_doubt' => 'integer',
            'votes_false' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ForumCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    /**
     * @return BelongsTo<Verification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    /**
     * @return HasMany<Reply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    /**
     * @return HasMany<ThreadVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ThreadVote::class);
    }

    /**
     * Qui a demandé l'escalade en fact-check.
     *
     * @return BelongsTo<User, $this>
     */
    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    /**
     * @return MorphMany<ContentLike, $this>
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(ContentLike::class, 'likeable');
    }

    public function totalDesVotes(): int
    {
        return $this->votes_true + $this->votes_doubt + $this->votes_false;
    }

    /**
     * Répartition en pourcentages, pour les barres du prototype.
     *
     * Sans vote, tout est à zéro plutôt qu'à un tiers chacun : afficher trois
     * barres égales laisserait croire à un partage d'opinions là où personne ne
     * s'est prononcé.
     *
     * @return array<string, int>
     */
    public function repartitionDesAvis(): array
    {
        $total = $this->totalDesVotes();

        if ($total === 0) {
            return [
                AvisCommunaute::Vrai->value => 0,
                AvisCommunaute::Doute->value => 0,
                AvisCommunaute::Faux->value => 0,
            ];
        }

        return [
            AvisCommunaute::Vrai->value => (int) round($this->votes_true / $total * 100),
            AvisCommunaute::Doute->value => (int) round($this->votes_doubt / $total * 100),
            AvisCommunaute::Faux->value => (int) round($this->votes_false / $total * 100),
        ];
    }

    /** L'équipe a-t-elle tranché ? */
    public function porteUnVerdictOfficiel(): bool
    {
        return $this->official_verdict !== null;
    }
}
