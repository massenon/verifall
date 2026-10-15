<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Marketing\Services\GabaritsDEmail;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Prévient l'équipe qu'un message vient d'arriver.
 *
 * CE COURRIEL N'EST QU'UN RAPPEL. Le message est déjà en base quand il part :
 * si le serveur SMTP refuse, rien n'est perdu, et l'écran d'administration le
 * montre quand même. C'est l'inverse qui serait dangereux — un formulaire qui
 * ne fait qu'envoyer un courriel perd le message dès la première panne, sans
 * que personne ne l'apprenne.
 *
 * `replyTo` porte l'adresse de l'expéditeur : répondre depuis son client mail
 * suffit, sans passer par le back-office.
 */
final class MessageDeContactRecu extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContactMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(GabaritsDEmail::class)->sujet(
                'message_contact',
                'Nouveau message de contact — '.$this->message->name,
            ),
            replyTo: [$this->message->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.message-contact');
    }
}
