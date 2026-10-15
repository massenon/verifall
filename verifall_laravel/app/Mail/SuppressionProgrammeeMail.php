<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Identity\Services\SuppressionCompteService;
use App\Core\Marketing\Services\GabaritsDEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * « Votre compte sera supprimé dans 30 jours ».
 *
 * Ce courriel est la seule voie de retour. Le compte étant fermé, il n'est plus
 * possible de se connecter pour annuler : le lien signé qu'il contient est donc
 * le mécanisme de rétractation lui-même, pas une commodité.
 *
 * Il est aussi ce qui alerte la vraie personne si quelqu'un d'autre a demandé
 * la suppression depuis son écran resté ouvert.
 */
final class SuppressionProgrammeeMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $utilisateur) {}

    public function envelope(): Envelope
    {
        /*
         | Le « 30 » était écrit EN DUR ici, alors que le corps du courriel, dix
         | lignes plus bas, calcule déjà l'échéance depuis
         | `SuppressionCompteService::JOURS_RETRACTATION`. Le jour où la
         | constante changerait, le sujet annoncerait un délai et le corps un
         | autre — dans le même message, sur la décision la plus irréversible
         | que la plateforme propose.
         |
         | Même défaut que celui corrigé dans les CGU la semaine dernière : un
         | nombre recopié à la main ment au premier changement.
         */
        $defaut = 'Votre compte Vérif\'All sera supprimé dans '
            .SuppressionCompteService::JOURS_RETRACTATION.' jours';

        return new Envelope(
            subject: app(GabaritsDEmail::class)->sujet('suppression_programmee', $defaut),
        );
    }

    public function content(): Content
    {
        $echeance = ($this->utilisateur->deletion_requested_at ?? now())
            ->addDays(SuppressionCompteService::JOURS_RETRACTATION);

        return new Content(
            markdown: 'emails.suppression-programmee',
            with: [
                'prenom' => $this->utilisateur->first_name,
                'echeance' => $echeance,
                'jours' => SuppressionCompteService::JOURS_RETRACTATION,
                'lienAnnulation' => URL::temporarySignedRoute(
                    'account.deletion.cancel',
                    $echeance,
                    ['uuid' => $this->utilisateur->uuid],
                ),
            ],
        );
    }
}
