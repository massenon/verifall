<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une adresse qui attend la fin de la maintenance.
 *
 * Volontairement SÉPARÉE de `NewsletterSubscriber` : quelqu'un qui laisse son
 * adresse sur une page de maintenance demande un message, une fois, sur un sujet
 * précis. L'inscrire à l'infolettre hebdomadaire détournerait son consentement.
 */
final class MaintenanceWatcher extends Model
{
    protected $fillable = ['email', 'waiting_since', 'notified_at', 'ip_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waiting_since' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
