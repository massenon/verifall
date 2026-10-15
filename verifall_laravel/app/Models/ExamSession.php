<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutSessionExamen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une session d'examen : une date, un lieu, des places.
 *
 * C'est elle qui rend l'examen distinct du quiz. Un quiz n'a pas d'heure ; une
 * session, si — et tout l'enrôlement en dépend.
 */
final class ExamSession extends Model
{
    protected $fillable = [
        'uuid', 'exam_id', 'starts_at', 'seats',
        'is_proctored', 'location', 'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutSessionExamen::class,
            'starts_at' => 'datetime',
            'seats' => 'integer',
            'is_proctored' => 'boolean',
            'registrations_count' => 'integer',
            'attended_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return HasMany<ExamRegistration, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    /** Reste-t-il de la place ? `null` sur `seats` veut dire « sans limite ». */
    public function placesRestantes(): ?int
    {
        if ($this->seats === null) {
            return null;
        }

        return max(0, $this->seats - $this->registrations_count);
    }

    public function estComplete(): bool
    {
        return $this->placesRestantes() === 0;
    }

    /**
     * Taux de présence, calculé — jamais stocké.
     *
     * `null` et non zéro tant que personne n'est inscrit : « 0 % de présence »
     * sur une session sans candidat est un chiffre inventé, et un chiffre
     * inventé inspire plus confiance qu'une absence de chiffre.
     */
    public function tauxDePresence(): ?int
    {
        if ($this->registrations_count === 0) {
            return null;
        }

        return (int) round($this->attended_count / $this->registrations_count * 100);
    }
}
