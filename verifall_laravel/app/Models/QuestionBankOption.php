<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une proposition de réponse dans la banque.
 *
 * Une table et non un tableau JSON, pour la même raison que `answers` : c'est
 * `picked_count` qui dit au mentor QUELLE mauvaise réponse attire, donc où sa
 * leçon n'accroche pas. Un JSON ne se compte pas.
 */
final class QuestionBankOption extends Model
{
    protected $fillable = [
        'question_bank_item_id', 'position', 'label', 'match_label', 'is_correct',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_correct' => 'boolean',
            'picked_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<QuestionBankItem, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBankItem::class, 'question_bank_item_id');
    }
}
