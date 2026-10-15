<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LiveClass;
use App\Models\User;

/**
 * Qui programme, anime et diffuse les séances en direct.
 *
 * La matrice déclare quatre verbes — `live_class.view|create|update|delete` —
 * et le Mentor les détient tous : c'est lui qui anime. Le découpage sert donc
 * moins à distinguer des rôles qu'à borner ce qu'un jeton d'API peut faire.
 *
 * ## La suppression, et la raison de la refuser
 *
 * Une séance PASSÉE ne se supprime pas. Trois liens pendent derrière elle : les
 * inscriptions (`live_class_registrations`), qui sont la trace de qui comptait
 * venir ; les fichiers de calendrier déjà déposés dans les agendas ; et, depuis
 * le lot 1, la rediffusion — dont l'adresse peut avoir été partagée dans un
 * cours. Effacer la ligne laisse tout cela dans le vide.
 *
 * Une séance À VENIR se supprime, elle : rien ne s'y rattache encore qu'une
 * intention, et une séance annulée doit disparaître de la liste plutôt que d'y
 * rester en promettant un rendez-vous qui n'aura pas lieu.
 */
final class LiveClassPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('live_class.view');
    }

    public function view(User $utilisateur, LiveClass $seance): bool
    {
        return $utilisateur->can('live_class.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('live_class.create');
    }

    public function update(User $utilisateur, LiveClass $seance): bool
    {
        return $utilisateur->can('live_class.update');
    }

    public function delete(User $utilisateur, LiveClass $seance): bool
    {
        return $utilisateur->can('live_class.delete') && ! $seance->estPassee();
    }
}
