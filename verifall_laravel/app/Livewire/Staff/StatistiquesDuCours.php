<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Lms\Services\StatistiquesDeCours;
use App\Models\Course;
use App\Models\Enrollment;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * « Statistiques d'un cours » — ce que le cours produit, et qui décroche.
 *
 * ## Trois chiffres, et le seul sur lequel on peut agir
 *
 * Inscrits et certificats disent l'entrée et la sortie. **La progression
 * moyenne dit ce qui se passe entre les deux**, et c'est la seule des trois sur
 * laquelle un formateur peut faire quelque chose : quatre-vingts inscrits,
 * trois certificats et 22 % de moyenne n'est pas un problème d'audience, c'est
 * un problème au troisième module.
 *
 * ## La liste est triée par les MOINS avancés
 *
 * Cet écran sert à relancer. Trier par date d'inscription mettrait en tête les
 * anciens, c'est-à-dire le plus souvent ceux qui ont fini — et ferait descendre
 * ceux à qui il faut écrire.
 *
 * ## Le mentor ne voit que SES cours
 *
 * Même règle que l'écran « Cours » : la Policy tranche, et un mentor qui ouvre
 * l'adresse d'un cours qui n'est pas le sien obtient un refus. Les chiffres
 * d'inscription d'un collègue ne le regardent pas.
 */
#[Layout('components.layouts.staff', ['title' => "Statistiques du cours · Vérif'All"])]
final class StatistiquesDuCours extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        // `view` et non `viewAny` : c'est CE cours qu'on ouvre, et la Policy
        // sait qu'un mentor n'accède qu'aux siens.
        $this->authorize('view', $this->cours());
    }

    public function render(StatistiquesDeCours $statistiques): View
    {
        $cours = $this->cours();

        return view('livewire.staff.statistiques-du-cours', [
            'cours' => $cours,
            'chiffres' => $statistiques->pour($cours),
            'inscrits' => $statistiques->inscrits($cours),
            'reposJours' => StatistiquesDeCours::REPOS_JOURS,
        ]);
    }

    /**
     * Relance un inscrit qui a décroché.
     *
     * La garde est ici ET dans la vue : la vue masque le bouton pour qui a
     * terminé, mais un appel Livewire se forge sans passer par elle.
     */
    public function relancer(string $uuid, StatistiquesDeCours $statistiques, AuditLogService $audit): void
    {
        $auteur = Auth::guard('staff')->user();

        $this->authorize('update', $this->cours());

        $inscription = Enrollment::query()
            ->with(['user', 'course'])
            ->where('uuid', $uuid)
            ->first();

        // L'inscription doit appartenir à CE cours : un uuid deviné ne doit pas
        // permettre d'écrire aux inscrits d'un autre formateur.
        if ($inscription === null || $inscription->course_id !== $this->cours()->id) {
            $this->toastError('Cette inscription n’existe plus.');

            return;
        }

        try {
            $statistiques->relancer($inscription, $auteur);
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        $audit->record(
            action: 'course.reminder',
            module: 'academie',
            acteur: $auteur,
            cible: $inscription->course,
            apres: ['citoyen' => $inscription->user?->uuid, 'avancement' => (int) $inscription->progress],
            cibleLibelle: (string) $inscription->course?->title,
        );

        /*
         | « X est relancé » impose un accord, donc un genre — qu'on ne connaît
         | pas et qu'on n'a pas à supposer. La tournure impersonnelle
         | l'évite entièrement, et se lit aussi bien.
         */
        $this->toastSuccess(sprintf(
            'Relance envoyée à %s.',
            $inscription->user?->name ?? 'la personne',
        ));
    }

    private function cours(): Course
    {
        return Course::query()->where('slug', $this->slug)->firstOrFail();
    }
}
