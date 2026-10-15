<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tool;
use App\Models\User;

/**
 * Qui tient le répertoire d'outils de vérification.
 *
 * L'annuaire ne calcule rien : sa valeur tient **entièrement à l'exactitude de
 * ses liens**. C'est ce qui justifie qu'il ait ses propres droits plutôt que de
 * vivre sous un `system.*` fourre-tout — une fiche qui pointe vers un service
 * disparu, ou pire, vers un domaine racheté par quelqu'un d'autre, envoie des
 * citoyens y déposer des captures d'écran.
 *
 * La suppression est ouverte, contrairement aux offres du Hub : une fiche
 * d'outil n'a ni candidature ni partage attaché, et un outil mort doit pouvoir
 * quitter l'annuaire. Le drapeau `is_published` reste le geste ordinaire ; la
 * suppression sert au doublon et à l'erreur de saisie.
 */
final class ToolPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('tool.view');
    }

    public function view(User $utilisateur, Tool $outil): bool
    {
        return $utilisateur->can('tool.view');
    }

    public function create(User $utilisateur): bool
    {
        return $utilisateur->can('tool.create');
    }

    public function update(User $utilisateur, Tool $outil): bool
    {
        return $utilisateur->can('tool.update');
    }

    public function delete(User $utilisateur, Tool $outil): bool
    {
        return $utilisateur->can('tool.delete');
    }
}
