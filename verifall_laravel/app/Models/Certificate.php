<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un certificat délivré.
 *
 * Le NOM et l'INTITULÉ sont recopiés à l'émission, jamais lus par jointure. Un
 * certificat atteste de ce qui a été suivi CE jour-là : si le cours est renommé
 * ou le compte anonymisé après un effacement RGPD, le document déjà délivré ne
 * doit ni se mettre à mentir, ni redevenir nominatif.
 */
final class Certificate extends Model
{
    protected $fillable = [
        'uuid', 'code', 'user_id', 'course_id',
        'holder_name', 'course_title',
        'issued_at', 'revoked_at', 'revoked_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function estValide(): bool
    {
        return $this->revoked_at === null;
    }
}
