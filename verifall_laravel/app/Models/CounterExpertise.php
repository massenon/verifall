<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutContreExpertise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une demande de contre-expertise : quelqu'un conteste NOTRE verdict.
 *
 * C'est le retour le plus précieux qu'une plateforme de fact-checking puisse
 * recevoir, et le plus facile à enterrer. Il a donc sa table, sa file et son
 * issue explicite — voir {@see StatutContreExpertise}, qui distingue « nous
 * maintenons » de « nous nous étions trompés ».
 */
final class CounterExpertise extends Model
{
    protected $fillable = [
        'uuid', 'verification_id', 'user_id', 'reason',
        'status', 'handled_by', 'handled_at', 'resolution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutContreExpertise::class,
            'handled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Verification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @param  Builder<CounterExpertise>  $requete
     * @return Builder<CounterExpertise>
     */
    public function scopeEnAttente(Builder $requete): Builder
    {
        return $requete->where('status', StatutContreExpertise::EnAttente->value);
    }
}
