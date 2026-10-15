<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TypeCampagne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Une campagne d'affichage du site public : fenêtre ou bandeau.
 *
 * À ne pas confondre avec `Campaign`, qui porte les campagnes d'e-mailing.
 * Voir la migration pour la raison de cette séparation.
 *
 * Deux formes seulement — la fenêtre et le bandeau — parce que ce sont les
 * deux que le design system du prototype sait rendre. Une troisième
 * demanderait un gabarit, pas une valeur de plus dans une colonne.
 */
final class MarketingCampaign extends Model
{
    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'uuid', 'slug', 'title', 'text', 'cta', 'url', 'image',
        'color', 'type', 'is_active', 'delay_seconds',
        'starts_at', 'ends_at', 'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeCampagne::class,
            'is_active' => 'boolean',
            'delay_seconds' => 'integer',
            'position' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Les campagnes réellement diffusables MAINTENANT.
     *
     * Trois conditions, et les trois comptent. `is_active` est l'interrupteur
     * humain ; les deux dates sont la programmation. Une campagne d'échéance
     * laissée active après coup décrédibilise le site plus qu'elle ne l'a servi
     * avant.
     *
     * @param  Builder<MarketingCampaign>  $requete
     * @return Builder<MarketingCampaign>
     */
    public function scopeDiffusable(Builder $requete): Builder
    {
        return $requete
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('position');
    }

    /**
     * La destination part-elle chez un tiers ?
     *
     * Une ancre (`#infolettre`) et un chemin (`/verifier-une-information`)
     * restent internes ; une adresse complète sort du site et prend donc
     * `target="_blank"` avec `rel="noopener"` — sans quoi la page de
     * destination peut rediriger la nôtre par `window.opener`.
     */
    public function estExterne(): bool
    {
        return str_starts_with($this->url, 'http');
    }
}
