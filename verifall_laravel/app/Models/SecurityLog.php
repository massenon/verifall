<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Événement de sécurité — alimente la page « Alertes Intrusion ».
 *
 * @property string $event
 * @property string $severity
 * @property array<string, mixed>|null $context
 */
final class SecurityLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'event', 'severity', 'user_id', 'identity_hash', 'ip_hash', 'user_agent', 'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
