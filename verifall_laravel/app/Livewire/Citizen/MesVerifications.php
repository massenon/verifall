<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Models\Verification;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Mes Vérifications » — page mes-verifications.html du prototype.
 *
 * L'historique personnel : ce qui a été soumis, ce qui en est sorti, et ce qui
 * tourne encore. C'est la page où l'on revient chercher un rapport qu'on a
 * fermé trop vite — elle doit donc montrer les analyses EN COURS autant que les
 * terminées.
 */
#[Layout('components.layouts.citizen', ['title' => "Mes Vérifications · Vérif'All"])]
final class MesVerifications extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'type', except: '')]
    public string $filtreType = '';

    #[Url(as: 'verdict', except: '')]
    public string $filtreVerdict = '';

    public function render(): View
    {
        return view('livewire.citizen.mes-verifications', [
            'verifications' => $this->verifications(),
            'types' => TypeContenu::cases(),
            'verdicts' => VerdictFactCheck::cases(),
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'filtreType', 'filtreVerdict'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'filtreType', 'filtreVerdict']);
        $this->resetPage();
    }

    public function filtrerParVerdict(string $verdict): void
    {
        $this->filtreVerdict = $this->filtreVerdict === $verdict ? '' : $verdict;
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Verification>
     */
    private function verifications(): LengthAwarePaginator
    {
        // with('report') : sans lui, chaque ligne déclencherait une requête —
        // et preventLazyLoading lèverait, ce qui est le comportement voulu.
        return $this->requete()->with('report')->latest('id')->paginate(10);
    }

    /**
     * @return Builder<Verification>
     */
    private function requete(): Builder
    {
        return Verification::query()
            // Toujours borné au propriétaire : c'est ici, et non dans
            // l'appelant, que la fuite se produirait si on l'oubliait.
            ->where('user_id', $this->utilisateur()->id)
            ->when($this->filtreType !== '', fn (Builder $q) => $q->where('type', $this->filtreType))
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                $q->where(fn (Builder $sous) => $sous->where('claim', 'like', $terme)
                    ->orWhere('context', 'like', $terme));
            })
            ->when($this->filtreVerdict === 'en_cours',
                // « En cours » n'est pas un verdict mais un état : le prototype
                // les mélange dans le même filtre, ce qui est juste du point de
                // vue de qui cherche « où en est ma demande ».
                fn (Builder $q) => $q->whereIn('status', StatutVerification::valeursEnCours()),
            )
            ->when($this->filtreVerdict !== '' && $this->filtreVerdict !== 'en_cours',
                fn (Builder $q) => $q->whereHas('report',
                    fn (Builder $r) => $r->where('verdict', $this->filtreVerdict)),
            );
    }

    /**
     * Indicateurs de l'en-tête.
     *
     * Calculés sur TOUT l'historique, pas sur la page filtrée : ce sont des
     * repères sur son activité, et ils changeraient de sens en suivant les
     * filtres.
     *
     * @return array{total: int, en_cours: int, intox: int, fiables: int, derniere: ?string}
     */
    #[Computed]
    public function indicateurs(): array
    {
        $utilisateur = $this->utilisateur();
        $base = Verification::where('user_id', $utilisateur->id);

        $derniere = (clone $base)->latest('created_at')->value('created_at');

        return [
            'total' => (clone $base)->count(),

            'en_cours' => (clone $base)
                ->whereIn('status', StatutVerification::valeursEnCours())
                ->count(),

            // « Intox détectées » réunit faux ET trompeur : du point de vue de
            // quelqu'un qui a failli partager, les deux sont des pièges.
            'intox' => (clone $base)->whereHas('report', fn (Builder $r) => $r->whereIn('verdict', [
                VerdictFactCheck::Faux->value,
                VerdictFactCheck::Trompeur->value,
            ]))->count(),

            'fiables' => (clone $base)->whereHas('report',
                fn (Builder $r) => $r->where('verdict', VerdictFactCheck::Vrai->value))->count(),

            'derniere' => $derniere?->diffForHumans(),
        ];
    }

    /** Des filtres sont-ils actifs ? */
    public function filtresActifs(): bool
    {
        return $this->recherche !== '' || $this->filtreType !== '' || $this->filtreVerdict !== '';
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
