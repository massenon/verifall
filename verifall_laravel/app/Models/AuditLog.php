<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrée du journal d'audit.
 *
 * @property string $action
 * @property string $module
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 */
final class AuditLog extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'actor_id', 'actor_label', 'action', 'module',
        'target_type', 'target_uuid', 'target_label',
        'before', 'after', 'ip_hash', 'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
