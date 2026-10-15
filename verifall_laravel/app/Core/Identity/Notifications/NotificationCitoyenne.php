<?php

declare(strict_types=1);

namespace App\Core\Identity\Notifications;

use App\Enums\CategorieNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Le socle commun des notifications affichées dans la cloche.
 *
 * ## Pourquoi une classe de base plutôt que des notifications indépendantes
 *
 * Parce que la cloche du prototype porte trois onglets — *Tout*,
 * *Vérifications*, *Apprentissage* — et que ce filtre ne marche que si CHAQUE
 * notification déclare sa catégorie. Une notification qui l'oublierait
 * disparaîtrait des deux onglets thématiques sans que rien ne le signale :
 * elle serait dans « Tout », et nulle part ailleurs.
 *
 * La classe de base impose donc les quatre champs que l'affichage exige, et
 * rend l'oubli impossible plutôt qu'improbable.
 *
 * ## Ce qui n'est PAS ici
 *
 * Ni courriel, ni SMS, ni notification poussée. `via()` ne rend que
 * `database`, et c'est délibéré : les canaux supplémentaires demandent des
 * préférences par canal, une gestion du désabonnement et un budget d'envoi.
 * Les préférences existent déjà en base (`notification_preferences`) mais rien
 * ne les consomme — les brancher à moitié produirait des envois que personne
 * ne peut arrêter.
 */
abstract class NotificationCitoyenne extends Notification
{
    use Queueable;

    /** Le texte montré dans la liste. Une phrase, pas un paragraphe. */
    abstract public function texte(): string;

    /** L'icône Material Symbols, telle que le prototype les emploie. */
    abstract public function icone(): string;

    /** L'onglet sous lequel elle se range. */
    abstract public function categorie(): CategorieNotification;

    /**
     * Où l'on va en cliquant. `null` quand il n'y a rien à ouvrir.
     *
     * Un lien mort dans une notification est pire qu'aucun lien : il donne le
     * sentiment d'un défaut alors qu'il n'y avait rien à montrer.
     */
    public function lien(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'texte' => $this->texte(),
            'icone' => $this->icone(),
            'categorie' => $this->categorie()->value,
            'lien' => $this->lien(),
        ];
    }
}
