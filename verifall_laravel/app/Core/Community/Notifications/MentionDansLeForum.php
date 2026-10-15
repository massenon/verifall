<?php

declare(strict_types=1);

namespace App\Core\Community\Notifications;

use App\Core\Identity\Notifications\NotificationCitoyenne;
use App\Core\Identity\Services\PreferencesService;
use App\Enums\CategorieNotification;
use App\Enums\PreferenceNotification;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * « Quelqu'un vous a cité dans une discussion. »
 *
 * ## Le premier envoi par courriel de la plateforme citoyenne
 *
 * La classe de base ne rendait que `database`, et son commentaire disait
 * pourquoi : les préférences par canal existaient en base sans que rien ne les
 * consomme, et les brancher à moitié produirait des envois que personne ne
 * peut arrêter.
 *
 * Cette notification les consomme. `ActiviteForum` commande le canal courriel,
 * et rien d'autre : quelqu'un qui la désactive continue de voir la mention dans
 * sa cloche, sans recevoir de message. La cloche n'est pas optionnelle parce
 * qu'elle ne sollicite personne — elle attend qu'on vienne.
 *
 * ## L'application mobile
 *
 * Elle lit la même table `notifications` par l'API. Aucune notification poussée
 * n'est envoyée : cela demanderait un service tiers, une clé, et le
 * consentement du système d'exploitation. Le point d'entrée existe ; la poussée
 * reste à brancher, et c'est écrit plutôt que sous-entendu.
 */
final class MentionDansLeForum extends NotificationCitoyenne
{
    public function __construct(
        public readonly string $auteur,
        public readonly string $titreDuSujet,
        public readonly string $slugDuSujet,
        public readonly string $extrait,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        /*
         | Le courriel dépend de la PRÉFÉRENCE, jamais de l'envie du code.
         |
         | `accepte()` est interrogé sur le destinataire : c'est lui qui décide
         | s'il veut être dérangé hors de la plateforme. Sans ce test, la
         | préférence resterait un interrupteur qui n'éteint rien.
         */
        if ($this->acceptePreference($notifiable)) {
            $canaux[] = 'mail';
        }

        return $canaux;
    }

    public function texte(): string
    {
        return $this->auteur.' vous a cité dans « '.$this->titreDuSujet.' »';
    }

    public function icone(): string
    {
        return 'alternate_email';
    }

    public function categorie(): CategorieNotification
    {
        return CategorieNotification::Communaute;
    }

    public function lien(): ?string
    {
        return route('citizen.thread', $this->slugDuSujet);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->auteur.' vous a cité dans une discussion')
            ->greeting('Bonjour,')
            ->line($this->auteur.' vous a cité dans la discussion « '.$this->titreDuSujet.' ».')
            // L'extrait évite d'avoir à ouvrir pour savoir de quoi il retourne.
            // Sur une connexion lente, c'est la différence entre lire et
            // remettre à plus tard.
            ->line('« '.$this->extrait.' »')
            ->action('Voir la discussion', (string) $this->lien())
            ->line('Vous pouvez désactiver ces courriels depuis vos réglages, section notifications.');
    }

    private function acceptePreference(object $notifiable): bool
    {
        /*
         | Silence par défaut si le destinataire n'est pas un compte citoyen.
         | Quelqu'un qui ne sait pas répondre à la question n'a pas consenti :
         | lui écrire reviendrait à traiter l'absence de réponse comme un accord.
         */
        if (! $notifiable instanceof User) {
            return false;
        }

        return app(PreferencesService::class)
            ->estActive($notifiable, PreferenceNotification::ActiviteForum);
    }
}
