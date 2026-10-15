<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HubJob;
use App\Models\User;

/**
 * Qui peut alimenter le Hub des opportunités.
 *
 * La matrice ne déclare qu'un verbe pour ce module — `hub_job.manage` — et
 * c'est un choix, pas un raccourci : créer, modifier et vérifier une offre
 * relèvent du même métier et du même niveau de responsabilité. Les découper
 * donnerait l'illusion d'une granularité que personne n'emploierait.
 *
 * La SUPPRESSION est la seule qu'on distingue, et vers le bas : une offre
 * publiée a pu être partagée, candidatée, mise en favori. On la retire du Hub
 * plutôt que de l'effacer.
 */
final class HubJobPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('hub_job.view');
    }

    public function view(User $utilisateur, HubJob $offre): bool
    {
        return $utilisateur->can('hub_job.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('hub_job.manage');
    }

    public function update(User $utilisateur, HubJob $offre): bool
    {
        return $utilisateur->can('hub_job.manage');
    }

    /**
     * Une offre PUBLIÉE ne se supprime pas.
     *
     * Elle a pu être partagée, mise en favori, ou faire l'objet d'une
     * candidature en cours. Effacer sa ligne laisse ces liens dans le vide.
     * On la dépublie — le geste est réversible, celui-ci ne l'est pas.
     */
    public function delete(User $utilisateur, HubJob $offre): bool
    {
        return $utilisateur->can('hub_job.manage') && ! $offre->is_published;
    }
}
