<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Marketing\Services\GabaritsDEmail;
use App\Models\HubArticle;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Collection;

/**
 * Le récapitulatif du lundi — « les pires intox détectées cette semaine ».
 *
 * ## Pourquoi il n'est PAS `ShouldQueue`
 *
 * Un Mailable mis en file rend la main tout de suite : la commande d'envoi ne
 * saurait alors que « c'est parti en file », jamais si c'est arrivé. Les trois
 * compteurs de la campagne — destinataires, envoyés, échoués — deviendraient
 * deux compteurs et un vœu.
 *
 * Il est donc envoyé DEPUIS la commande, dans un `try`, destinataire par
 * destinataire, avec une trace écrite pour chacun. La commande est planifiée
 * `runInBackground()` pour ne pas retenir le planificateur pendant ce temps.
 * Voir l'arbitrage 157.
 */
final class RecapitulatifHebdomadaire extends Mailable
{
    /**
     * @param  Collection<int, HubArticle>  $articles
     */
    public function __construct(
        public readonly string $objet,
        public readonly Collection $articles,
        public readonly string $lienDeDesabonnement,
        public readonly string $nomDuSite,
        public readonly string $periode,
        public readonly ?string $prenom = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(GabaritsDEmail::class)->sujet('recapitulatif_hebdomadaire', $this->objet),
        );
    }

    /** Voir `ConfirmationAbonnement::headers()` — même raison, même enjeu. */
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
            markdown: 'emails.infolettre.recapitulatif',
            with: [
                'articles' => $this->articles,
                'lienDeDesabonnement' => $this->lienDeDesabonnement,
                'nomDuSite' => $this->nomDuSite,
                'periode' => $this->periode,
                'prenom' => $this->prenom,
            ],
        );
    }
}
