<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutCopieExamen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une copie : ce qu'une personne a rendu, un jour, pour un examen.
 *
 * `pass_score` et `max_points` sont recopiés ici et n'en bougent plus. Une note
 * est un fait daté : relever le seuil de l'examen ne doit pas recaler après
 * coup ceux qui l'avaient franchi.
 */
final class ExamAttempt extends Model
{
    protected $fillable = [
        'uuid', 'reference', 'exam_id', 'exam_session_id', 'user_id',
        'attempt_no', 'status', 'started_at', 'submitted_at',
        'pass_score', 'max_points', 'score_points', 'score_percent', 'passed',
        'assigned_to', 'assigned_at', 'graded_by', 'graded_at', 'feedback',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutCopieExamen::class,
            'attempt_no' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'pass_score' => 'integer',
            'max_points' => 'integer',
            'score_points' => 'integer',
            'score_percent' => 'integer',
            'passed' => 'boolean',
            'assigned_at' => 'datetime',
            'graded_at' => 'datetime',
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
     * @return BelongsTo<ExamSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function correcteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<ExamAnswer, $this>
     */
    public function reponses(): HasMany
    {
        return $this->hasMany(ExamAnswer::class)->orderBy('position');
    }

    /**
     * Depuis combien d'heures cette copie attend-elle un correcteur ?
     *
     * `null` si elle n'a pas été rendue : une copie en composition n'attend
     * personne, et la compter dans les « urgentes » afficherait une alerte que
     * rien ne justifie.
     */
    public function heuresDAttente(): ?int
    {
        if ($this->submitted_at === null || $this->status->estFigee()) {
            return null;
        }

        return (int) $this->submitted_at->diffInHours(now());
    }

    /** Le seuil des 72 h affiché en tête de la file de correction. */
    public function estUrgente(): bool
    {
        return ($this->heuresDAttente() ?? 0) > 72;
    }
}
