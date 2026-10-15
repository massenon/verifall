<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une inscription à une séance en direct. */
final class LiveClassRegistration extends Model
{
    protected $fillable = ['user_id', 'live_class_id', 'attended_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LiveClass, $this>
     */
    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(LiveClass::class);
    }
}
