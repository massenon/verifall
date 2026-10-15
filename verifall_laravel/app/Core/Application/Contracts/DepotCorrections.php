<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Infrastructure\Persistence\DepotCorrectionsEloquent;
use App\Models\VerdictRevision;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal des corrections de verdict.
 *
 * Table en APPEND SEULEMENT : on y ajoute, on n'y modifie ni n'y supprime
 * jamais. Un journal de corrections qu'on peut corriger ne prouve plus rien —
 * c'est la raison pour laquelle ce contrat n'expose ni `mettreAJour` ni
 * `supprimer`, et l'omission est délibérée.
 *
 * ## Pourquoi l'implémentation par défaut est déclarée ICI
 *
 * `app/Providers/*.php` est verrouillé pendant le chantier parallèle : quatre
 * agents qui y écrivent en même temps produisent quatre versions incompatibles.
 * Sans câblage, ce contrat n'est pas résoluble et TOUTES les pages publiques de
 * vérification tombent — la garantie de transparence ferait tomber le site
 * qu'elle sert.
 *
 * `#[Bind]` est le mécanisme prévu par le conteneur pour cela : il pose une
 * implémentation par défaut, que n'importe quel `bind()` explicite écrase.
 * L'intégration peut donc ajouter la ligne habituelle dans `AppServiceProvider`
 * pour l'uniformité des six autres dépôts — elle prendra le pas, sans que rien
 * d'autre ne bouge.
 */
#[Bind(DepotCorrectionsEloquent::class)]
interface DepotCorrections
{
    /**
     * @param  array<string, mixed>  $attributs
     */
    public function enregistrer(array $attributs): VerdictRevision;

    /**
     * Le journal public, du plus récent au plus ancien.
     *
     * @return LengthAwarePaginator<int, VerdictRevision>
     */
    public function journal(int $parPage = 15): LengthAwarePaginator;

    /**
     * L'historique d'UN contenu, du plus récent au plus ancien.
     *
     * @return Collection<int, VerdictRevision>
     */
    public function pour(Model $contenu): Collection;

    /** Combien de fois ce contenu a été corrigé. */
    public function nombrePour(Model $contenu): int;

    /**
     * Vide le cache de lecture d'un contenu.
     *
     * L'historique est relu sur CHAQUE affichage de la vérification
     * correspondante ; il change quelques fois par an. Servir une version
     * périmée après une correction reviendrait à cacher la correction pendant
     * le quart d'heure qui suit — précisément le contraire du but.
     */
    public function oublierLeCache(Model $contenu): void;
}
