<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une campagne d'e-mailing.
 *
 * À ne pas confondre avec `MarketingCampaign`, qui porte les fenêtres et
 * bandeaux du site public. Celle-ci part par courriel vers une audience ;
 * l'autre s'affiche à qui passe. Le vocabulaire du prototype emploie
 * malheureusement le même mot pour les deux.
 *
 * Les trois compteurs — destinataires, envoyés, échoués — existent ensemble à
 * dessein : une campagne qui n'afficherait que ses succès laisserait croire
 * qu'elle a touché tout le monde.
 */
final class Campaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'subject', 'body',
        'audience', 'status', 'scheduled_at', 'sent_at',
        'recipients_count', 'sent_count', 'failed_count', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
