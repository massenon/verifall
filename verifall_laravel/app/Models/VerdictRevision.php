<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\FactChecking\Services\RaisonDeCorrectionManquante;
use App\Enums\VerdictFactCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une correction de verdict, publiée.
 *
 * Chaque ligne dit : ce qui était affirmé, ce que la plateforme en disait, ce
 * qu'elle en dit maintenant, et POURQUOI elle a changé d'avis. L'ancien
 * verdict ne disparaît pas — c'est toute la différence entre corriger et
 * réécrire.
 *
 * Le modèle porte lui-même l'invariant de la raison. La contrainte `CHECK` de
 * la migration ne s'applique qu'aux moteurs qui savent l'ajouter après coup :
 * ici, elle vaut partout, y compris sur le SQLite de la suite de tests.
 */
final class VerdictRevision extends Model
{
    protected $fillable = [
        'uuid',
        'revisable_type',
        'revisable_id',
        'affirmation',
        'ancien_verdict',
        'nouveau_verdict',
        'raison',
        'auteur_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ancien_verdict' => VerdictFactCheck::class,
            'nouveau_verdict' => VerdictFactCheck::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Le garde-fou de dernier recours.
     *
     * Il ne remplace pas la validation de l'écran — un message d'erreur sous
     * un champ vaut mieux qu'une exception — mais il garantit qu'aucun chemin
     * de code, présent ou futur, ne pourra écrire une correction muette.
     */
    protected static function booted(): void
    {
        self::saving(function (self $revision): void {
            if (trim((string) $revision->raison) === '') {
                throw RaisonDeCorrectionManquante::pourUneCorrection();
            }
        });
    }

    /**
     * Le contenu corrigé — `HubArticle` ou `VerifiedFact`.
     *
     * Les colonnes sont nommées explicitement, et ce n'est pas cosmétique :
     * `morphTo('revisable')` les déduirait bien, mais rangerait le résultat du
     * chargement anticipé sous la clé « revisable » — donc jamais sous
     * « contenu », et la relation reviendrait vide après un `with('contenu')`.
     *
     * @return MorphTo<Model, $this>
     */
    public function contenu(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'revisable_type', 'revisable_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /**
     * Le lien vers la vérification corrigée, s'il en existe un de public.
     *
     * `VerifiedFact` n'a pas de page publique : son verdict est servi dans un
     * rapport, il ne s'affiche pas à une URL. Le journal le dit alors sans
     * lien plutôt que d'en fabriquer un qui mènerait à une 404.
     */
    public function lienPublic(): ?string
    {
        $contenu = $this->contenu;

        if ($contenu instanceof HubArticle && $contenu->is_published) {
            return route('public.fact', $contenu);
        }

        return null;
    }
}
