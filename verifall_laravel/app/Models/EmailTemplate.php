<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La surcharge du SUJET d'un courriel.
 *
 * Pas de corps : les six courriels de la plateforme portent de la logique — un
 * code à usage unique et son délai, un lien signé, un tableau hebdomadaire. Un
 * corps modifiable en base perdrait ces variables au premier enregistrement, et
 * le seul symptôme serait un courriel envoyé sans son code.
 *
 * Une ligne absente n'est pas une anomalie : c'est « le sujet écrit en code
 * convient ». `GabaritsDEmail` s'y replie.
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
