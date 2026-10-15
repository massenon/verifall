<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Marketing\Services\GabaritsDEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * « Confirmez votre abonnement à l'infolettre du lundi. »
 *
 * C'est le SEUL courriel qu'une adresse non confirmée reçoit, et il en reçoit
 * un seul par soumission. Le lien qu'il porte est la preuve du consentement :
 * tant qu'il n'est pas cliqué, la plateforme considère que l'adresse a été
 * saisie par quelqu'un d'autre.
 *
 * Il porte AUSSI un lien de désabonnement. Ce n'est pas redondant : quelqu'un
 * qui reçoit cette confirmation sans l'avoir demandée doit pouvoir clore
 * l'affaire d'un clic, sans avoir à confirmer d'abord pour pouvoir sortir
 * ensuite.
 */
final class ConfirmationAbonnement extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $lien,
        public readonly string $lienDeDesabonnement,
        public readonly string $nomDuSite,
        public readonly ?string $prenom = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(GabaritsDEmail::class)->sujet(
                'confirmation_abonnement',
                'Confirmez votre abonnement à l\'infolettre',
            ),
        );
    }

    /**
     * `List-Unsubscribe` : le désabonnement que le client mail affiche LUI-MÊME.
     *
     * Gmail et Outlook proposent un bouton « Se désabonner » à côté de
     * l'expéditeur quand cet en-tête est présent. C'est le chemin le plus
     * court hors de la liste, et donc le meilleur rempart contre le bouton
     * « Signaler comme indésirable », qui lui coûte la réputation du domaine.
     */
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->lienDeDesabonnement.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.infolettre.confirmation',
            with: [
                'lien' => $this->lien,
                'lienDeDesabonnement' => $this->lienDeDesabonnement,
                'nomDuSite' => $this->nomDuSite,
                'prenom' => $this->prenom,
            ],
        );
    }
}
