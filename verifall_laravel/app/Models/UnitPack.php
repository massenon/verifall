<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un pack d'unités mis en vente.
 *
 * Le prix et la quantité ne sont JAMAIS lus dans la requête du client : celui-ci
 * n'envoie qu'un code. C'est ce qui empêche d'acheter soixante unités au prix
 * de dix en modifiant un formulaire.
 */
final class UnitPack extends Model
{
    protected $fillable = [
        'uuid', 'code', 'label', 'units', 'price_fcfa',
        'is_featured', 'is_active', 'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'units' => 'integer',
            'price_fcfa' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'pack', 'code');
    }

    /**
     * @param  Builder<self>  $requete
     * @return Builder<self>
     */
    public function scopeEnVente(Builder $requete): Builder
    {
        return $requete->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Le prix d'une unité, en francs.
     *
     * Sert à comparer les packs entre eux sur l'écran d'administration : c'est
     * le seul chiffre qui dit si un pack est vraiment plus avantageux qu'un
     * autre, et le seul qu'on ne peut pas lire directement dans le tableau.
     */
    public function prixParUnite(): float
    {
        return $this->units > 0 ? $this->price_fcfa / $this->units : 0.0;
    }
}
