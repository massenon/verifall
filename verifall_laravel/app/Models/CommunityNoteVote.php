<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le vote d'une personne sur une note — utile, ou pas utile.
 *
 * Ces lignes sont la matière première du calcul de pontage : c'est en les
 * croisant deux à deux qu'on établit qui vote habituellement avec qui. Un
 * doublon fausserait donc bien plus qu'un compteur.
 */
final class CommunityNoteVote extends Model
{
    protected $fillable = ['community_note_id', 'user_id', 'helpful'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['helpful' => 'boolean'];
    }

    /**
     * @return BelongsTo<CommunityNote, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(CommunityNote::class, 'community_note_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
