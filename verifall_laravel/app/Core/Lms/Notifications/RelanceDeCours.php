<?php

declare(strict_types=1);

namespace App\Core\Lms\Notifications;

use App\Core\Identity\Notifications\NotificationCitoyenne;
use App\Core\Marketing\Services\GabaritsDEmail;
use App\Enums\CategorieNotification;
use App\Models\Enrollment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\InteractsWithQueue;

/**
 * « Votre cours vous attend. »
 *
 * ## Le message dit OÙ la personne s'est arrêtée
 *
 * « Reprenez votre formation » n'apprend rien et se supprime. « Vous êtes à
 * 40 % de *Sourcer et citer* » rappelle un effort déjà fourni — et c'est cet
 * effort, pas la relance, qui fait revenir. Le chiffre est donc dans le corps
 * du message, pas seulement sur l'écran.
 *
 * ## Deux canaux, parce que la personne n'est pas là
 *
 * Quelqu'un qui a décroché ne revient pas voir sa cloche : c'est précisément le
 * problème. Le courriel est le seul canal qui l'atteint. La notification en
 * base l'attend pour le jour où elle revient.
 *
 * ## Ce que cette notification NE FAIT PAS
 *
 * Elle ne se répète pas d'elle-même. Aucun planificateur ne l'envoie : un
 * membre de l'équipe décide, depuis l'écran de statistiques du cours, et
 * l'application tient le délai minimum entre deux envois. Une relance
 * automatique deviendrait vite le message que tout le monde filtre.
 */
final class RelanceDeCours extends NotificationCitoyenne implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly Enrollment $inscription,
        private readonly string $auteur,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function texte(): string
    {
        return sprintf(
            'Vous êtes à %d %% de « %s ». Il reste peu pour aller au bout.',
            (int) $this->inscription->progress,
            $this->titreDuCours(),
        );
    }

    public function icone(): string
    {
        return 'school';
    }

    public function categorie(): CategorieNotification
    {
        return CategorieNotification::Apprentissage;
    }

    public function lien(): string
    {
        return route('citizen.catalogue');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sujet = app(GabaritsDEmail::class)->sujet(
            'relance_de_cours',
            'Votre cours vous attend',
        );

        $avancement = (int) $this->inscription->progress;

        return (new MailMessage)
            ->subject($sujet)
            ->greeting('Bonjour,')
            ->line(sprintf(
                'Vous avez commencé « %s » et vous en êtes à %d %%.',
                $this->titreDuCours(),
                $avancement,
            ))
            /*
             | Ni reproche, ni urgence fabriquée. Une relance qui culpabilise
             | obtient un désabonnement, pas une reprise — et sur une plateforme
             | dont l'objet est d'apprendre à résister à la manipulation, jouer
             | sur l'urgence serait particulièrement mal venu.
             */
            ->line('Reprenez quand cela vous arrange : votre progression est conservée.')
            ->action('Reprendre le cours', route('citizen.catalogue'))
            ->line(sprintf('Message envoyé par %s, de l’équipe Vérif’All.', $this->auteur));
    }

    private function titreDuCours(): string
    {
        return (string) ($this->inscription->course?->title ?? 'votre cours');
    }
}
