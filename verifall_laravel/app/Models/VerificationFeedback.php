<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * « Ce rapport vous a-t-il été utile ? » — un avis par personne et par rapport.
 *
 * La table est dessinée par `docs/kb/API_SPECIFICATION.md` §11, qui déclarait
 * l'endpoint `POST /verifications/{uuid}/feedback` absent faute de stockage et
 * en donnait le plan : « compte, vérification, utile oui/non, unicité sur le
 * couple ». L'endpoint mobile cesse d'être une réserve.
 *
 * Pas d'`uuid` ici, et ce n'est pas un oubli : la règle 4 vise ce qui est
 * EXPOSÉ en URL. Un avis ne l'est jamais — il n'a ni page, ni route, ni
 * partage. Lui donner une clé publique inviterait à en créer une.
 */
final class VerificationFeedback extends Model
{
    protected $table = 'verification_feedback';

    protected $fillable = ['verification_id', 'user_id', 'useful'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['useful' => 'boolean'];
    }

    /**
     * @return BelongsTo<Verification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
