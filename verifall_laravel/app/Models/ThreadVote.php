<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AvisCommunaute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** L'avis d'une personne sur un signalement — un seul par sujet. */
final class ThreadVote extends Model
{
    protected $fillable = ['thread_id', 'user_id', 'opinion'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opinion' => AvisCommunaute::class];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
