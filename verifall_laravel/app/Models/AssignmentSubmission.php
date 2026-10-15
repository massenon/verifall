<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutDevoir;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un exercice remis, en attente ou corrigé. */
final class AssignmentSubmission extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'assignment_id', 'status', 'note',
        'file_path', 'file_name',
        'reviewed_by', 'feedback', 'grade', 'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutDevoir::class,
            'grade' => 'integer',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
