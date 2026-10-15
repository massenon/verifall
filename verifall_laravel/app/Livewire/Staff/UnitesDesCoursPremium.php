<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Enums\MotifMouvementUnite;
use App\Models\Course;
use App\Models\UnitLog;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Unités des Cours Premium » — la grille tarifaire de l'Académie.
 *
 * ## Pourquoi un écran à part, alors que le coût est déjà dans la fiche du cours
 *
 * Il l'est, et on peut l'y changer un cours à la fois. Mais fixer des prix est
 * un geste COMPARATIF : on ne décide pas qu'un cours vaut 30 unités dans
 * l'absolu, on décide qu'il en vaut plus que celui d'à côté et autant que le
 * troisième. Cela demande de voir la grille entière, pas quinze fiches ouvertes
 * l'une après l'autre.
 *
 * L'écran montre donc tous les cours payants côte à côte, avec ce que chacun a
 * réellement rapporté — un cours à 50 unités que personne n'achète et un cours
 * à 5 unités que tout le monde prend ne se corrigent pas de la même façon.
 *
 * ## Le revenu est lu au JOURNAL, pas recalculé
 *
 * `UnitLog` porte les débits au motif `CoursPremium`, avec le cours en source
 * polymorphe. C'est la seule mesure exacte : multiplier le prix actuel par le
 * nombre d'inscrits compterait au tarif d'aujourd'hui des achats faits hier,
 * à un autre prix — et donnerait un chiffre faux qui a l'air juste.
 *
 * ## Le droit est celui du cours
 *
 * Pas de permission neuve : changer le prix d'un cours, c'est le modifier, donc
 * `course.update`. Le mentor y accède pour ses cours — la Policy le borne — ce
 * qui est cohérent : c'est lui qui sait ce que son cours demande de travail.
 */
#[Layout('components.layouts.staff', ['title' => "Unités des cours premium · Vérif'All"])]
final class UnitesDesCoursPremium extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'payants' | 'gratuits' */
    #[Url(as: 'tarif', except: 'payants')]
    public string $filtreTarif = 'payants';

    /**
     * Les coûts en cours de saisie, indexés par uuid de cours.
     *
     * L'écran est une GRILLE : on corrige trois prix d'affilée avant
     * d'enregistrer. Un tiroir par cours ferait perdre la comparaison, qui est
     * la seule raison d'être de cet écran.
     *
     * @var array<string, string>
     */
    public array $couts = [];

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreTarif(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Course::class);
    }

    public function render(): View
    {
        $cours = $this->lignes();

        /*
         | Les champs sont amorcés à CHAQUE rendu, pour les lignes qui n'ont pas
         | encore de valeur saisie. Sans le `??=`, changer de page effacerait
         | une correction en cours ; sans l'amorçage, les champs de la page 2
         | s'afficheraient vides et enregistreraient zéro.
         */
        foreach ($cours as $unCours) {
            $this->couts[$unCours->uuid] ??= (string) $unCours->cost_units;
        }

        return view('livewire.staff.unites-des-cours-premium', [
            'cours' => $cours,
            'indicateurs' => $this->indicateurs(),
            'revenus' => $this->revenusParCours($cours->pluck('id')->all()),
        ]);
    }

    /**
     * Enregistre le tarif d'un cours.
     */
    public function enregistrerLeTarif(string $uuid, AuditLogService $audit): void
    {
        $cours = Course::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $cours);

        $this->validate(
            ['couts.'.$uuid => ['required', 'integer', 'min:0', 'max:500']],
            attributes: ['couts.'.$uuid => 'coût'],
        );

        $avant = (int) $cours->cost_units;
        $apres = (int) $this->couts[$uuid];

        if ($avant === $apres) {
            return;
        }

        $cours->forceFill(['cost_units' => $apres])->save();

        $audit->record(
            action: 'course.price',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $cours,
            avant: ['cost_units' => $avant],
            apres: ['cost_units' => $apres],
            cibleLibelle: $cours->title,
        );

        /*
         | Le message dit ce qui NE CHANGE PAS. Un tarif modifié ne rembourse ni
         | ne redébite personne : les inscrits gardent leur accès au prix payé.
         | Sans cette phrase, on croit avoir agi sur l'existant.
         */
        $this->toastSuccess($apres === 0
            ? 'Cours rendu gratuit. Les inscrits déjà payants gardent leur accès.'
            : 'Tarif enregistré : '.$apres.' unité(s). Les inscriptions déjà payées ne changent pas.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return LengthAwarePaginator<int, Course>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Course::query()
            ->with('categorie')
            ->withCount('enrollments')
            ->when($this->recherche !== '', function (Builder $requete): void {
                $requete->where('title', 'like', '%'.$this->recherche.'%');
            })
            ->when($this->filtreTarif === 'payants', fn (Builder $q) => $q->where('cost_units', '>', 0))
            ->when($this->filtreTarif === 'gratuits', fn (Builder $q) => $q->where('cost_units', 0))
            ->orderByDesc('cost_units')
            ->orderBy('title')
            ->paginate(15);
    }

    /**
     * Ce que chaque cours a RÉELLEMENT rapporté, d'après le journal.
     *
     * Une seule requête agrégée pour la page entière. Compter au tarif actuel
     * multiplié par le nombre d'inscrits donnerait un chiffre faux qui a l'air
     * juste : il appliquerait le prix d'aujourd'hui à des achats d'hier.
     *
     * @param  list<int>  $coursIds
     * @return array<int, int>
     */
    private function revenusParCours(array $coursIds): array
    {
        if ($coursIds === []) {
            return [];
        }

        return UnitLog::query()
            ->where('reason', MotifMouvementUnite::CoursPremium->value)
            ->where('source_type', Course::class)
            ->whereIn('source_id', $coursIds)
            ->selectRaw('source_id, COALESCE(SUM(-delta), 0) as total')
            ->groupBy('source_id')
            ->pluck('total', 'source_id')
            ->map(static fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @return array{payants: int, gratuits: int, prix_moyen: int, revenu: int}
     */
    private function indicateurs(): array
    {
        $payants = Course::query()->where('cost_units', '>', 0);

        return [
            'payants' => (clone $payants)->count(),
            'gratuits' => Course::query()->where('cost_units', 0)->count(),
            'prix_moyen' => (int) round((float) (clone $payants)->avg('cost_units')),
            'revenu' => (int) UnitLog::query()
                ->where('reason', MotifMouvementUnite::CoursPremium->value)
                ->sum('delta') * -1,
        ];
    }
}
