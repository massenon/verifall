<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Notifications;

use App\Core\Identity\Notifications\NotificationCitoyenne;
use App\Enums\CategorieNotification;
use App\Models\CounterExpertise;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * « Votre contestation a été examinée. »
 *
 * ## Le message dit l'ISSUE, pas « c'est traité »
 *
 * Quelqu'un qui conteste un verdict attend une seule réponse : **avions-nous
 * tort ?** Une notification qui se contente de « votre demande a été traitée »
 * oblige à rouvrir la page pour l'apprendre, et donne l'impression d'une
 * formalité expédiée.
 *
 * `StatutContreExpertise::messageAuDemandeur()` porte donc les quatre formulations,
 * y compris **« Vous aviez raison : le rapport a été corrigé »** — celle qu'une
 * plateforme est le plus tentée d'atténuer, et celle qui fait sa crédibilité.
 *
 * ## Un seul canal
 *
 * `database`. La personne a demandé, elle revient voir — contrairement à une
 * assignation, qui confie un travail à quelqu'un qui n'attend rien.
 */
final class ContreExpertiseTranchee extends NotificationCitoyenne implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly CounterExpertise $demande) {}

    public function texte(): string
    {
        return $this->demande->status->messageAuDemandeur();
    }

    public function icone(): string
    {
        return 'gavel';
    }

    public function categorie(): CategorieNotification
    {
        return CategorieNotification::Verification;
    }

    public function lien(): string
    {
        return route('citizen.report', ['verification' => $this->demande->verification->uuid]);
    }
}
