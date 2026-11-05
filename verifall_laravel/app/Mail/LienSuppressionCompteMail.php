<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Marketing\Services\GabaritsDEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * « Confirmez votre demande de suppression de compte ».
 *
 * Voie de secours pour un utilisateur qui n'a plus accès à son compte.
 * Il ne peut pas se connecter pour déclencher la suppression in-app, donc
 * nous lui envoyons un lien signé valable 24 h. Cliquer sur ce lien prouve
 * qu'il contrôle l'adresse enregistrée : c'est la seule preuve disponible
 * dans ce cas de figure, et elle suffit au regard du RGPD.
 *
 * Le flux est identique à la voie in-app (Option A) : un clic ferme le
 * compte immédiatement et lance le délai de rétractation de 30 jours.
 */
final class LienSuppressionCompteMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $utilisateur) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(GabaritsDEmail::class)->sujet(
                'lien_suppression_compte',
                'Confirmez la suppression de votre compte Vérif\'All',
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.lien-suppression-compte',
            with: [
                'prenom' => $this->utilisateur->first_name,
                'lienConfirmation' => URL::temporarySignedRoute(
                    'account.deletion.confirm',
                    now()->addHours(24),
                    ['uuid' => $this->utilisateur->uuid],
                ),
            ],
        );
    }
}
