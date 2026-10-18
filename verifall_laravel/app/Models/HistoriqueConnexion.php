<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une connexion (réussie, échouée ou bloquée) rattachée à un compte.
 *
 * Écrite seulement par `JournalDesConnexions` ; conservée 12 mois
 * (`verifall:purger-donnees-expirees`). Voir l'arbitrage n° 178.
 *
 * @property string $resultat
 * @property Carbon $created_at
 */
final class HistoriqueConnexion extends Model
{
    public const UPDATED_AT = null;

    public const MOIS_DE_CONSERVATION = 12;

    protected $table = 'historique_connexions';

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'espace', 'resultat', 'methode', 'navigateur', 'systeme',
        'appareil', 'ip_tronquee', 'ip_hash', 'pays',
    ];

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
