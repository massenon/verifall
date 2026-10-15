<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Une trace d'envoi de courriel.
 *
 * ## Le corps n'est PAS ici, et ne doit jamais y venir
 *
 * `OtpCodeMail` documente la règle en toutes lettres : le code « ne doit
 * apparaître NULLE PART ailleurs — ni en journal, ni en base, ni dans une
 * exception ». Stocker le corps des courriels l'y mettrait, pour tous les
 * codes, indéfiniment.
 *
 * Ce journal garde donc le destinataire, l'objet, l'état et l'erreur du
 * transport. Assez pour répondre à « le courriel est-il parti ? » ; pas assez
 * pour créer une fuite.
 */
final class MailLog extends Model
{
    public const ENVOYE = 'sent';

    public const ECHOUE = 'failed';

    protected $fillable = [
        'uuid', 'recipient', 'subject', 'mailable', 'status', 'error', 'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function aEchoue(): bool
    {
        return $this->status === self::ECHOUE;
    }

    /**
     * @param  Builder<self>  $requete
     * @return Builder<self>
     */
    public function scopeEnEchec(Builder $requete): Builder
    {
        return $requete->where('status', self::ECHOUE);
    }
}
