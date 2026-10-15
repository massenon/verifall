<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Application\Reglages\Reglages;
use App\Livewire\Staff\PortailsOfficiels as EcranAdministration;
use App\Models\HubPortal;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Portails officiels » — l'annuaire des sites de l'administration.
 *
 * ## La rubrique est FERMÉE tant qu'un réglage ne l'ouvre pas
 *
 * Demande explicite du porteur du projet : le CRUD d'administration d'abord,
 * l'exposition citoyenne quand le contenu sera prêt. L'entrée figure dans le
 * menu du compte, rendue désactivée, et la case
 * `hub_portals_actif` l'ouvre — voir {@see EcranAdministration}.
 *
 * **La route rend 404 quand la rubrique est fermée**, et pas seulement le lien.
 * Un lien désactivé dont l'adresse répondrait quand même ne serait désactivé
 * que pour la forme : il suffirait de la connaître, ou de la deviner.
 *
 * ## Pourquoi 404 et non 403
 *
 * Une rubrique pas encore ouverte n'existe pas encore pour le public. 403
 * dirait « elle existe et vous n'y avez pas droit », ce qui est faux et
 * invite à insister.
 */
#[Layout('components.layouts.citizen', ['title' => "Portails officiels · Vérif'All"])]
final class PortailsOfficiels extends Component
{
    public function mount(Reglages $reglages): void
    {
        abort_unless($reglages->actif(EcranAdministration::CLE_ACTIVATION), 404);
    }

    public function render(): View
    {
        /** @var Collection<string, Collection<int, HubPortal>> $parRubrique */
        $parRubrique = HubPortal::query()
            ->where('is_published', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            // Groupés par rubrique : un annuaire de trente entrées à plat ne
            // se parcourt pas, et les rubriques du prototype existent déjà en
            // base (Institutions, Démarches, Télécoms, Santé, Médias).
            ->groupBy('category');

        return view('livewire.citizen.portails-officiels', [
            'parRubrique' => $parRubrique,
        ]);
    }
}
