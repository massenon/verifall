<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsletterSubscriber;
use App\Models\User;

/**
 * Qui voit la liste d'abonnés, et qui peut lancer un envoi.
 *
 * La liste est un fichier d'adresses e-mail : c'est la donnée personnelle la
 * plus facilement exfiltrable de la plateforme, et celle dont la fuite se
 * remarque le moins. Deux verbes seulement, ceux que la matrice déclare —
 * `newsletter.view` et `newsletter.send`.
 *
 * Ce qui est ABSENT est aussi un choix :
 *
 *   · pas de `create` — un abonné naît d'un consentement, jamais d'une saisie
 *     dans le back-office. Ajouter une adresse à la main, c'est abonner
 *     quelqu'un sans son accord, ce que le double opt-in existe pour empêcher ;
 *   · pas de `update` — on ne corrige pas l'adresse de quelqu'un d'autre ;
 *   · `delete` est accordé au droit d'envoi, pas au droit de lecture : effacer
 *     une ligne détruit la PREUVE du consentement, geste plus lourd que de
 *     désabonner. Le retrait normal reste le désabonnement, qui n'efface rien.
 */
final class NewsletterSubscriberPolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('newsletter.view');
    }

    public function view(User $utilisateur, NewsletterSubscriber $abonne): bool
    {
        return $utilisateur->can('newsletter.view');
    }

    /**
     * Déclencher une édition à la main.
     *
     * Le geste engage la réputation d'envoi du domaine auprès de toute la
     * liste : il ne se confie pas au même droit que la consultation.
     */
    public function send(User $utilisateur): bool
    {
        return $utilisateur->can('newsletter.send');
    }

    public function delete(User $utilisateur, NewsletterSubscriber $abonne): bool
    {
        return $utilisateur->can('newsletter.send');
    }
}
