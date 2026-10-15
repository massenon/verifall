<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SecurityLog;
use App\Models\User;

/**
 * Journal de sécurité — mêmes règles que l'audit, permission distincte.
 *
 * La séparation n'est pas cosmétique : surveiller les tentatives d'intrusion
 * et consulter l'historique des actions d'équipe sont deux besoins différents,
 * qui peuvent être confiés à deux personnes différentes.
 */
final class SecurityLogPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('security.alerts');
    }

    public function view(User $utilisateur, SecurityLog $evenement): bool
    {
        return $this->viewAny($utilisateur);
    }

    public function create(User $utilisateur): bool
    {
        return false;
    }

    public function update(User $utilisateur, SecurityLog $evenement): bool
    {
        return false;
    }

    public function delete(User $utilisateur, SecurityLog $evenement): bool
    {
        return false;
    }
}
