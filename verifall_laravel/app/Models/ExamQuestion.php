<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le fait qu'une question de la banque soit posée dans un examen, à un rang.
 *
 * Table de liaison avec un modèle à elle : l'ordre des questions se manipule
 * (glisser-déposer du studio), et une pivot anonyme rendrait ce réordonnancement
 * illisible depuis un service.
 */
final class ExamQuestion extends Model
{
    protected $fillable = ['exam_id', 'question_bank_item_id', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<QuestionBankItem, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBankItem::class, 'question_bank_item_id');
    }
}
