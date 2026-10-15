<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Compteur d'échecs de connexion par couple (identité, IP).
 *
 * @property string $identity_hash
 * @property string $ip_hash
 * @property int $failures
 * @property Carbon|null $locked_until
 * @property Carbon|null $last_failure_at
 */
final class LoginAttempt extends Model
{
    /** @var list<string> */
    protected $fillable = ['identity_hash', 'ip_hash', 'failures', 'locked_until', 'last_failure_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failures' => 'integer',
            'locked_until' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }
}
