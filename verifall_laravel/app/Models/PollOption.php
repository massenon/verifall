<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une réponse possible d'un sondage. */
final class PollOption extends Model
{
    protected $fillable = ['uuid', 'poll_id', 'label', 'position', 'votes_count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'votes_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * Part de cette option, en pourcentage entier.
     *
     * Sans aucun vote, la part vaut ZÉRO et non « une part égale » : afficher
     * des barres identiques laisserait croire à un partage d'opinions là où
     * personne ne s'est prononcé (règle §5 du chantier).
     */
    public function part(int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) round($this->votes_count / $total * 100);
    }
}
