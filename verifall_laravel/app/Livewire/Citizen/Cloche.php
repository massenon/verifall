<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Enums\CategorieNotification;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * La cloche de notifications — enfin adossée à des données.
 *
 * ## Ce qu'elle remplace
 *
 * Le gabarit portait la cloche du prototype : pastille rouge, trois onglets,
 * liste. Mais la liste était remplie par `citizen.js` depuis `VFStore`, un
 * magasin en **localStorage**. Trois conséquences, toutes visibles :
 *
 *  - la pastille rouge était **toujours allumée**, puisque rien ne la
 *    conditionnait à des données réelles ;
 *  - les notifications ne suivaient pas d'un appareil à l'autre — se connecter
 *    depuis un autre téléphone les faisait disparaître ;
 *  - vider le cache du navigateur les effaçait toutes.
 *
 * C'était le comportement normal d'un prototype. Ce n'en est pas un pour un
 * produit dont la notification principale annonce le résultat d'une analyse
 * payée.
 *
 * ## Ce qui reste hors périmètre, et qui est dit à l'écran
 *
 * Ni courriel, ni notification poussée. La table `notification_preferences`
 * existe et n'est consommée par personne ; brancher un canal sans son
 * désabonnement produirait des envois que personne ne peut arrêter.
 */
final class Cloche extends Component
{
    /** '' pour « Tout », sinon la valeur d'une CategorieNotification. */
    public string $onglet = '';

    /**
     * Combien on en montre dans le menu déroulant.
     *
     * Le prototype borne la liste à une hauteur avec défilement ; on borne
     * aussi la requête. Charger tout l'historique pour en afficher dix serait
     * payé à chaque ouverture de page — la cloche est sur les vingt-cinq
     * écrans citoyens.
     */
    private const PLAFOND = 15;

    public function filtrer(string $onglet): void
    {
        $this->onglet = $onglet;
    }

    /**
     * Marque tout comme lu.
     *
     * Sur TOUT, pas seulement sur l'onglet affiché : le bouton dit « Tout
     * marquer lu », et la pastille compte toutes les catégories. Ne traiter
     * que l'onglet courant laisserait la pastille allumée après un clic censé
     * l'éteindre.
     */
    public function toutMarquerLu(): void
    {
        auth()->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        return view('livewire.citizen.cloche', [
            'notifications' => $this->notifications(),
            'nonLues' => $this->nonLues(),
            'categories' => CategorieNotification::cases(),
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function notifications(): Collection
    {
        $compte = auth()->user();

        if ($compte === null) {
            return collect();
        }

        return $compte->notifications()
            ->when($this->onglet !== '', fn ($requete) => $requete
                ->where('data->categorie', $this->onglet))
            ->latest()
            ->limit(self::PLAFOND)
            ->get();
    }

    private function nonLues(): int
    {
        return (int) (auth()->user()?->unreadNotifications()->count() ?? 0);
    }
}
