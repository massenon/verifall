<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/**
 * Qui lit la boîte de contact.
 *
 * Elle contient des adresses e-mail et des messages libres qui peuvent nommer
 * des personnes — parfois pour signaler que l'une d'elles diffuse une intox.
 * C'est pourquoi elle n'est PAS ouverte au modérateur : son périmètre est le
 * forum, et l'élargir « puisqu'il modère déjà » reviendrait à confondre deux
 * responsabilités distinctes.
 *
 * Ce qui est absent est un choix : ni `create` — un message naît d'un
 * formulaire public, jamais d'une saisie interne — ni `delete`. Un message se
 * traite, il ne s'efface pas ; le marquer « indésirable » le sort de la file
 * sans détruire la trace.
 */
final class ContactMessagePolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('contact_message.view');
    }

    public function view(User $utilisateur, ContactMessage $message): bool
    {
        return $utilisateur->can('contact_message.view');
    }

    /** Changer le statut, et consigner une note de traitement. */
    public function update(User $utilisateur): bool
    {
        return $utilisateur->can('contact_message.handle');
    }
}
