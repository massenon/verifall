<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Notifications;

use App\Core\Identity\Notifications\NotificationCitoyenne;
use App\Enums\CategorieNotification;
use App\Models\CounterExpertise;
use App\Models\Verification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * « Un lecteur conteste ce verdict. »
 *
 * ## Un seul canal, et c'est un choix
 *
 * `database` uniquement — pas de courriel, contrairement à l'assignation
 * nominative. Une contestation s'adresse à une ÉQUIPE : écrire à tout le monde
 * ferait que personne ne se sent concerné, et userait l'attention qu'on garde
 * pour le message qui, lui, désigne quelqu'un.
 *
 * Le chemin reste celui-ci : la contestation arrive dans la cloche de chaque
 * arbitre, la file se voit sur le tableau de bord, et l'un d'eux se voit
 * confier le dossier — ce courriel-là part.
 */
final class ContreExpertiseDemandee extends NotificationCitoyenne implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly Verification $verification,
        private readonly CounterExpertise $demande,
    ) {}

    public function texte(): string
    {
        return sprintf(
            'Verdict contesté sur %s : « %s »',
            $this->verification->reference(),
            mb_strimwidth(trim($this->demande->reason), 0, 80, '…'),
        );
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
        return route('staff.dashboard');
    }
}
