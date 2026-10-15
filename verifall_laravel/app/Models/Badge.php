<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un badge de l'Achievement Center.
 *
 * La CONDITION est portée par le slug et non par une colonne de règles : une
 * règle stockée en base — « champ ≥ valeur » — serait à la fois illisible et
 * incapable d'exprimer « intox détectées », qui suppose une jointure. Le slug
 * est stable, la condition vit dans le code, et l'un ne peut pas diverger de
 * l'autre sans qu'un test le voie.
 */
final class Badge extends Model
{
    protected $fillable = [
        'uuid', 'slug', 'icon', 'name', 'description',
        'requirement_label', 'xp_reward', 'units_reward', 'position', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'xp_reward' => 'integer',
            'units_reward' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')->withPivot('unlocked_at');
    }

    /**
     * Ce badge est-il acquis, au vu des mesures ?
     *
     * Un slug inconnu rend `false` et non une erreur : un badge désactivé mal
     * nettoyé ne doit pas faire tomber la page des récompenses.
     *
     * @param  array<string, int>  $mesures
     */
    public function estAcquis(array $mesures): bool
    {
        return match ($this->slug) {
            'premiere-verification' => ($mesures['verifications'] ?? 0) >= 1,
            'verificateur-debutant' => ($mesures['lecons'] ?? 0) >= 3,
            'premier-quiz' => ($mesures['quiz'] ?? 0) >= 1,
            'diplome' => ($mesures['certificats'] ?? 0) >= 1,
            'serie-sept-jours' => ($mesures['serie'] ?? 0) >= 7,
            'chasseur-intox' => ($mesures['intox'] ?? 0) >= 5,
            'verificateur-confirme' => ($mesures['xp'] ?? 0) >= 1000,
            'ambassadeur' => ($mesures['filleuls'] ?? 0) >= 3,
            default => false,
        };
    }
}
