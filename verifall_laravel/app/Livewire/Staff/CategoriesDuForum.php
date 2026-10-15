<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\ForumCategory;
use App\Models\Thread;
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
 * « Catégories du Forum » — les rubriques du débat citoyen.
 *
 * ## Un CRUD de référentiel, et l'arbitrage du lot 0 s'applique
 *
 * `CLAUDE.md` tolère explicitement Eloquent direct pour « un CRUD de
 * référentiel à faible volume, lu depuis un seul écran : catégories, FAQ,
 * partenaires… ». C'est exactement ce cas : cinq rubriques, une table de
 * quatre colonnes.
 *
 * ## La désactivation plutôt que la suppression
 *
 * `ForumCategory` porte `is_active`. Désactiver retire la rubrique des choix
 * offerts au citoyen SANS toucher aux sujets qu'elle contient : ils restent
 * lisibles, classés, retrouvables. C'est le geste ordinaire quand une rubrique
 * ne prend pas.
 *
 * La suppression, elle, laisse les sujets orphelins — la clé étrangère les
 * garde, mais sans rubrique. Elle est offerte, précédée du compte exact.
 *
 * ## La couleur n'est pas décorative
 *
 * `color` sert la pastille du forum citoyen. Deux rubriques de la même couleur
 * rendent le repérage visuel inopérant — l'écran affiche donc la couleur, pour
 * qu'on voie ce qui se ressemble avant de choisir.
 */
#[Layout('components.layouts.staff', ['title' => "Catégories du forum · Vérif'All"])]
final class CategoriesDuForum extends Component
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

    public string $couleur = '#059669';

    public string $ordre = '0';

    public bool $active = true;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('forum_category.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.categories-du-forum', [
            'categories' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'peutGerer' => $this->peutGerer(),
            'categorieVisee' => $this->aSupprimer !== null ? $this->categorie($this->aSupprimer) : null,
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->reset('enEdition', 'nom');
        $this->couleur = '#059669';
        $this->ordre = (string) ((int) ForumCategory::query()->max('position') + 10);
        $this->active = true;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $categorie = $this->categorie($uuid);

        $this->enEdition = $uuid;
        $this->nom = (string) $categorie->name;
        $this->couleur = (string) ($categorie->color ?? '#059669');
        $this->ordre = (string) $categorie->position;
        $this->active = (bool) $categorie->is_active;

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
        abort_unless($this->peutGerer(), 403);

        $donnees = $this->validate();

        $categorie = $this->enEdition !== null ? $this->categorie($this->enEdition) : new ForumCategory;

        $suivies = ['name', 'color', 'position', 'is_active'];
        $avant = $categorie->exists ? $categorie->only($suivies) : null;

        $categorie->forceFill([
            'uuid' => $categorie->uuid ?? (string) Str::uuid(),
            /*
             | Slug figé à la création. C'est la clé de filtre de l'écran
             | citoyen ET de l'API mobile (`ForumController` cherche par slug) :
             | le laisser suivre un nom corrigé casserait les deux d'un coup,
             | sans qu'aucune erreur ne le signale.
             */
            'slug' => $categorie->slug ?? Str::slug($donnees['nom']).'-'.Str::lower(Str::random(4)),
            'name' => $donnees['nom'],
            'color' => $donnees['couleur'],
            'position' => (int) $donnees['ordre'],
            'is_active' => $donnees['active'],
        ])->save();

        $audit->record(
            action: $avant === null ? 'forum_category.create' : 'forum_category.update',
            module: 'communaute',
            acteur: Auth::guard('staff')->user(),
            cible: $categorie,
            avant: $avant,
            apres: $categorie->only($suivies),
            cibleLibelle: $categorie->name,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Rubrique créée.' : 'Rubrique mise à jour.');
    }

    public function basculerLActivation(string $uuid, AuditLogService $audit): void
    {
        abort_unless($this->peutGerer(), 403);

        $categorie = $this->categorie($uuid);
        $active = ! $categorie->is_active;

        $categorie->forceFill(['is_active' => $active])->save();

        $audit->record(
            action: $active ? 'forum_category.activate' : 'forum_category.deactivate',
            module: 'communaute',
            acteur: Auth::guard('staff')->user(),
            cible: $categorie,
            avant: ['is_active' => ! $active],
            apres: ['is_active' => $active],
            cibleLibelle: $categorie->name,
        );

        $this->toastSuccess($active
            ? 'Rubrique rouverte : les citoyens peuvent y publier.'
            : 'Rubrique fermée. Les sujets existants restent lisibles.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->aSupprimer = $uuid;
    }

    public function annulerLaSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(AuditLogService $audit): void
    {
        if ($this->aSupprimer === null) {
            return;
        }

        abort_unless($this->peutGerer(), 403);

        $categorie = $this->categorie($this->aSupprimer);
        $orphelins = $categorie->threads()->count();

        $audit->record(
            action: 'forum_category.delete',
            module: 'communaute',
            acteur: Auth::guard('staff')->user(),
            cible: $categorie,
            avant: $categorie->only(['name', 'is_active']) + ['sujets_orphelins' => $orphelins],
            cibleLibelle: $categorie->name,
        );

        $categorie->delete();

        $this->aSupprimer = null;
        $this->toastSuccess($orphelins === 0
            ? 'Rubrique supprimée.'
            : 'Rubrique supprimée — '.$orphelins.' sujet(s) sont désormais sans rubrique.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:80'],
            // Forme hexadécimale stricte : la valeur alimente un attribut de
            // style côté citoyen, et une chaîne libre y serait injectée telle
            // quelle.
            'couleur' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nom' => 'nom de la rubrique',
            'couleur' => 'couleur',
            'ordre' => 'ordre d\'affichage',
        ];
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('forum_category.manage') ?? false;
    }

    /**
     * @return LengthAwarePaginator<int, ForumCategory>
     */
    private function lignes(): LengthAwarePaginator
    {
        return ForumCategory::query()
            ->withCount('threads')
            ->when($this->recherche !== '',
                fn (Builder $q) => $q->where('name', 'like', '%'.$this->recherche.'%'))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * @return array{total: int, actives: int, sujets: int, sans_rubrique: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => ForumCategory::query()->count(),
            'actives' => ForumCategory::query()->where('is_active', true)->count(),
            'sujets' => Thread::query()->count(),
            'sans_rubrique' => Thread::query()->whereNull('forum_category_id')->count(),
        ];
    }

    private function categorie(string $uuid): ForumCategory
    {
        return ForumCategory::query()->withCount('threads')->where('uuid', $uuid)->firstOrFail();
    }
}
