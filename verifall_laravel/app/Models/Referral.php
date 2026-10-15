<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutParrainage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un parrainage.
 *
 * Une ligne n'existe qu'à partir de l'INSCRIPTION du filleul. Avant, il n'y a
 * personne à nommer : fabriquer des lignes « invitation envoyée » remplirait la
 * page de fantômes qu'on ne pourrait ni suivre ni relancer.
 */
final class Referral extends Model
{
    protected $fillable = [
        'uuid', 'referrer_id', 'referred_id', 'status',
        'registered_at', 'qualified_at', 'rewarded_at',
        'referrer_points', 'referred_points',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutParrainage::class,
            'registered_at' => 'datetime',
            'qualified_at' => 'datetime',
            'rewarded_at' => 'datetime',
            'referrer_points' => 'integer',
            'referred_points' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    /**
     * Nom affiché du filleul.
     *
     * Le PRÉNOM et l'initiale seulement — « Marie A. », comme le prototype.
     * Afficher l'adresse ou le nom complet livrerait à un parrain des données
     * que son filleul ne lui a pas confiées.
     */
    public function nomDuFilleul(): string
    {
        $nom = $this->referred?->name;

        if ($nom === null || $nom === '') {
            return 'Membre Vérif\'All';
        }

        $morceaux = preg_split('/\s+/', trim($nom)) ?: [];
        $prenom = $morceaux[0] ?? '';
        $initiale = isset($morceaux[1]) ? mb_substr($morceaux[1], 0, 1).'.' : '';

        return trim($prenom.' '.$initiale);
    }
}
