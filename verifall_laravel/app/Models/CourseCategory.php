<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une catégorie du catalogue de l'Académie.
 *
 * Remplace la chaîne libre `courses.category`, qui avait laissé deux
 * conventions d'écriture cohabiter — « Fact-Checking » à côté de
 * « investigation » — dans le menu de filtres du citoyen.
 */
final class CourseCategory extends Model
{
    protected $fillable = [
        'uuid', 'name', 'slug', 'description', 'icon', 'sort_order', 'is_published',
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
        return 'slug';
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function cours(): HasMany
    {
        return $this->hasMany(Course::class, 'course_category_id');
    }

    /**
     * @param  Builder<self>  $requete
     * @return Builder<self>
     */
    public function scopePubliees(Builder $requete): Builder
    {
        return $requete->where('is_published', true);
    }
}
