<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La surcharge d'un courriel du catalogue : son COPYWRITING, pas sa logique.
 *
 * Modifiables depuis l'administration (arbitrage n° 179) : l'objet, le titre,
 * le corps et le libellé du bouton. Les marqueurs `{{ variable }}` sont limités
 * à ceux que le code fournit (`CatalogueDesCourriels::variables()`), contrôlés à
 * l'enregistrement ; le corps est purifié à l'affichage (`emails/gabarit`).
 *
 * Ce qui reste dans le code et ne se modifie pas ici : ce qui porte de la
 * logique — le code à usage unique et son délai, le lien signé, un tableau
 * hebdomadaire. Ils arrivent par leurs marqueurs ; un corps qui les oublierait
 * est refusé quand le catalogue les déclare obligatoires.
 *
 * Une ligne absente n'est pas une anomalie : c'est « le texte écrit en code
 * convient ». `RenduDeGabarit` s'y replie.
 */
final class EmailTemplate extends Model
{
    protected $fillable = [
        'uuid', 'key', 'subject', 'heading', 'body',
        'cta_label', 'variables', 'is_active', 'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Les marqueurs que l'envoi fournit réellement. Écrite par le semis
            // depuis le catalogue, jamais saisie à la main : c'est le code qui
            // sait ce qu'il passe au gabarit, pas l'écran.
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteurDeLaModification(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
