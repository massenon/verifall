<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un sondage posé sur un sujet du forum.
 *
 * À ne pas confondre avec l'avis de la communauté sur la véracité : celui-là
 * n'a que trois valeurs et engage la lecture du fil. Ici, l'auteur pose SA
 * question.
 */
final class Poll extends Model
{
    protected $fillable = [
        'uuid', 'thread_id', 'user_id', 'question',
        'is_closed', 'closes_at', 'votes_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
            'closes_at' => 'datetime',
            'votes_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<PollOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    /**
     * @return HasMany<PollVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /** Ouvert au vote ? Une date d'échéance dépassée ferme le sondage. */
    public function estOuvert(): bool
    {
        if ($this->is_closed) {
            return false;
        }

        return $this->closes_at === null || $this->closes_at->isFuture();
    }
}
