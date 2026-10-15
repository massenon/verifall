<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Enums\StatutParrainage;
use App\Models\Referral;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Parrainage » — ce que le programme produit réellement.
 *
 * ## Pourquoi cet écran est en LECTURE SEULE
 *
 * Un parrainage n'est pas une fiche que l'on crée : c'est la trace d'un
 * enchaînement de faits — quelqu'un s'est inscrit avec un code, puis a fait sa
 * première vérification éligible. Offrir un bouton « créer un parrainage »
 * reviendrait à permettre de fabriquer cette histoire après coup, et donc de
 * distribuer des unités sans contrepartie.
 *
 * Il n'y a pas non plus de bouton « récompenser ». La récompense est versée par
 * `Parrainage::honorer()` au moment exact où le filleul remplit la condition ;
 * la déclencher à la main ici court-circuiterait la règle et créerait deux
 * chemins vers le même effet — dont un seul serait testé.
 *
 * Ce que l'écran apporte, c'est ce qui manquait : voir. Combien de parrainages,
 * combien restent à l'étape « inscrit » sans jamais devenir éligibles, combien
 * d'unités le programme a distribuées. Un dispositif d'acquisition qu'on ne
 * mesure pas est un dispositif qu'on ne peut ni arrêter ni défendre.
 *
 * ## Le chiffre qui compte
 *
 * Le taux de transformation entre « inscrit » et « éligible ». S'il est bas,
 * le programme attire des inscriptions qui ne se servent jamais du produit —
 * ce qui est la définition d'un canal d'acquisition à corriger.
 */
#[Layout('components.layouts.staff', ['title' => "Parrainage · Vérif'All"])]
final class Parrainages extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'registered' | 'qualified' | 'rewarded' */
    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    /** '' | '7' | '30' | '90' */
    #[Url(as: 'periode', except: '')]
    public string $filtrePeriode = '';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreStatut(): void
    {
        $this->resetPage();
    }

    public function updatingFiltrePeriode(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreStatut', 'filtrePeriode');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.staff.parrainages', [
            'parrainages' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'statuts' => StatutParrainage::cases(),
        ]);
    }

    private function lignes(): LengthAwarePaginator
    {
        return Referral::query()
            ->with(['referrer:id,name,email', 'referred:id,name,email'])
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';

                /*
                 | On cherche des deux côtés du lien. Une réclamation arrive
                 | toujours par l'un ou l'autre — « je n'ai pas reçu mes
                 | unités » vient du parrain, « on m'a parrainé » du filleul —
                 | et obliger à savoir lequel avant de chercher, c'est faire
                 | perdre du temps à celui qui répond.
                 */
                $requete->where(function (Builder $ou) use ($terme): void {
                    $ou->whereHas('referrer', fn (Builder $q) => $q
                        ->where('name', 'like', $terme)->orWhere('email', 'like', $terme))
                        ->orWhereHas('referred', fn (Builder $q) => $q
                            ->where('name', 'like', $terme)->orWhere('email', 'like', $terme));
                });
            })
            ->when($this->filtreStatut !== '', fn (Builder $q) => $q->where('status', $this->filtreStatut))
            ->when($this->filtrePeriode !== '', fn (Builder $q) => $q
                ->where('registered_at', '>=', now()->subDays((int) $this->filtrePeriode)))
            ->latest('registered_at')
            ->paginate(20);
    }

    /**
     * @return array{total: int, inscrits: int, eligibles: int, recompenses: int, unites: int, transformation: ?float}
     */
    private function indicateurs(): array
    {
        /*
         | Une seule requête agrégée plutôt que cinq comptages : ces chiffres
         | s'affichent sur chaque page, filtres compris.
         */
        $compte = Referral::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as inscrits', [StatutParrainage::Inscrit->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as eligibles', [StatutParrainage::Eligible->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as recompenses', [StatutParrainage::Recompense->value])
            ->selectRaw('coalesce(sum(referrer_points), 0) + coalesce(sum(referred_points), 0) as unites')
            ->first();

        $total = (int) ($compte->total ?? 0);
        $recompenses = (int) ($compte->recompenses ?? 0);
        $eligibles = (int) ($compte->eligibles ?? 0);

        return [
            'total' => $total,
            'inscrits' => (int) ($compte->inscrits ?? 0),
            'eligibles' => $eligibles,
            'recompenses' => $recompenses,
            'unites' => (int) ($compte->unites ?? 0),
            /*
             | Le taux de transformation : parmi tous les filleuls inscrits,
             | quelle part est allée jusqu'à une première vérification.
             |
             | `null` quand il n'y a aucun parrainage — et non zéro. « 0 % »
             | se lit « le programme ne marche pas » ; l'absence de données se
             | lit « il n'a pas encore tourné ». Ce ne sont pas les mêmes
             | conclusions, et c'est la règle §2 du chantier.
             */
            'transformation' => $total > 0
                ? round((($eligibles + $recompenses) / $total) * 100, 1)
                : null,
        ];
    }
}
