<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutQuestion;
use App\Enums\TypeQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une question de la banque, réutilisable d'un examen à l'autre.
 *
 * Distincte de `Question`, qui appartient à UN quiz et n'en sort jamais. Ici la
 * question est un objet de catalogue : elle a un barème, un cycle de vie, et un
 * taux de réussite qui dit si elle est bien écrite.
 */
final class QuestionBankItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'course_id', 'type', 'prompt', 'explanation',
        'points', 'status', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeQuestion::class,
            'status' => StatutQuestion::class,
            'points' => 'integer',
            'answers_count' => 'integer',
            'correct_count' => 'integer',
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
     * @return HasMany<QuestionBankOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionBankOption::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Exam, $this>
     */
    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_questions')->withPivot('position');
    }

    /** Se corrige-t-elle sans mentor ? */
    public function seCorrigeSeule(): bool
    {
        return $this->type->correctionAutomatique();
    }

    /**
     * Taux de réussite, ou `null` tant que personne n'y a répondu.
     *
     * `null` et non zéro : une question jamais posée n'a pas 0 % de réussite,
     * elle n'a pas encore de taux. Afficher « 0 % » la ferait passer pour
     * impossible et déclencherait une révision inutile.
     */
    public function tauxDeReussite(): ?int
    {
        if ($this->answers_count === 0) {
            return null;
        }

        return (int) round($this->correct_count / $this->answers_count * 100);
    }
}
