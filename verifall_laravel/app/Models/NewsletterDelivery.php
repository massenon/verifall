<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une trace d'envoi : cette édition, à cette adresse.
 *
 * C'est la pièce qui rend l'infolettre REJOUABLE sans dégât. Un cron qui
 * déraille, une reprise manuelle après incident, un `queue:work` qui rejoue un
 * travail : les trois arrivent, et sans cette table les trois envoient deux
 * fois. Deux exemplaires du même récapitulatif dans une boîte, c'est un
 * désabonnement — ou un signalement en indésirable, ce qui est pire.
 *
 * La contrainte d'unicité `(campaign_id, newsletter_subscriber_id)` porte la
 * garantie ; elle vit dans la BASE et non dans le code, parce qu'un `if` en
 * PHP ne survit pas à deux processus concurrents.
 *
 * Elle sert aussi à REPRENDRE : un envoi interrompu à mi-course redémarre là où
 * il s'est arrêté, puisqu'on sait exactement qui a déjà reçu.
 */
final class NewsletterDelivery extends Model
{
    protected $fillable = [
        'campaign_id', 'newsletter_subscriber_id',
        'sent_at', 'failed_at', 'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<NewsletterSubscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }
}
