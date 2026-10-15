<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Models\LiveClass;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Live Classes » — transposition de compte-user/live-classes.html.
 *
 * Trois onglets, dans l'ordre du prototype : à venir, en direct, terminées.
 *
 * Le statut n'est pas une colonne : il se déduit de l'heure. Une séance stockée
 * comme « planifiée » resterait planifiée le lendemain si personne ne repassait
 * derrière — c'est le genre d'écran qui ment tout seul, un dimanche.
 */
#[Layout('components.layouts.citizen', ['title' => "Live Classes · Vérif'All"])]
final class LiveClasses extends Component
{
    public string $onglet = 'lc-upcoming';

    public function render(): View
    {
        $seances = LiveClass::with(['course', 'host'])
            ->where('is_published', true)
            ->orderBy('starts_at')
            ->get();

        return view('livewire.citizen.live-classes', [
            'aVenir' => $seances->filter(fn (LiveClass $s): bool => $s->etat() === 'upcoming')->values(),
            'enDirect' => $seances->filter(fn (LiveClass $s): bool => $s->etat() === 'live')->values(),
            'terminees' => $seances->filter(fn (LiveClass $s): bool => $s->etat() === 'past')
                ->sortByDesc('starts_at')->values(),
        ]);
    }
}
