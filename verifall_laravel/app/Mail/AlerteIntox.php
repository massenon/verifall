<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Core\Marketing\Emails\RenduDeGabarit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\SerializesModels;

/**
 * L'alerte envoyée quand un démenti urgent est publié.
 *
 * ## Pourquoi elle ne passe PAS par `CourrielDeGabarit`
 *
 * Le mailable générique s'adresse à un COMPTE : il n'a ni lien de
 * désabonnement, ni en-tête `List-Unsubscribe`, parce qu'un message
 * transactionnel n'en a pas besoin. Celle-ci part à la liste d'infolettre.
 *
 * Un envoi de masse sans lien de retrait est une infraction, et le seul geste
 * qui resterait à quelqu'un qui n'en veut plus serait « signaler comme
 * indésirable » — la réputation d'envoi du domaine entier le paierait, y
 * compris pour les codes de connexion.
 *
 * Elle emprunte donc la forme de {@see DiffusionInfolettre}, et le CONTENU au
 * gabarit : objet, titre et corps restent modifiables depuis l'administration
 * comme n'importe quel autre courriel.
 */
final class AlerteIntox extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<string, string|int|null>  $variables
     */
    public function __construct(
        public readonly array $variables,
        public readonly string $lienDuFactCheck,
        public readonly string $lienDeDesabonnement,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(RenduDeGabarit::class)->sujet(
                'alerte_intox',
                CatalogueDesCourriels::evenement('alerte_intox')['libelle'],
                $this->variables,
            ),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->lienDeDesabonnement.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $rendu = app(RenduDeGabarit::class);

        return new Content(
            markdown: 'emails.alerte-intox',
            with: [
                'titre' => $rendu->titre(
                    'alerte_intox',
                    CatalogueDesCourriels::evenement('alerte_intox')['libelle'],
                    $this->variables,
                ),
                // Converti en HTML ici, purifié dans la vue : purifier le
                // Markdown brut encoderait le `>` des citations.
                'corps' => Markdown::parse($rendu->corps('alerte_intox', $this->variables) ?? ''),
                'libelleDuBouton' => $rendu->libelleDuBouton('alerte_intox', $this->variables),
            ],
        );
    }
}
