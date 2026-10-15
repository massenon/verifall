<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TypeQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une réponse dans une copie.
 *
 * L'énoncé, le type et le barème sont recopiés : le correcteur doit pouvoir
 * lire la copie même si la question a été retirée de la banque entre-temps.
 * Sans cette copie du texte, une révision de la banque rendrait illisibles
 * toutes les corrections en attente.
 */
final class ExamAnswer extends Model
{
    protected $fillable = [
        'exam_attempt_id', 'question_bank_item_id', 'position',
        'prompt', 'type', 'points_max',
        'given_text', 'given_options',
        'is_correct', 'points_awarded', 'grader_comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeQuestion::class,
            'position' => 'integer',
            'points_max' => 'integer',
            'given_options' => 'array',
            'is_correct' => 'boolean',
            'points_awarded' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function copie(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    /**
     * @return BelongsTo<QuestionBankItem, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBankItem::class, 'question_bank_item_id');
    }

    /** Attend-elle encore l'œil d'un mentor ? */
    public function attendUneCorrection(): bool
    {
        return $this->points_awarded === null;
    }
}
