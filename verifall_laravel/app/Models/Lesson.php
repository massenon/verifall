<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TypeLecon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une leçon, à sa place dans l'ordre du cours. */
final class Lesson extends Model
{
    protected $fillable = [
        'uuid', 'course_id', 'position', 'title', 'type',
        'minutes', 'content', 'video_url', 'audio_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeLecon::class,
            'position' => 'integer',
            'minutes' => 'integer',
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
     * Les documents joints, dans l'ordre où le formateur les a rangés.
     *
     * @return HasMany<LessonAttachment, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(LessonAttachment::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<LessonCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }
}
