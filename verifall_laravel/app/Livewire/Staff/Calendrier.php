<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Le calendrier de la console : lives, sessions d'examen, maintenances.
 *
 * Lecture seule. Modifier un événement se fait depuis son écran ; un
 * glisser-déposer qui déplacerait un live après l'envoi des rappels serait un
 * geste sans trace des gens prévenus.
 */
#[Layout('components.layouts.staff', ['title' => "Calendrier · Vérif'All"])]
final class Calendrier extends Component
{
    public function render(): View
    {
        return view('livewire.staff.calendrier');
    }
}
