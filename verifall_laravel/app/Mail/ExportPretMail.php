<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Identity\Services\ExportDonneesService;
use App\Core\Marketing\Services\GabaritsDEmail;
use App\Models\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * « Votre export est prêt ».
 *
 * L'archive n'est PAS jointe au courriel. Elle contient l'intégralité des
 * données personnelles d'une personne, et un courriel se relaie, se transfère
 * et se conserve indéfiniment sur des serveurs que nous ne maîtrisons pas. Le
 * message ne porte qu'un lien signé, valable 24 heures.
 */
final class ExportPretMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DataExport $export) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(GabaritsDEmail::class)->sujet('export_pret', 'Votre export de données est prêt'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.export-pret',
            with: [
                'lien' => URL::temporarySignedRoute(
                    'citizen.export.download',
                    $this->export->expires_at ?? now()->addHours(ExportDonneesService::HEURES_VALIDITE),
                    ['export' => $this->export->uuid],
                ),
                'heures' => ExportDonneesService::HEURES_VALIDITE,
                'echec' => $this->export->status === DataExport::ECHEC,
            ],
        );
    }
}
