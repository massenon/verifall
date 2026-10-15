<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Lms\Services\EspaceDuMentor;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Mes Étudiants » — la cohorte d'un mentor, tous ses cours confondus.
 *
 * ## Le premier écran du module mentor
 *
 * Jusqu'ici, un mentor empruntait les écrans d'administration : il voyait TOUS
 * les cours, y compris ceux de ses collègues, et n'avait aucun moyen de savoir
 * qui suivait les siens. Les permissions `student.view` et `student.progress`
 * lui étaient pourtant attribuées depuis l'origine — **et employées nulle
 * part**. Cet écran est le premier des deux à les consommer.
 *
 * ## Une ligne par INSCRIPTION
 *
 * Quelqu'un inscrit à trois cours du même mentor apparaît trois fois. La
 * question posée ici est « où en est cette personne dans ce cours », pas « qui
 * suis-je en train de former » : fusionner les lignes obligerait à choisir un
 * avancement parmi trois, et le choix serait arbitraire.
 *
 * ## La différence avec « Progression »
 *
 * Celui-ci est le TROMBINOSCOPE : qui est inscrit, où il en est, quand il est
 * passé. L'autre est l'outil de rattrapage : résultats de quiz, état de
 * décrochage, export de cohorte. Les deux partagent leur source
 * ({@see EspaceDuMentor::cohorte()}) et pas leur usage — les fondre donnerait un
 * écran qui répond mal aux deux questions.
 */
#[Layout('components.layouts.staff', ['title' => "Mes étudiants · Vérif'All"])]
final class MesEtudiants extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cours', except: '')]
    public string $filtreCours = '';

    public function mount(): void
    {
        abort_unless($this->acteur()->can('student.view'), 403);
    }

    public function render(EspaceDuMentor $espace): View
    {
        $mentor = $this->acteur();
        $toutVoir = $this->voitTout();

        $inscrits = $espace->cohorte(
            $mentor,
            $toutVoir,
            $this->recherche,
            $this->filtreCours,
            parPage: 20,
        );

        return view('livewire.staff.mes-etudiants', [
            'inscrits' => $inscrits,
            'mesCours' => $espace->coursDuMentor($mentor, $toutVoir),
            'toutVoir' => $toutVoir,
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'filtreCours'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCours');
        $this->resetPage();
    }

    /**
     * Un administrateur voit la plateforme entière ; un mentor, ses cours.
     *
     * Le discriminant est le DROIT, pas la présence de données : un mentor sans
     * cours doit voir une liste vide, pas la liste de tout le monde. Se fier à
     * « il n'a aucun cours, donc montrons tout » serait la fuite la plus
     * silencieuse possible.
     */
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
