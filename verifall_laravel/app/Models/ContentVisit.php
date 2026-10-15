<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une visite sur un contenu public.
 *
 * Ni IP, ni agent utilisateur, ni identifiant de compte : voir la migration
 * `create_content_visits` pour ce que `visitor_hash` garantit — et pour ce
 * qu'il rend impossible.
 */
final class ContentVisit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'visitable_type', 'visitable_id', 'visitor_hash',
        'referrer_host', 'is_bot', 'visited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'visited_at' => 'datetime',
        ];
    }

    public function visitable(): MorphTo
    {
        return $this->morphTo();
    }
}
