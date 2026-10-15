<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActionMotClef;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un mot surveillé par la modération du forum. */
final class ModerationKeyword extends Model
{
    protected $fillable = [
        'uuid', 'term', 'action', 'note', 'is_active', 'hits', 'last_hit_at', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ActionMotClef::class,
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
