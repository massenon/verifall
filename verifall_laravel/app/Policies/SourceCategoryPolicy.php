<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SourceCategory;
use App\Models\User;

/**
 * Qui tient les familles de sources.
 *
 * ## Pas de permission neuve, et c'est une règle du chantier
 *
 * `CLAUDE.md` est explicite : « un nouvel écran écrit sa Policy et sa garde de
 * route — il n'invente pas de permission ». La matrice ne déclare pas de
 * `source_category.*`, et il n'y a pas lieu d'en créer une.
 *
 * Le fond le confirme : une famille n'existe QUE pour classer des sources. Qui
 * peut référencer une source peut nommer la famille où la ranger ; l'inverse —
 * quelqu'un qui créerait des familles sans pouvoir y mettre quoi que ce soit —
 * ne correspond à aucun rôle réel de la rédaction.
 *
 * ## Sauf pour la suppression, qui est plus lourde qu'elle n'en a l'air
 *
 * Supprimer une famille laisse ses sources orphelines — sans les effacer, la
 * clé étrangère est en `nullOnDelete`. Le geste reste donc sous
 * `trusted_source.delete`, le droit le plus étroit de la famille.
 */
final class SourceCategoryPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('trusted_source.view');
    }

    public function view(User $utilisateur, SourceCategory $categorie): bool
    {
        return $utilisateur->can('trusted_source.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('trusted_source.create');
    }

    public function update(User $utilisateur, SourceCategory $categorie): bool
    {
        return $utilisateur->can('trusted_source.update');
    }

    public function delete(User $utilisateur, SourceCategory $categorie): bool
    {
        return $utilisateur->can('trusted_source.delete');
    }
}
