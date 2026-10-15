<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VerifiedFact;

/**
 * Base des faits tranchés par l'équipe — qui peut la lire, l'alimenter, l'engager.
 *
 * Une Policy et pas un simple middleware `permission:` : l'écran expose cinq
 * gestes de portée très différente sur le même objet, et « publier » n'est pas
 * « enregistrer ». Un fait publié court-circuite douze moteurs et sort en
 * rapport à 100 % de confiance — c'est la plateforme qui parle. Le modérateur
 * rédige donc, mais n'engage pas ; seul l'administrateur publie et supprime.
 *
 * Écrite ici plutôt que répétée dans le composant : l'absence de bouton n'est
 * pas une protection, et chaque action doit revérifier côté serveur.
 */
final class VerifiedFactPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('knowledge_base.view');
    }

    public function view(User $utilisateur, VerifiedFact $fait): bool
    {
        return $this->viewAny($utilisateur);
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('knowledge_base.create');
    }

    /**
     * Le fait est facultatif, comme pour `publish` : l'écran doit pouvoir
     * demander le droit avant d'avoir chargé la cible.
     */
    public function update(User $utilisateur, ?VerifiedFact $fait = null): bool
    {
        return $utilisateur->can('knowledge_base.update');
    }

    /**
     * Publier ou dépublier — le geste le plus engageant de l'écran.
     *
     * Distinct de `update` à dessein : corriger une coquille dans une analyse
     * et décider qu'un verdict fera désormais autorité ne demandent pas la
     * même confiance.
     *
     * Le fait est facultatif : l'écran de saisie doit pouvoir demander « cette
     * personne pourrait-elle publier ? » avant même que l'enregistrement
     * existe, sinon le bouton « Publier » apparaîtrait à qui n'en a pas le
     * droit — et l'échec ne surviendrait qu'après la rédaction complète.
     */
    public function publish(User $utilisateur, ?VerifiedFact $fait = null): bool
    {
        return $utilisateur->can('knowledge_base.publish');
    }

    public function delete(User $utilisateur, VerifiedFact $fait): bool
    {
        return $utilisateur->can('knowledge_base.delete');
    }
}
