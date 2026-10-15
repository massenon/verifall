<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Mentors » — l'équipe pédagogique, et ce qu'elle porte.
 *
 * ## Ce que cet écran ajoute à « Utilisateurs »
 *
 * `Staff\Utilisateurs` (lot 3) sait déjà créer un compte et lui donner le rôle
 * Mentor. Un second écran ne se justifierait pas s'il ne montrait que les mêmes
 * lignes filtrées.
 *
 * Il montre autre chose : **ce que chaque mentor porte**. Combien de cours,
 * combien publiés, combien de copies lui sont assignées, et depuis quand la
 * plus ancienne attend. Ce sont les seules questions qu'on se pose sur un
 * mentor — « à qui confier cette correction ? », « qui est débordé ? », « qui
 * n'a rien produit depuis six mois ? » — et aucune ne se répond depuis un
 * annuaire de comptes.
 *
 * ## Le retrait du rôle est un geste à conséquences, et l'écran les montre
 *
 * Retirer le rôle Mentor à quelqu'un qui porte huit cours et douze copies en
 * attente ne casse rien en base — mais ces copies n'ont plus de correcteur, et
 * personne ne le saura. La confirmation dit le nombre exact.
 *
 * ## Aucune création ici
 *
 * Créer un compte reste sur `Staff\Utilisateurs`, qui porte les trois
 * garde-fous du lot 3 (dernier administrateur, auto-rétrogradation, adresse
 * vérifiée). Les dupliquer ici les ferait diverger au premier correctif.
 */
#[Layout('components.layouts.staff', ['title' => "Mentors · Vérif'All"])]
final class Mentors extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'actifs' | 'sans-cours' | 'charges' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $aRetrograder = null;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreEtat');
        $this->resetPage();
    }

    public function mount(): void
    {
        // `mentor.view`, la permission qui désigne CET écran — voir la route.
        abort_unless(Auth::guard('staff')->user()?->can('mentor.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.mentors', [
            'mentors' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'peutRetrograder' => $this->peutRetrograder(),
            'mentorVise' => $this->aRetrograder !== null ? $this->mentor($this->aRetrograder) : null,
            'copiesEnAttente' => $this->aRetrograder !== null
                ? $this->copiesAssignees($this->mentor($this->aRetrograder))
                : 0,
        ]);
    }

    // ————————————————————————————————————————————————— retrait du rôle

    public function demanderLaRetrogradation(string $uuid): void
    {
        abort_unless($this->peutRetrograder(), 403);

        $this->aRetrograder = $uuid;
    }

    public function annulerLaRetrogradation(): void
    {
        $this->aRetrograder = null;
    }

    public function retrograder(AuditLogService $audit): void
    {
        if ($this->aRetrograder === null) {
            return;
        }

        abort_unless($this->peutRetrograder(), 403);

        $mentor = $this->mentor($this->aRetrograder);

        /*
         | On ne se retire pas son propre rôle par cet écran. Le garde-fou est le
         | même qu'au lot 3 pour l'auto-rétrogradation d'un administrateur : se
         | verrouiller dehors demande une console pour revenir.
         */
        if ($mentor->id === Auth::guard('staff')->id()) {
            $this->aRetrograder = null;
            $this->toastError('Vous ne pouvez pas retirer votre propre rôle depuis cet écran.');

            return;
        }

        $coursPortes = Course::query()->where('instructor_id', $mentor->id)->count();
        $copies = $this->copiesAssignees($mentor);

        $mentor->removeRole(UserRole::Mentor->value);

        $audit->record(
            action: 'user.update',
            module: 'utilisateurs',
            acteur: Auth::guard('staff')->user(),
            cible: $mentor,
            avant: ['role' => UserRole::Mentor->value],
            apres: [
                'role' => null,
                // Ce que le retrait laisse derrière : c'est ce qu'on cherchera
                // le jour où douze copies n'auront plus de correcteur.
                'cours_orphelins' => $coursPortes,
                'copies_sans_correcteur' => $copies,
            ],
            cibleLibelle: $mentor->name.' <'.$mentor->email.'>',
        );

        $this->aRetrograder = null;

        $this->toastSuccess($copies === 0
            ? 'Rôle retiré. '.$mentor->name.' n\'accède plus au back-office pédagogique.'
            : 'Rôle retiré — '.$copies.' copie(s) assignée(s) n\'ont plus de correcteur.');
    }

    // ————————————————————————————————————————————————— lectures

    private function peutRetrograder(): bool
    {
        /*
         | `mentor.manage` et non `user.update` : promouvoir ou rétrograder un
         | mentor n'est pas modifier un compte. Les séparer permet de confier la
         | première sans la seconde — ce que la matrice prévoyait déjà, et que
         | le code ignorait.
         */
        return Auth::guard('staff')->user()?->can('mentor.manage') ?? false;
    }

    /**
     * Les copies encore ouvertes assignées à ce mentor.
     *
     * Les copies déjà rendues ne comptent pas : elles n'attendent plus rien.
     */
    private function copiesAssignees(User $mentor): int
    {
        return ExamAttempt::query()
            ->where('assigned_to', $mentor->id)
            ->whereIn('status', ['pending', 'grading'])
            ->count();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    private function lignes(): LengthAwarePaginator
    {
        return User::query()
            // Guard `web` : tous les rôles y sont semés (cf.
            // `User::getDefaultGuardName()`). `staff` lèverait au rendu.
            ->role(UserRole::Mentor->value, 'web')
            ->withCount([
                'coursEnseignes',
                'coursEnseignes as cours_publies_count' => fn (Builder $q) => $q->where('is_published', true),
            ])
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('name', 'like', $terme)
                    ->orWhere('email', 'like', $terme));
            })
            ->when($this->filtreEtat === 'sans-cours',
                fn (Builder $q) => $q->doesntHave('coursEnseignes'))
            ->when($this->filtreEtat === 'actifs',
                fn (Builder $q) => $q->has('coursEnseignes'))
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * @return array{total: int, avec_cours: int, sans_cours: int, copies_en_attente: int}
     */
    private function indicateurs(): array
    {
        $base = fn (): Builder => User::query()->role(UserRole::Mentor->value, 'web');

        return [
            'total' => $base()->count(),
            'avec_cours' => $base()->has('coursEnseignes')->count(),
            /*
             | Les mentors SANS cours méritent leur compteur : ce sont des
             | accès au back-office qui ne servent à rien, et un accès inutile
             | est une surface d'attaque de plus.
             */
            'sans_cours' => $base()->doesntHave('coursEnseignes')->count(),
            'copies_en_attente' => ExamAttempt::query()
                ->whereNotNull('assigned_to')
                ->whereIn('status', ['pending', 'grading'])
                ->count(),
        ];
    }

    private function mentor(string $uuid): User
    {
        return User::query()->where('uuid', $uuid)->firstOrFail();
    }
}
