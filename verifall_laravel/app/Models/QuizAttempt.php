<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une tentative de quiz.
 *
 * Volontairement multiples : garder l'historique des essais est ce qui permet
 * de dire « vous progressez » plutôt que « vous avez échoué ».
 */
final class QuizAttempt extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'quiz_id', 'score', 'passed',
        'duration_s', 'given_answers',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'passed' => 'boolean',
            'duration_s' => 'integer',
            'given_answers' => 'array',
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
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
