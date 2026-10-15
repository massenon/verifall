<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ModerationKeyword;
use App\Models\User;

/**
 * Qui tient la liste des mots surveillés.
 *
 * Ouverte au modérateur : c'est son métier, et c'est lui qui voit passer les
 * formes que prend l'injure ou la rumeur. Lui interdire d'ajouter un mot le
 * jour où une rumeur monte reviendrait à ne pas la surveiller.
 *
 * Pas de `delete`. Un mot se DÉSACTIVE : la ligne demeure avec sa raison et son
 * compteur, ce qui permet de répondre plus tard à « pourquoi ce mot
 * était-il bloqué ? » Une liste sans mémoire se reconstruit à l'aveugle.
 */
final class ModerationKeywordPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('content_report.view');
    }

    public function view(User $utilisateur, ModerationKeyword $mot): bool
    {
        return $utilisateur->can('content_report.view');
    }

    public function update(User $utilisateur): bool
    {
        return $utilisateur->can('content_report.handle');
    }
}
