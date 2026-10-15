<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Journal d'audit — lisible par qui en a le droit, modifiable par PERSONNE.
 *
 * C'est la raison d'être de cette Policy. Les autorisations de lecture
 * pourraient tenir dans un middleware `permission:` ; l'interdiction d'écrire,
 * non — elle doit valoir pour tout le monde, administrateur compris, et
 * survivre aux sprints qui ajouteront des écrans.
 *
 * Un journal que l'on peut réécrire ne prouve rien. Aucune méthode ne renvoie
 * donc `true` pour une modification, et aucun `Gate::before` n'existe dans
 * l'application qui pourrait passer par-dessus : l'administrateur détient ses
 * 106 permissions explicitement, il n'a pas de passe-partout.
 */
final class AuditLogPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('security.logs');
    }

    public function view(User $utilisateur, AuditLog $trace): bool
    {
        return $this->viewAny($utilisateur);
    }

    /** Exporter, c'est extraire : même droit que lire, et journalisé à part. */
    public function export(User $utilisateur): bool
    {
        return $this->viewAny($utilisateur);
    }

    public function create(User $utilisateur): bool
    {
        // Les traces naissent d'actions réelles, jamais d'une saisie.
        return false;
    }

    public function update(User $utilisateur, AuditLog $trace): bool
    {
        return false;
    }

    public function delete(User $utilisateur, AuditLog $trace): bool
    {
        return false;
    }
}
