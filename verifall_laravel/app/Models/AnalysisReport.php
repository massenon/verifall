<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\FactChecking\ValueObjects\IndiceHaine;
use App\Enums\CompositionDuRapport;
use App\Enums\VerdictFactCheck;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le rapport rendu au terme d'une analyse.
 *
 * Depuis le contrat V-8 (phase 2) : `verdict` est NULLABLE et `NULL` ne
 * signifie JAMAIS « mixte » — c'est `composition_status`, un axe distinct,
 * qui porte cette information. Lire l'un sans l'autre donne un sens FAUX :
 * voir {@see estMixte()} avant de déréférencer `verdict` dans une vue.
 */
final class AnalysisReport extends Model
{
    protected $fillable = [
        'uuid', 'verification_id', 'verdict', 'composition_status', 'confidence',
        'summary', 'caveat', 'scores', 'timeline', 'published_at',
        'hate_speech_score', 'hate_speech_tags', 'hate_speech_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Nullable : le cast enum natif de Laravel rend `null` sans
            // erreur quand la colonne l'est — aucun traitement spécial requis.
            'verdict' => VerdictFactCheck::class,
            'composition_status' => CompositionDuRapport::class,
            'confidence' => 'integer',
            'scores' => 'array',
            'timeline' => 'array',
            'hate_speech_score' => 'integer',
            'hate_speech_tags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function porteUneReserve(): bool
    {
        return $this->caveat !== null && $this->caveat !== '';
    }

    /**
     * Le rapport porte-t-il plusieurs verdicts tranchés et distincts ?
     *
     * C'EST LE SEUL ENDROIT QUI DOIT ÊTRE TESTÉ avant de déréférencer
     * `verdict` dans une vue : sur un rapport mixte, `verdict` vaut `NULL` et
     * `$rapport->verdict->badge()` plante. C'est exactement le défaut trouvé
     * dans quatre vues lors du traçage du contrat V-8.
     */
    public function estMixte(): bool
    {
        return $this->composition_status === CompositionDuRapport::Mixte;
    }

    /**
     * Les claims de la vérification qui porte ce rapport, dans l'ordre.
     *
     * Pas une relation Eloquent directe : un claim est rattaché à la
     * VÉRIFICATION, pas au rapport (contrat V-8) — ce raccourci évite à
     * chaque vue de remonter par `$rapport->verification->claims`.
     *
     * @return Collection<int, Claim>
     */
    public function claims(): Collection
    {
        return $this->verification->claims;
    }

    /**
     * Hate Speech Index — bloc « 🛡️ Hate Speech Indicator » du rapport.
     */
    public function indiceHaine(): IndiceHaine
    {
        return new IndiceHaine(
            $this->hate_speech_score,
            $this->hate_speech_tags ?? [],
            $this->hate_speech_note,
        );
    }
}
