<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un témoignage.
 *
 * `is_published` vaut FAUX par défaut. Un témoignage se relit avant de
 * paraître : c'est la parole de quelqu'un, publiée sous le nom de la
 * plateforme.
 */
final class Testimonial extends Model
{
    protected $fillable = [
        'uuid', 'author', 'role', 'quote', 'avatar_path',
        'rating', 'sort_order', 'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  Builder<self>  $requete
     * @return Builder<self>
     */
    public function scopePublies(Builder $requete): Builder
    {
        return $requete->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }
}
