<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Marketing\Services\GabaritsDEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Une diffusion écrite à la main depuis l'administration.
 *
 * ## Ce qui la distingue du récapitulatif hebdomadaire
 *
 * Le récapitulatif compose son corps À PARTIR des fact-checks publiés : son
 * contenu est dérivé, donc reproductible. Celle-ci porte un texte que
 * quelqu'un a écrit, une fois. Le corps voyage donc AVEC le message, purifié
 * avant d'être mis en file — jamais reconstruit à l'ouverture du job, sinon
 * modifier la campagne pendant l'envoi changerait le message en cours de route.
 *
 * ## Le lien de désabonnement n'est pas optionnel
 *
 * Il est passé au constructeur plutôt que calculé dans le gabarit : un envoi de
 * masse sans lien de retrait est une infraction, et le rendre obligatoire à la
 * construction est le seul endroit où l'oubli devient impossible.
 */
final class DiffusionInfolettre extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $objet,
        public readonly string $corps,
        public readonly string $lienDeDesabonnement,
        public readonly string $nomDuSite,
        public readonly ?string $prenom = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // L'objet saisi fait foi ; le gabarit ne sert que de repli, pour le
            // cas où la campagne aurait été enregistrée sans objet.
            subject: app(GabaritsDEmail::class)->sujet('diffusion_infolettre', $this->objet),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            /*
             | RFC 8058. Sans cet en-tête, le seul geste qui reste à quelqu'un
             | qui ne veut plus rien recevoir est « signaler comme indésirable »
             | — et c'est la réputation d'envoi du domaine entier qui le paie.
             */
            'List-Unsubscribe' => '<'.$this->lienDeDesabonnement.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.infolettre.diffusion');
    }
}
