<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une réponse dans un fil.
 *
 * `is_official` distingue la parole de la PLATEFORME de celle d'un modérateur
 * qui s'exprime. Elle doit se reconnaître au premier coup d'œil : c'est elle
 * qui engage Vérif'All.
 */
final class Reply extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'thread_id', 'user_id', 'parent_id', 'body', 'is_official'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Hors $fillable : le repos entre deux courriels de réaction est
            // posé par le service qui envoie, jamais par un formulaire.
            'reaction_notified_at' => 'datetime', 'is_official' => 'boolean', 'likes_count' => 'integer'];
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
     * @return HasMany<Reply, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Reply::class, 'parent_id')->orderBy('id');
    }

    /**
     * @return MorphMany<ContentLike, $this>
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(ContentLike::class, 'likeable');
    }
}
