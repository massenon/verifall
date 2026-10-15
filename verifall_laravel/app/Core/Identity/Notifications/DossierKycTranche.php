<?php

declare(strict_types=1);

namespace App\Core\Identity\Notifications;

use App\Enums\CategorieNotification;
use App\Enums\StatutDossierKyc;
use App\Models\KycSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * « Votre dossier d'identité a été examiné. »
 *
 * ## Le motif du refus voyage AVEC la décision
 *
 * Un refus sans raison fait redéposer exactement la même photo floue, et la
 * personne finit par conclure que la plateforme la rejette. Le motif est donc
 * dans la notification elle-même, pas seulement sur l'écran de profil : c'est
 * la seule chose qui rend un refus actionnable.
 *
 * ## `database` seulement, pas de courriel
 *
 * Le contenu touche à l'identité. Un courriel se transfère, se stocke chez un
 * tiers, s'affiche sur un écran verrouillé ; la cloche exige d'être connecté.
 * C'est le seul endroit du produit où l'on renonce délibérément à la portée du
 * courriel au profit de la discrétion.
 */
final class DossierKycTranche extends NotificationCitoyenne implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly KycSubmission $dossier) {}

    public function texte(): string
    {
        if ($this->dossier->status === StatutDossierKyc::Refuse) {
            return "Dossier d'identité refusé : ".trim((string) $this->dossier->rejection_reason);
        }

        return $this->dossier->status->messageAuCitoyen();
    }

    public function icone(): string
    {
        return $this->dossier->status === StatutDossierKyc::Approuve ? 'verified' : 'badge';
    }

    public function categorie(): CategorieNotification
    {
        return CategorieNotification::Communaute;
    }

    public function lien(): string
    {
        return route('citizen.profile');
    }
}
