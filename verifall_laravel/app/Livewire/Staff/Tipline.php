<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Reglages\CatalogueDesReglages;
use App\Core\FactChecking\Services\TiplineWhatsApp;
use App\Models\Setting;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Tipline WhatsApp » — l'état du dispositif, et ce qu'il reste à faire.
 *
 * ## Un écran d'ÉTAT, pas un écran de réglages
 *
 * Les trois valeurs se saisissent dans « Paramètres », onglet API externes,
 * avec les autres secrets : elles y sont chiffrées, jamais renvoyées au
 * navigateur, et conservées quand le champ est laissé vide. Les dupliquer ici
 * aurait signifié deux endroits où écrire un jeton, donc un endroit où l'on
 * oublie de le changer.
 *
 * Cet écran répond à une autre question : **où en est-on ?** Il dit ce qui
 * manque, nommément, et pourquoi la tipline ne reçoit rien tant que ce n'est
 * pas fourni.
 *
 * ## Pourquoi un écran alors que le module est éteint
 *
 * Parce qu'un module absent et un module en attente de configuration se
 * ressemblent exactement. Sans cet écran, quelqu'un qui a entendu parler de la
 * tipline la chercherait dans le menu, ne la trouverait pas, et conclurait
 * qu'elle n'a pas été faite. Ce qui manque n'est pas du code : c'est un
 * dossier chez Meta.
 */
#[Layout('components.layouts.staff', ['title' => "Tipline WhatsApp · Vérif'All"])]
final class Tipline extends Component
{
    public function render(TiplineWhatsApp $tipline): View
    {
        $this->authorize('viewAny', Setting::class);

        return view('livewire.staff.tipline', [
            'active' => $tipline->estActive(),
            'manquants' => $tipline->manquants(),
            'numero' => $tipline->identifiantDuNumero(),
            'ongletApi' => CatalogueDesReglages::GROUPE_API,
        ]);
    }
}
