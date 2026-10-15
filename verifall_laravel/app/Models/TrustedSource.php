<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Une source référencée par la rédaction.
 *
 * Le moteur d'analyse l'interroge PAR DOMAINE. Tout ce qui suit existe pour que
 * cette comparaison ne rate jamais pour une raison de forme.
 */
final class TrustedSource extends Model
{
    protected $fillable = [
        'uuid', 'source_category_id', 'name', 'domain', 'url',
        'credibility', 'is_active', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credibility' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<SourceCategory, $this>
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(SourceCategory::class, 'source_category_id');
    }

    /**
     * @param  Builder<self>  $requete
     * @return Builder<self>
     */
    public function scopeActives(Builder $requete): Builder
    {
        return $requete->where('is_active', true);
    }

    /**
     * Ramène une saisie humaine au domaine nu que le moteur comparera.
     *
     * Une personne qui référence une source colle l'adresse de la barre du
     * navigateur : `https://www.lemonde.fr/politique/`. Le moteur, lui, tient
     * `parse_url($url, PHP_URL_HOST)`, soit `www.lemonde.fr`, et le plus
     * souvent `lemonde.fr`. Sans normalisation, la fiche existe et ne sert
     * jamais — le pire des deux mondes, puisque rien ne le signale.
     *
     * `www.` tombe : il n'a jamais distingué deux sites différents, et le
     * conserver ferait dépendre la réponse du moteur de la façon dont le lien
     * a été copié.
     */
    public static function normaliserLeDomaine(string $saisie): string
    {
        $valeur = mb_strtolower(trim($saisie));

        if ($valeur === '') {
            return '';
        }

        // Un schéma, s'il est là, part avec le reste de l'URL.
        if (str_contains($valeur, '://')) {
            $valeur = (string) (parse_url($valeur, PHP_URL_HOST) ?? '');
        }

        // Sinon, la saisie peut rester de la forme « lemonde.fr/politique ».
        $valeur = Str::before($valeur, '/');
        $valeur = Str::before($valeur, ':');
        $valeur = trim($valeur, '.');

        return str_starts_with($valeur, 'www.') ? substr($valeur, 4) : $valeur;
    }
}
