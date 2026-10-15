<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une leçon lue.
 *
 * Les SECONDES comptent : sans temps de lecture minimal, terminer un cours se
 * réduirait à cliquer six fois sur « Suivant », et le certificat ne vaudrait
 * rien — ni pour celui qui le montre, ni pour celui qui le lit.
 */
final class LessonCompletion extends Model
{
    protected $fillable = ['user_id', 'lesson_id', 'seconds', 'completed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['seconds' => 'integer', 'completed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
