<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Un partenaire affiché sur la page d'accueil. */
final class Partner extends Model
{
    protected $fillable = [
        'uuid', 'name', 'logo_path', 'url', 'description', 'sort_order', 'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_published' => 'boolean'];
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
        return $requete->where('is_published', true)->orderBy('sort_order')->orderBy('name');
    }
}
