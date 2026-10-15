<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace d'un consentement.
 *
 * Immuable par convention : un retrait n'écrase pas la ligne d'octroi, il en
 * ajoute une nouvelle avec granted = false. C'est cet historique qui constitue
 * la preuve exigée par le RGPD.
 *
 * @property ConsentType $type
 * @property string $document_version
 * @property bool $granted
 */
final class Consent extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'type', 'document_version', 'granted', 'ip_hash', 'user_agent', 'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'granted' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
