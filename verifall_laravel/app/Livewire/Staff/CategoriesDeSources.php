<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Core\Identity\Services\AuditLogService;
use App\Models\SourceCategory;
use App\Models\TrustedSource;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Catégories de sources » — les familles du référentiel.
 *
 * ## Pourquoi une table, alors que `Tool` se contente d'une chaîne
 *
 * Les catégories d'outils sont une liste FERMÉE, reprise des filtres du
 * prototype. Celles-ci ne le sont pas : la rédaction crée ses familles au fil
 * de sa veille. Une chaîne libre aurait fait naître « Presse » à côté de
 * « presse » et de « Presse écrite », et le filtre du référentiel aurait cessé
 * de trouver la moitié des fiches sans qu'aucune erreur ne le signale.
 *
 * ## Une famille porte une décision, pas seulement un nom
 *
 * `default_credibility` est la raison d'être de la table. Référencer trente
 * agences de presse en tapant « 90 » trente fois, c'est trente occasions de
 * taper « 9 ». La famille propose, la fiche dispose.
 *
 * ## La suppression ne cascade pas, et l'écran le dit
 *
 * La clé étrangère est en `nullOnDelete` : supprimer une famille laisse ses
 * sources en place, sans famille. C'est le bon comportement — une fiche
 * effacée par ricochet se redécouvre à la main — mais c'est contre-intuitif,
 * donc la confirmation l'annonce et le compteur de sources reste visible.
 */
#[Layout('components.layouts.staff', ['title' => "Catégories de sources · Vérif'All"])]
final class CategoriesDeSources extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $nom = '';

    public string $descriptif = '';

    public string $credibiliteParDefaut = '70';

    public string $ordre = '0';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', SourceCategory::class);
    }

    public function render(): View
    {
        return view('livewire.staff.categories-de-sources', [
            'familles' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'familleASupprimer' => $this->aSupprimer !== null ? $this->famille($this->aSupprimer) : null,
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', SourceCategory::class);

        $this->reset('enEdition', 'nom', 'descriptif');
        $this->credibiliteParDefaut = '70';
        $this->ordre = (string) ((int) SourceCategory::query()->max('sort_order') + 10);
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $famille = $this->famille($uuid);
        $this->authorize('update', $famille);

        $this->enEdition = $uuid;
        $this->nom = (string) $famille->name;
        $this->descriptif = (string) $famille->description;
        $this->credibiliteParDefaut = (string) $famille->default_credibility;
        $this->ordre = (string) $famille->sort_order;

        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->resetValidation();
    }

    public function enregistrer(AuditLogService $audit): void
    {
        $donnees = $this->validate();

        $famille = $this->enEdition !== null ? $this->famille($this->enEdition) : new SourceCategory;

        $this->authorize($famille->exists ? 'update' : 'create', $famille->exists ? $famille : SourceCategory::class);

        $suivies = ['name', 'description', 'default_credibility', 'sort_order'];
        $avant = $famille->exists ? $famille->only($suivies) : null;

        $famille->forceFill([
            'uuid' => $famille->uuid ?? (string) Str::uuid(),
            /*
             | Le slug est figé à la création, comme celui des outils : il
             | identifie la famille, et personne ne le lit. Le laisser suivre un
             | nom corrigé n'apporterait rien et casserait ce qui s'y adosse.
             */
            'slug' => $famille->slug ?? Str::slug($donnees['nom']).'-'.Str::lower(Str::random(4)),
            'name' => $donnees['nom'],
            'description' => $donnees['descriptif'] !== '' ? $donnees['descriptif'] : null,
            'default_credibility' => (int) $donnees['credibiliteParDefaut'],
            'sort_order' => (int) $donnees['ordre'],
        ])->save();

        $audit->record(
            action: $avant === null ? 'trusted_source.category_create' : 'trusted_source.category_update',
            module: 'fact-checking',
            acteur: Auth::guard('staff')->user(),
            cible: $famille,
            avant: $avant,
            apres: $famille->only($suivies),
            cibleLibelle: $famille->name,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Famille créée.' : 'Famille mise à jour.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        $this->authorize('delete', $this->famille($uuid));
        $this->aSupprimer = $uuid;
    }

    public function annulerLaSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(AuditLogService $audit, DepotSourcesFiables $depot): void
    {
        if ($this->aSupprimer === null) {
            return;
        }

        $famille = $this->famille($this->aSupprimer);
        $this->authorize('delete', $famille);

        $orphelines = $famille->sources()->count();

        $audit->record(
            action: 'trusted_source.category_delete',
            module: 'fact-checking',
            acteur: Auth::guard('staff')->user(),
            cible: $famille,
            avant: $famille->only(['name', 'default_credibility'])
                // Combien de fiches se retrouvent sans famille : c'est ce qu'on
                // cherchera dans le journal si le référentiel paraît désordonné.
                + ['sources_orphelines' => $orphelines],
            cibleLibelle: $famille->name,
        );

        $famille->delete();

        /*
         | Le dépôt met en cache les sources AVEC leur famille chargée : une
         | famille supprimée y resterait attachée jusqu'à quinze minutes, et le
         | moteur citerait au citoyen une famille qui n'existe plus.
         */
        $depot->oublier();

        $this->aSupprimer = null;
        $this->toastSuccess($orphelines === 0
            ? 'Famille supprimée.'
            : 'Famille supprimée — '.$orphelines.' source(s) sont désormais sans famille.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:120'],
            'descriptif' => ['nullable', 'string', 'max:500'],
            'credibiliteParDefaut' => ['required', 'integer', 'min:0', 'max:100'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nom' => 'nom de la famille',
            'descriptif' => 'description',
            'credibiliteParDefaut' => 'crédibilité par défaut',
            'ordre' => 'ordre d\'affichage',
        ];
    }

    /**
     * @return LengthAwarePaginator<int, SourceCategory>
     */
    private function lignes(): LengthAwarePaginator
    {
        return SourceCategory::query()
            // Le compteur, en une requête plutôt qu'une par ligne.
            ->withCount('sources')
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('name', 'like', $terme)
                    ->orWhere('description', 'like', $terme));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return array{total: int, sources: int, sans_famille: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => SourceCategory::query()->count(),
            'sources' => TrustedSource::query()->count(),
            /*
             | Les fiches sans famille méritent leur compteur : ce sont celles
             | qu'une suppression a laissées derrière, et rien d'autre ne les
             | signalerait. Un référentiel qui les oublie se met à ressembler à
             | un tas.
             */
            'sans_famille' => TrustedSource::query()->whereNull('source_category_id')->count(),
        ];
    }

    private function famille(string $uuid): SourceCategory
    {
        return SourceCategory::query()->withCount('sources')->where('uuid', $uuid)->firstOrFail();
    }
}
