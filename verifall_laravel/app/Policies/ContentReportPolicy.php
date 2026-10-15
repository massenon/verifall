<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContentReport;
use App\Models\User;

/**
 * Qui traite les signalements de contenu.
 *
 * Ouverte au MODÉRATEUR, contrairement à la boîte de contact : c'est
 * exactement son métier, et c'est par ces signalements qu'un contenu
 * problématique lui parvient.
 *
 * Pas de `delete`, et c'est la décision qui compte ici. Un signalement se
 * traite — retenu ou rejeté — et la trace demeure. L'effacer ferait perdre le
 * fait qu'une décision a été prise, et rendrait impossible de reconnaître un
 * signaleur qui abuse du bouton.
 */
final class ContentReportPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('content_report.view');
    }

    public function view(User $utilisateur, ContentReport $signalement): bool
    {
        return $utilisateur->can('content_report.view');
    }

    public function update(User $utilisateur): bool
    {
        return $utilisateur->can('content_report.handle');
    }
}
