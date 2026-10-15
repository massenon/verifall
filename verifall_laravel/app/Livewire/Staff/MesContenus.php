<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Lms\Services\EspaceDuMentor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Mes contenus » — leçons, quiz et classes en direct, tous cours confondus.
 *
 * ## Ce qu'il apporte face au constructeur de cours
 *
 * Le constructeur répond à « comment ce cours est-il structuré ». Celui-ci
 * répond à une autre question, qu'aucun écran ne portait : **« qu'est-ce que
 * j'ai produit, et qu'est-ce qui traîne en brouillon »** — à travers tous les
 * cours, sans avoir à en ouvrir six.
 *
 * D'où la liste PLATE, et le filtre par format plutôt que par cours : trier par
 * cours reproduirait le constructeur, en moins bien.
 *
 * ## Une leçon n'a pas d'état de publication propre
 *
 * Elle suit celui de son cours. La colonne « Statut » ne concerne donc vraiment
 * que les classes en direct, et l'écran l'annonce plutôt que d'afficher
 * « Publié » partout comme si c'était une information.
 */
#[Layout('components.layouts.staff', ['title' => "Mes contenus · Vérif'All"])]
final class MesContenus extends Component
{
    /** '' | 'lecon' | 'quiz' | 'direct' */
    #[Url(as: 'format', except: '')]
    public string $filtreFormat = '';

    public function mount(): void
    {
        abort_unless($this->acteur()->can('lesson.view'), 403);
    }

    public function render(EspaceDuMentor $espace): View
    {
        $contenus = $espace->contenus($this->acteur(), $this->voitTout(), $this->filtreFormat);

        return view('livewire.staff.mes-contenus', [
            'contenus' => $contenus,
            'minutes' => array_sum(array_column($contenus, 'duree')),
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
