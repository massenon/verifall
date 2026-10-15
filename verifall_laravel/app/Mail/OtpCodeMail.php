<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Identity\Services\OtpService;
use App\Core\Marketing\Services\GabaritsDEmail;
use App\Enums\OtpPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Envoi d'un code à usage unique.
 *
 * Le code transite par courriel : c'est inévitable, mais il ne doit apparaître
 * NULLE PART ailleurs — ni en journal, ni en base, ni dans une exception. Le
 * marquer readonly et le garder hors de toute méthode de journalisation est le
 * minimum ; `dontFlash` dans bootstrap/app.php couvre le reste.
 */
final class OtpCodeMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly OtpPurpose $purpose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(GabaritsDEmail::class)->sujet('otp', $this->purpose->objetCourriel()),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.otp-code',
            with: [
                'code' => $this->code,
                'minutes' => OtpService::TTL_MINUTES,
                'purpose' => $this->purpose,
            ],
        );
    }
}
