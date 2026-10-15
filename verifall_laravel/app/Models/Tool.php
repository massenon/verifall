<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Une fiche du répertoire d'outils de vérification. */
final class Tool extends Model
{
    protected $fillable = [
        'uuid', 'slug', 'title', 'description', 'category', 'tag', 'icon',
        'url', 'link_label', 'use_cases', 'keywords',
        'is_recommended', 'is_published', 'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'use_cases' => 'array',
            'is_recommended' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<self>  $requete
     * @return Builder<self>
     */
    public function scopePubliees(Builder $requete): Builder
    {
        return $requete->where('is_published', true);
    }

    /**
     * Le texte sur lequel porte la recherche.
     *
     * Titre, résumé et mots-clés réunis : quelqu'un qui cherche « domaine »
     * doit trouver Whois, dont le titre ne contient pas le mot.
     */
    public function matiereDeRecherche(): string
    {
        return mb_strtolower(implode(' ', array_filter([
            $this->title,
            $this->description,
            $this->keywords,
            implode(' ', $this->use_cases ?? []),
        ])));
    }
}
