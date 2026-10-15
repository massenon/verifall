<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Core\Marketing\Seo\Referencement;
use App\Models\Course;
use App\Models\Review;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Fiche publique d'un cours — transposition de tutoriels-details.html.
 *
 * Publique et sans compte : on ne cache pas ce qu'on propose d'apprendre. Seule
 * l'inscription ouvre les leçons — la fiche, elle, doit pouvoir se partager, et
 * c'est ce lien qu'un mentor colle dans un message.
 */
#[Layout('components.layouts.public')]
final class TutorielPublic extends Component
{
    public Course $cours;

    public function mount(Course $tutoriel): void
    {
        abort_unless($tutoriel->is_published, 404);

        $this->cours = $tutoriel->load(['instructor', 'lessons', 'quizzes', 'assignments']);
    }

    public function render(): View
    {
        return view('livewire.public.tutoriel', [
            'avis' => $this->avis(),
            'repartition' => $this->repartitionDesNotes(),
            'autres' => $this->autresTutoriels(),
        ])->layoutData([
            'seo' => app(Referencement::class)->pourTutoriel($this->cours),
        ]);
    }

    /**
     * @return Collection<int, Review>
     */
    private function avis(): Collection
    {
        return $this->cours->reviews()
            ->with('user')
            ->where('is_visible', true)
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * Combien d'avis par note, de 5 à 1.
     *
     * Le prototype dessine cinq barres à des largeurs fixes (68 %, 22 %, 7 %…).
     * Ici elles sont calculées : sans avis, elles sont toutes à zéro, et la
     * section le dit plutôt que de peindre une satisfaction inexistante.
     *
     * @return array<int, array{nombre: int, part: int}>
     */
    private function repartitionDesNotes(): array
    {
        $notes = $this->cours->reviews()->where('is_visible', true)->pluck('rating');
        $total = max(1, $notes->count());

        $repartition = [];

        for ($note = 5; $note >= 1; $note--) {
            $nombre = $notes->filter(fn (int $n): bool => $n === $note)->count();
            $repartition[$note] = [
                'nombre' => $nombre,
                'part' => (int) round($nombre * 100 / $total),
            ];
        }

        return $repartition;
    }

    /**
     * @return Collection<int, Course>
     */
    private function autresTutoriels(): Collection
    {
        return Course::where('is_published', true)
            ->whereKeyNot($this->cours->id)
            ->withCount('lessons')
            ->orderBy('id')
            ->limit(4)
            ->get();
    }
}
