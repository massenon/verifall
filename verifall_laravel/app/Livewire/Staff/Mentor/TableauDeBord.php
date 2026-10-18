<?php

declare(strict_types=1);

namespace App\Livewire\Staff\Mentor;

use App\Core\Application\Contracts\DepotTableauDeBordMentor;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Le tableau de bord du mentor (IMPLEMENTATION_PLAN, P4-2).
 *
 * Le sien, pas une variante de celui de la plateforme. Ce qu'on retient,
 * c'est « Ma journée » : une frise de 8 h à 22 h où se placent ses lives, les
 * échéances de correction et les vérifications qui lui sont assignées. Le reste
 * répond à « qui a besoin de moi ? ». Toutes les lectures sont bornées à ses
 * cours (`DepotTableauDeBordMentor`).
 */
final class TableauDeBord extends Component
{
    /** La frise couvre la journée de travail. */
    public const DEBUT = 8;

    public const FIN = 22;

    #[Layout('components.layouts.staff')]
    #[Title('Tableau de bord')]
    public function render(DepotTableauDeBordMentor $depot): View
    {
        /** @var User $mentor */
        $mentor = auth('staff')->user();

        $prenom = $mentor->first_name ?: explode(' ', (string) $mentor->name)[0];

        return view('livewire.staff.mentor.tableau-de-bord', [
            'prenom' => $prenom,
            'donnees' => $depot->pour($mentor),
            'maintenant' => now()->hour + now()->minute / 60,
        ]);
    }
}
