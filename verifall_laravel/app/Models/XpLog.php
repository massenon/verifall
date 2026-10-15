<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Gamification\Services\JournalDXp;
use App\Enums\MotifGainXp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une ligne du journal des points d'expérience.
 *
 * Écrite UNIQUEMENT par {@see JournalDXp}, dans
 * la même transaction que le compteur. Aucune mise à jour n'est prévue : un
 * mouvement corrigé se corrige par un mouvement inverse.
 */
final class XpLog extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'delta', 'balance_after',
        'reason', 'label', 'source_type', 'source_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'balance_after' => 'integer',
            'reason' => MotifGainXp::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
