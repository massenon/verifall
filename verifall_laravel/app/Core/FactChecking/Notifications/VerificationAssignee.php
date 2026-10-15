<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Notifications;

use App\Core\Identity\Notifications\NotificationCitoyenne;
use App\Core\Marketing\Services\GabaritsDEmail;
use App\Enums\CategorieNotification;
use App\Models\Verification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\InteractsWithQueue;

/**
 * « Une vérification vous a été confiée. »
 *
 * ## Deux canaux, et les deux sont nécessaires
 *
 * `NotificationCitoyenne` ne diffuse qu'en `database` : c'est le bon réglage
 * pour une notification qu'on découvre en revenant sur le site. Ici, non — une
 * assignation est un TRAVAIL CONFIÉ, et la personne n'a aucune raison d'ouvrir
 * le back-office pour l'apprendre. Sans courriel, un dossier peut dormir des
 * jours pendant que la cloche l'annonce à un onglet fermé.
 *
 * D'où `['database', 'mail']` : la cloche pour le suivi, le courriel pour la
 * prise de connaissance.
 *
 * ## `ShouldQueue`, et ce que cela implique
 *
 * L'envoi passe par la file. **Sans `queue:work` — ou, en hébergement
 * mutualisé, sans l'entrée cron qui le remplace — le courriel ne partira
 * jamais**, et rien ne le signalera : c'est l'encadré de `CLAUDE.md` sur le
 * sujet, et le motif d'existence du journal `mail_logs`.
 *
 * La carte « Santé du système » du tableau de bord affiche justement l'âge du
 * plus ancien job en attente : c'est là que se voit une assignation dont le
 * courriel n'est jamais parti.
 */
final class VerificationAssignee extends NotificationCitoyenne implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly Verification $verification,
        private readonly string $assignePar,
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
        return sprintf('%s vous confie une vérification : %s', $this->assignePar, $this->apercu());
    }

    public function icone(): string
    {
        return 'assignment_ind';
    }

    public function categorie(): CategorieNotification
    {
        return CategorieNotification::Verification;
    }

    public function lien(): string
    {
        // Le tableau de bord du personnel, pas l'écran citoyen : le
        // destinataire est un membre de l'équipe, et le lien doit le déposer
        // là où il peut agir.
        return route('staff.dashboard');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sujet = app(GabaritsDEmail::class)->sujet(
            'verification_assignee',
            'Une vérification vous a été confiée',
        );

        return (new MailMessage)
            ->subject($sujet)
            ->greeting('Bonjour,')
            ->line(sprintf('%s vous a confié une vérification à traiter.', $this->assignePar))
            /*
             | L'affirmation EN CLAIR dans le courriel, et pas seulement un
             | lien. Une notification qui oblige à se connecter pour savoir de
             | quoi il s'agit se traite plus tard — c'est-à-dire trop tard,
             | quand il s'agit de désinformation en circulation.
             */
            ->line('« '.$this->apercu(140).' »')
            ->action('Ouvrir le back-office', route('staff.dashboard'))
            ->line("Si vous n'êtes pas en mesure de la traiter, signalez-le à la personne qui vous l'a confiée : la vérification reste sinon en attente.");
    }

    /** L'affirmation, ramenée à ce qui tient dans une ligne. */
    private function apercu(int $longueur = 90): string
    {
        $texte = trim($this->verification->claim);

        if ($texte === '') {
            return $this->verification->reference();
        }

        return mb_strimwidth($texte, 0, $longueur, '…');
    }
}
