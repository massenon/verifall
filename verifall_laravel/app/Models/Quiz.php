<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un quiz chronométré, avec son seuil de réussite. */
final class Quiz extends Model
{
    protected $table = 'quizzes';

    protected $fillable = [
        'uuid', 'course_id', 'position', 'title',
        'minutes', 'pass_score', 'xp_reward',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'minutes' => 'integer',
            'pass_score' => 'integer',
            'xp_reward' => 'integer',
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
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** Durée en secondes — ce que le chronomètre décompte. */
    public function secondes(): int
    {
        return $this->minutes * 60;
    }
}
