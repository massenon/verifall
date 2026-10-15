<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TrustedSource;
use App\Models\User;

/**
 * Qui tient le référentiel des sources.
 *
 * Ce référentiel n'est pas un annuaire de plus : le MOTEUR d'analyse le
 * consulte, et une fiche y désarme une heuristique de suspicion. Ajouter un
 * domaine, c'est décider qu'il ne sera plus soupçonné d'usurper une
 * institution — sur toutes les vérifications à venir.
 *
 * C'est pourquoi `trusted_source.*` existe séparément dans la matrice, et n'est
 * pas rangé sous `knowledge_base.*` : les deux droits ne portent pas le même
 * risque. Se tromper sur un fait vérifié se corrige par une correction datée ;
 * se tromper ici modifie silencieusement le jugement de la machine.
 */
final class TrustedSourcePolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('trusted_source.view');
    }

    public function view(User $utilisateur, TrustedSource $source): bool
    {
        return $utilisateur->can('trusted_source.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('trusted_source.create');
    }

    public function update(User $utilisateur, TrustedSource $source): bool
    {
        return $utilisateur->can('trusted_source.update');
    }

    public function delete(User $utilisateur, TrustedSource $source): bool
    {
        return $utilisateur->can('trusted_source.delete');
    }
}
