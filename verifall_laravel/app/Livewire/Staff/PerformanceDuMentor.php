<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Lms\Services\EspaceDuMentor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Performance » — ce que les cours d'un mentor produisent.
 *
 * ## Quatre colonnes, et une seule sur laquelle on peut agir
 *
 * Apprenants, complétion, score moyen, note. Le nombre d'inscrits dépend du
 * catalogue et de la promotion ; la note dépend de qui prend la peine d'en
 * laisser une. **La complétion, elle, dit quelque chose du cours** — et c'est la
 * seule que le mentor peut faire bouger en changeant son contenu.
 *
 * L'écran l'affiche donc en clair plutôt qu'en tendance : une flèche verte ou
 * rouge suggère un mouvement qu'aucune donnée ne mesure ici. Le prototype
 * portait une colonne « Tendance » ; elle n'est pas transposée, faute de série
 * temporelle à laquelle la comparer. Une flèche inventée serait pire qu'une
 * colonne absente.
 *
 * ## Zéro inscrit donne 0 %, pas une division par zéro
 *
 * Un cours qui vient d'être publié n'a pas « échoué » : il n'a pas commencé.
 * L'écran le dit avec des mots plutôt qu'avec un pourcentage trompeur.
 */
#[Layout('components.layouts.staff', ['title' => "Performance · Vérif'All"])]
final class PerformanceDuMentor extends Component
{
    public function mount(): void
    {
        abort_unless($this->acteur()->can('student.progress'), 403);
    }

    public function render(EspaceDuMentor $espace): View
    {
        $lignes = $espace->performance($this->acteur(), $this->voitTout());

        return view('livewire.staff.performance-du-mentor', [
            'lignes' => $lignes,
            'totalApprenants' => array_sum(array_column($lignes, 'apprenants')),
            /*
             | La complétion d'ensemble est une MOYENNE DES MOYENNES, et l'écran
             | le dit. La calculer sur le total des inscrits donnerait un chiffre
             | plus juste mathématiquement, mais dominé par le cours le plus
             | fréquenté — un mentor avec un cours à 500 inscrits et cinq cours à
             | 10 ne verrait plus que le premier.
             */
            'completionMoyenne' => $lignes !== []
                ? (int) round(array_sum(array_column($lignes, 'completion')) / count($lignes))
                : 0,
        ]);
    }

    private function voitTout(): bool
    {
        return $this->acteur()->can('user.view');
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }
}
