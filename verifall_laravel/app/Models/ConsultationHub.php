<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une consultation du Hub, rattachée à un compte qui y a CONSENTI.
 *
 * Écrite seulement par `HistoriqueDuHub`, après vérification du consentement ;
 * conservée 90 jours ; effacée au retrait du consentement. Arbitrage n° 178.
 *
 * @property Carbon $created_at
 */
final class ConsultationHub extends Model
{
    public const UPDATED_AT = null;

    public const JOURS_DE_CONSERVATION = 90;

    protected $table = 'consultations_hub';

    /** @var list<string> */
    protected $fillable = ['user_id', 'rubrique', 'cible_id', 'titre', 'action'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
