<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

/**
 * Qui peut voir et changer les réglages de la plateforme.
 *
 * ## Pourquoi il n'y a ni `create` ni `delete`
 *
 * Un réglage ne se crée pas depuis l'écran : il existe parce qu'il figure au
 * catalogue, et le catalogue est du code. Permettre d'en créer reviendrait à
 * laisser écrire n'importe quelle clé dans la table — et les réglages sont
 * précisément l'endroit où « n'importe quelle clé » devient une clé d'API ou un
 * drapeau de sécurité.
 *
 * Il ne se supprime pas davantage : on le vide. Une ligne absente et une ligne
 * vide se lisent pareil côté code, mais la seconde garde qui l'a vidée, et
 * quand.
 *
 * ## Pourquoi c'est réservé à l'administration
 *
 * Cet écran porte les clés de paiement, les identifiants SMTP et le mode
 * « bientôt disponible » qui ferme le site. Ce n'est pas une question de
 * confiance envers les mentors : c'est que le périmètre d'un rôle doit
 * correspondre à ce qu'il a besoin de faire.
 */
final class SettingPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('setting.manage');
    }

    public function view(User $utilisateur, Setting $reglage): bool
    {
        return $utilisateur->can('setting.manage');
    }

    public function update(User $utilisateur, ?Setting $reglage = null): bool
    {
        return $utilisateur->can('setting.manage');
    }
}
