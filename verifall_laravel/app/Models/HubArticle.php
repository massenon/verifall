<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerdictFactCheck;
use App\Observers\ObservateurHubArticle;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une brève du Hub opportunités.
 *
 * Ce n'est pas un agrégateur : chaque brève porte le VERDICT rendu par
 * l'équipe. On n'y relaie pas une information, on dit ce qu'elle vaut.
 */
#[ObservedBy(ObservateurHubArticle::class)]
final class HubArticle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'slug', 'title', 'claim', 'excerpt', 'body', 'category', 'verdict',
        'source_name', 'source_url', 'cover_path', 'verified_fact_id',
        'author_id', 'is_published', 'is_urgent', 'published_at',
        // Lues par `Referencement` depuis l'origine ; les colonnes
        // n'existaient pas, et la surcharge SEO était donc inerte.
        'meta_title', 'meta_description', 'meta_keywords',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verdict' => VerdictFactCheck::class,
            'is_published' => 'boolean',
            // L'urgence décrit la CIRCULATION, pas le sujet : une intox sur la
            // santé n'est pas urgente parce qu'elle parle de santé.
            'is_urgent' => 'boolean',
            // Hors $fillable : une alerte partie ne se déclare pas depuis un
            // formulaire, elle s'horodate depuis le job qui l'expédie.
            'alerted_at' => 'datetime',
            'published_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<VerifiedFact, $this>
     */
    public function verifiedFact(): BelongsTo
    {
        return $this->belongsTo(VerifiedFact::class);
    }

    /**
     * Pastille du Hub, dans le vocabulaire de la page.
     *
     * Le Hub dit « DÉMENTI » là où le rapport dit « FAUX » : ce n'est pas une
     * divergence mais un registre — on dément une rumeur, on qualifie une
     * affirmation. Le verdict stocké, lui, reste unique.
     */
    public function libelleDuBandeau(): string
    {
        return match ($this->verdict) {
            VerdictFactCheck::Vrai => 'VÉRIFIÉ',
            VerdictFactCheck::Faux => 'DÉMENTI',
            VerdictFactCheck::Trompeur => 'TROMPEUR',
            default => 'EN COURS',
        };
    }

    public function classeDuBandeau(): string
    {
        return match ($this->verdict) {
            VerdictFactCheck::Vrai => 'hub-badge hub-badge-ok',
            VerdictFactCheck::Faux => 'hub-badge hub-badge-no',
            VerdictFactCheck::Trompeur => 'hub-badge hub-badge-warn',
            default => 'hub-badge hub-badge-neutral',
        };
    }
}
