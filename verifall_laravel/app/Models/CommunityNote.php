<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutNoteCommunaute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une note de communauté attachée à un sujet.
 *
 * Elle ne se publie pas à la majorité. Elle se publie quand des gens qui
 * votent habituellement à l'opposé la jugent tous utile — c'est le seul signal
 * qui distingue « ce qui a des chances d'être vrai » de « ce qui plaît au camp
 * le plus nombreux ».
 */
final class CommunityNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'thread_id', 'user_id', 'body', 'sources',
        'status', 'bridging_score', 'scored_at',
        'helpful_count', 'not_helpful_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'status' => StatutNoteCommunaute::class,
            // Chaîne et non flottant : `decimal:4` conserve la précision
            // stockée. Ce score n'est de toute façon jamais affiché — il ne
            // sert qu'à trier et à comparer au seuil.
            'bridging_score' => 'decimal:4',
            'scored_at' => 'datetime',
            'helpful_count' => 'integer',
            'not_helpful_count' => 'integer',
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
     * @return HasMany<CommunityNoteVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(CommunityNoteVote::class);
    }

    public function totalDesVotes(): int
    {
        return $this->helpful_count + $this->not_helpful_count;
    }

    public function estPubliee(): bool
    {
        return $this->status === StatutNoteCommunaute::Publiee;
    }

    /**
     * Les sources, débarrassées de ce qui n'en est pas une.
     *
     * Filtré À LA LECTURE aussi : une note écrite avant un durcissement de la
     * validation ne doit pas afficher un lien mort comme s'il était une preuve.
     *
     * @return list<string>
     */
    public function sourcesValides(): array
    {
        /** @var list<string> $sources */
        $sources = is_array($this->sources) ? $this->sources : [];

        return array_values(array_filter(
            $sources,
            static fn (mixed $url): bool => is_string($url)
                && filter_var($url, FILTER_VALIDATE_URL) !== false,
        ));
    }
}
