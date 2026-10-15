<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Lms\Services\RemiseDExercice;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * « Mes Exercices » — transposition de compte-user/exercices.html.
 *
 * Les travaux pratiques, corrigés par un mentor. Trois états seulement, et ils
 * se lisent d'un coup d'œil : cours non suivi ⇒ cadenas, copie rendue ⇒ en
 * attente de correction, copie validée ⇒ ✓.
 */
#[Layout('components.layouts.citizen', ['title' => "Mes Exercices · Vérif'All"])]
final class Exercices extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;

    /** L'exercice ouvert dans la modale. */
    public ?string $exerciceOuvert = null;

    public string $reponse = '';

    public $piece = null;

    public function render(): View
    {
        $utilisateur = $this->utilisateur();

        $exercices = Assignment::with('course')
            ->whereHas('course', fn ($q) => $q->where('is_published', true))
            ->orderBy('course_id')
            ->orderBy('position')
            ->get();

        $remises = AssignmentSubmission::where('user_id', $utilisateur->id)
            ->get()
            ->keyBy('assignment_id');

        $inscrits = Enrollment::where('user_id', $utilisateur->id)
            ->pluck('course_id')
            ->all();

        return view('livewire.citizen.exercices', [
            'exercices' => $exercices,
            'remises' => $remises,
            'coursSuivis' => $inscrits,
            'exercice' => $this->exerciceOuvert !== null
                ? $exercices->firstWhere('uuid', $this->exerciceOuvert)
                : null,
            'prochaineEcheance' => $this->prochaineEcheance($exercices, $remises, $inscrits),
        ]);
    }

    public function ouvrir(string $uuid): void
    {
        $this->exerciceOuvert = $uuid;
        $this->reset(['reponse', 'piece']);
        $this->dispatch('ouvrir-modale', id: 'modal-assignment');
    }

    public function soumettre(RemiseDExercice $remises): void
    {
        $exercice = Assignment::where('uuid', $this->exerciceOuvert)->first();

        if ($exercice === null) {
            $this->toastError("Cet exercice n'existe plus.");

            return;
        }

        $this->validate([
            // Les types acceptés sont ceux annoncés dans la modale — annoncer
            // autre chose que ce qu'on accepte fait perdre un envoi.
            'piece' => 'nullable|file|mimes:pdf,zip,jpg,jpeg,png,webp|max:10240',
        ], attributes: ['piece' => 'pièce jointe']);

        try {
            $remises->remettre(
                $this->utilisateur(),
                $exercice,
                $this->reponse,
                $this->piece?->getRealPath() !== null ? $this->piece : null,
            );
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        $this->reset(['reponse', 'piece', 'exerciceOuvert']);
        $this->dispatch('fermer-modale', id: 'modal-assignment');
        $this->toastSuccess('Exercice soumis — un mentor le corrigera sous peu.');
    }

    /**
     * L'échéance annoncée en tête de page.
     *
     * Le prototype affiche une date en dur. Ici c'est le premier exercice non
     * rendu d'un cours suivi ; s'il n'y en a pas, la phrase disparaît plutôt
     * que d'annoncer une échéance imaginaire.
     *
     * @param  Collection<int, Assignment>  $exercices
     * @param  Collection<int, AssignmentSubmission>  $remises
     * @param  list<int>  $inscrits
     */
    private function prochaineEcheance($exercices, $remises, array $inscrits): ?Assignment
    {
        return $exercices->first(
            fn (Assignment $e): bool => in_array($e->course_id, $inscrits, true)
                && ! $remises->has($e->id)
        );
    }

    private function utilisateur(): User
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();

        return $utilisateur;
    }
}
