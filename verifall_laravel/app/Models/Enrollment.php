<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutInscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'inscription de quelqu'un à un cours.
 *
 * Le quatrième état du catalogue — « pas inscrit » — est l'ABSENCE de cette
 * ligne. Créer une inscription pour chaque personne qui ouvre une fiche de
 * cours gonflerait le nombre d'inscrits affiché, qui est justement ce sur quoi
 * les gens jugent un cours.
 */
final class Enrollment extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'course_id', 'status', 'progress',
        'paid_units', 'started_at', 'last_activity_at', 'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutInscription::class,
            'progress' => 'integer',
            'paid_units' => 'integer',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'xp_awarded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function estTerminee(): bool
    {
        return $this->status === StatutInscription::Termine;
    }

    /**
     * Libellé du bouton, dans l'état où se trouve la personne.
     *
     * Le prototype change ce libellé à chaque état, et il a raison :
     * « Commencer » sur un cours à moitié fait donnerait l'impression que la
     * progression a été perdue.
     */
    public function libelleDeReprise(): string
    {
        return match (true) {
            $this->estTerminee() => 'Revoir le cours',
            $this->progress > 0 => 'Continuer le cours',
            default => 'Commencer le cours',
        };
    }
}
