<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutExamen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un examen certifiant de l'Académie.
 *
 * Il se distingue d'un quiz par tout ce qui l'entoure : des sessions datées,
 * des convocations, une correction humaine et un nombre de tentatives compté.
 */
final class Exam extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'slug', 'course_id', 'title', 'description', 'instructions',
        'pass_score', 'duration_minutes', 'max_attempts',
        'status', 'published_at', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutExamen::class,
            'pass_score' => 'integer',
            'duration_minutes' => 'integer',
            'max_attempts' => 'integer',
            'candidates_count' => 'integer',
            'passed_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ExamSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class)->orderBy('starts_at');
    }

    /**
     * @return HasMany<ExamQuestion, $this>
     */
    public function lignesDeQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('position');
    }

    /**
     * Les questions posées, dans l'ordre de l'épreuve.
     *
     * @return BelongsToMany<QuestionBankItem, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBankItem::class, 'exam_questions')
            ->withPivot('position')
            ->orderBy('exam_questions.position');
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function estPublie(): bool
    {
        return $this->status->ouvertALaPassation();
    }

    /**
     * Taux de réussite affiché, en pourcentage entier.
     *
     * Calculé à partir des deux compteurs mesurés plutôt que stocké : un
     * pourcentage figé ne dit plus sur combien de copies il porte, et se met à
     * mentir dès qu'une correction est annulée.
     */
    public function tauxDeReussite(): int
    {
        if ($this->candidates_count === 0) {
            return 0;
        }

        return (int) round($this->passed_count / $this->candidates_count * 100);
    }
}
