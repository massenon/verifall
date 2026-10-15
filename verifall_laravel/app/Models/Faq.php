<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Une question fréquente, dans UNE langue.
 *
 * Le site sert le français et l'anglais. Une question existe donc en deux
 * lignes, reliées par `group_key` — sans quoi corriger la version française ne
 * permettrait pas de retrouver l'anglaise à corriger aussi.
 */
final class Faq extends Model
{
    protected $fillable = [
        'uuid', 'locale', 'group_key', 'category',
        'question', 'answer', 'sort_order', 'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Les questions publiées dans la langue demandée.
     *
     * Repli sur le français : une question ajoutée en français et pas encore
     * traduite doit rester lisible plutôt que de disparaître de la page
     * anglaise. Une FAQ incomplète vaut mieux qu'une FAQ trouée.
     *
     * @param  Builder<self>  $requete
     * @return Builder<self>
     */
    public function scopePourLaLangue(Builder $requete, string $langue): Builder
    {
        return $requete
            ->where('is_published', true)
            ->where(function (Builder $q) use ($langue): void {
                $q->where('locale', $langue)
                    ->orWhereNotExists(function ($sous) use ($langue): void {
                        $sous->selectRaw('1')
                            ->from('faqs as traduction')
                            ->whereColumn('traduction.group_key', 'faqs.group_key')
                            ->where('traduction.locale', $langue)
                            ->where('traduction.is_published', true);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
