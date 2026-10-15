<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutSignalementContenu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Un contenu signalé à la modération. */
final class ContentReport extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'reportable_type', 'reportable_id',
        'reason', 'detail', 'status', 'handled_by', 'handled_at', 'handling_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatutSignalementContenu::class,
            'handled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
