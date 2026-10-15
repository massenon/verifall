<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PorteeOpportunite;
use App\Enums\TypeOpportunite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une opportunité du Hub : emploi, stage, bourse, conférence, financement.
 *
 * `is_verified` n'est pas décoratif : sur un hub béninois, la fausse offre qui
 * réclame des « frais de dossier » est l'arnaque la plus répandue. Publier une
 * opportunité sans dire si elle a été contrôlée reviendrait à la cautionner.
 */
final class HubJob extends Model
{
    use SoftDeletes;

    /**
     * Délai de grâce après la date limite.
     *
     * Une offre ne disparaît pas le jour de son échéance : les candidatures
     * arrivées la veille se traitent encore, et une annonce retirée trop tôt
     * laisse croire qu'elle n'a jamais existé à qui vient de postuler. Une
     * semaine plus tard, elle ne sert plus qu'à faire perdre du temps.
     */
    public const JOURS_DE_GRACE = 7;

    protected $fillable = [
        'uuid', 'slug', 'title', 'organisation', 'scope', 'type', 'location',
        'description', 'apply_url', 'deadline', 'is_verified', 'verified_by',
        'is_published', 'published_at', 'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => PorteeOpportunite::class,
            'type' => TypeOpportunite::class,
            'deadline' => 'date',
            'is_verified' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** La date limite est-elle passée ? */
    public function estExpiree(): bool
    {
        return $this->deadline !== null && $this->deadline->isPast();
    }

    /**
     * Le délai de grâce est-il écoulé ?
     *
     * C'est ce que la commande de nettoyage vérifie avant de retirer une offre.
     */
    public function graceEcoulee(): bool
    {
        return $this->deadline !== null
            && $this->deadline->copy()->addDays(self::JOURS_DE_GRACE)->isPast();
    }

    /**
     * Combien de jours reste-t-il pour candidater ?
     *
     * Négatif une fois l'échéance passée : l'écran s'en sert pour dire
     * « clôturée » plutôt que « il reste -3 jours ».
     */
    public function joursRestants(): ?int
    {
        if ($this->deadline === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->deadline, absolute: false);
    }
}
