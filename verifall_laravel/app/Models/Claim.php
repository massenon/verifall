<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActeDeLangage;
use App\Enums\PorteeTemporelle;
use App\Enums\VerdictFactCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une affirmation, question, opinion ou prédiction extraite d'un contenu soumis.
 *
 * Rattachée à la VÉRIFICATION, pas au rapport (contrat V-8) : elle naît à
 * l'extraction, avant que le rapport n'existe. Le rapport reste la synthèse
 * de l'ensemble des claims d'une vérification.
 */
final class Claim extends Model
{
    protected $fillable = [
        'verification_id', 'position', 'texte', 'texte_original',
        'acte_de_langage', 'portee_temporelle', 'verdict', 'confiance', 'reponse',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'acte_de_langage' => ActeDeLangage::class,
            // Nullable : non renseignée tant que la phase 3 n'a pas classé
            // ce claim (reprise d'un rapport antérieur, par exemple).
            'portee_temporelle' => PorteeTemporelle::class,
            'verdict' => VerdictFactCheck::class,
            'confiance' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Verification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    /**
     * @return HasMany<EngineRun, $this>
     */
    public function engineRuns(): HasMany
    {
        return $this->hasMany(EngineRun::class);
    }

    /**
     * @return HasMany<ReportEvidence, $this>
     */
    public function evidences(): HasMany
    {
        return $this->hasMany(ReportEvidence::class);
    }

    /** Ce claim peut-il porter un verdict ? Seule une affirmation le peut. */
    public function estVerifiable(): bool
    {
        return $this->acte_de_langage->peutPorterUnVerdict();
    }
}
