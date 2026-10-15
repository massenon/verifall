<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerdictFactCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un fait tranché par l'ÉQUIPE, pas par les moteurs.
 *
 * Consulté avant tout appel payant : quand une rédaction a déjà statué sur une
 * rumeur, aucun modèle n'a besoin d'en rediscuter — et la réponse rendue est
 * meilleure que ce que douze moteurs produiraient.
 */
final class VerifiedFact extends Model
{
    protected $fillable = [
        'uuid', 'title', 'summary', 'verdict', 'keywords',
        'content_fingerprint', 'sources',
        'is_published', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verdict' => VerdictFactCheck::class,
            'sources' => 'array',
            'is_published' => 'boolean',
            'hits' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Sources retenues, normalisées.
     *
     * Le champ est libre en base : une liste vide, un tableau mal formé ou une
     * URL sans nom ne doivent pas faire tomber un rapport. Ce qui n'a pas
     * d'URL n'est pas une source et disparaît.
     *
     * @return list<array{url: string, name: string}>
     */
    public function sourcesRetenues(): array
    {
        $brutes = $this->sources;

        if (! is_array($brutes)) {
            return [];
        }

        $propres = [];

        foreach ($brutes as $source) {
            $url = is_array($source) ? trim((string) ($source['url'] ?? '')) : '';

            if ($url === '') {
                continue;
            }

            $propres[] = [
                'url' => $url,
                'name' => trim((string) ($source['name'] ?? '')) ?: $this->nomDepuisLUrl($url),
            ];
        }

        return $propres;
    }

    /** Combien de sources appuient ce fait — colonne du tableau admin. */
    public function nombreDeSources(): int
    {
        return count($this->sourcesRetenues());
    }

    /**
     * À défaut de nom saisi, le domaine.
     *
     * « arcep.bj » dit au lecteur d'où vient l'information ; une URL complète
     * de 200 caractères ne lui dit rien.
     */
    private function nomDepuisLUrl(string $url): string
    {
        $hote = parse_url($url, PHP_URL_HOST);

        return is_string($hote) ? preg_replace('/^www\./', '', $hote) : 'Source';
    }

    /**
     * Compte une réutilisation.
     *
     * Mesure l'économie réelle, et indique quelles rumeurs circulent le plus —
     * information que le back-office exploitera pour prioriser la veille.
     */
    public function compterUneReutilisation(): void
    {
        $this->increment('hits');
    }
}
