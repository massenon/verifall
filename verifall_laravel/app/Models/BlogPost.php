<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Un article du blog public. */
final class BlogPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'slug', 'blog_category_id', 'author_id',
        'title', 'excerpt', 'body', 'cover_path', 'is_featured',
        'is_published', 'published_at', 'meta_title', 'meta_description', 'meta_keywords',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Titre destiné aux moteurs.
     *
     * Un titre de page réussi n'est pas toujours un bon titre de résultat de
     * recherche : le champ existe pour les distinguer, et retombe sur le titre
     * quand il n'a pas été renseigné.
     */
    public function titreSeo(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function descriptionSeo(): string
    {
        return $this->meta_description ?: $this->excerpt;
    }
}
