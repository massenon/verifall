<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MoteurAnalyse;
use App\Enums\VerdictFactCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ce qu'un moteur a répondu, conservé tel quel.
 *
 * C'est la pièce qui rend la méthode auditable : un lecteur du rapport peut
 * voir quel moteur a dit quoi, en combien de temps, et lesquels ont échoué.
 * Ne garder que le verdict final rendrait la conclusion invérifiable.
 */
final class EngineRun extends Model
{
    protected $fillable = [
        'verification_id', 'claim_id', 'engine', 'verdict', 'confidence',
        'latency_ms', 'failed', 'fail_reason', 'reasoning',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'engine' => MoteurAnalyse::class,
            'verdict' => VerdictFactCheck::class,
            'confidence' => 'integer',
            'latency_ms' => 'integer',
            'failed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Verification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    /**
     * @return BelongsTo<Claim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
