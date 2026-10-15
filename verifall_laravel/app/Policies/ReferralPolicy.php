<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Referral;
use App\Models\User;

/**
 * Qui peut consulter le suivi des parrainages.
 *
 * ## Pourquoi il n'y a QUE de la lecture ici
 *
 * Un parrainage n'est pas une fiche : c'est la trace de deux faits enchaînés —
 * quelqu'un s'est inscrit avec un code, puis a fait sa première vérification
 * éligible. Exposer `create` reviendrait à permettre d'écrire cette histoire
 * après coup, et donc de distribuer des unités sans contrepartie.
 *
 * Exposer `update` ou `delete` serait pire encore : un parrainage récompensé
 * a un pendant comptable dans le journal des unités. Le modifier d'un côté
 * sans l'autre rendrait les deux illisibles.
 *
 * Ces méthodes ne sont donc pas « à écrire plus tard ». Leur absence est la
 * décision.
 */
final class ReferralPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('referral.view');
    }

    public function view(User $utilisateur, Referral $parrainage): bool
    {
        return $utilisateur->can('referral.view');
    }
}
