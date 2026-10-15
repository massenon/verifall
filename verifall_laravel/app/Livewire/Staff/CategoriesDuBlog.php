<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\BlogCategory;
use App\Models\BlogPost;
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
 * « Catégories du Blog » — les rubriques éditoriales.
 *
 * ## Le plus simple des sept écrans, et il n'y a pas de honte à ça
 *
 * Quatre colonnes, cinq lignes, aucun effet de bord. `CLAUDE.md` tolère
 * Eloquent direct pour exactement ce cas — un CRUD de référentiel à faible
 * volume, lu depuis un seul écran.
 *
 * Ce qui mérite d'être écrit ici tient en une phrase : **le slug est figé à la
 * création**, parce qu'il est l'adresse publique de la rubrique. Un billet de
 * blog se partage, s'indexe, se met en favori ; renommer « Enquêtes » en
 * « Nos enquêtes » ne doit pas casser les liens.
 *
 * ## Pas de `is_published` ici, contrairement aux catégories de cours
 *
 * `blog_categories` n'a pas de drapeau de publication, et il n'y a pas lieu
 * d'en ajouter un : une rubrique de blog vide ne s'affiche simplement pas côté
 * public — c'est le nombre de billets qui décide, pas un état de plus à tenir.
 */
#[Layout('components.layouts.staff', ['title' => "Catégories du blog · Vérif'All"])]
final class CategoriesDuBlog extends Component
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

    public string $nom = '';

    public string $ordre = '0';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('blog_category.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.categories-du-blog', [
            'categories' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'peutGerer' => $this->peutGerer(),
            'categorieVisee' => $this->aSupprimer !== null ? $this->categorie($this->aSupprimer) : null,
        ]);
    }

    public function ouvrirCreation(): void
    {
        abort_unless($this->peutGerer(), 403);

        $this->reset('enEdition', 'nom');
        $this->ordre = (string) ((int) BlogCategory::query()->max('position') + 10);
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peutGerer(), 403);

        $categorie = $this->categorie($uuid);

        $this->enEdition = $uuid;
        $this->nom = (string) $categorie->name;
        $this->ordre = (string) $categorie->position;

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

        $categorie = $this->enEdition !== null ? $this->categorie($this->enEdition) : new BlogCategory;

        $avant = $categorie->exists ? $categorie->only(['name', 'position']) : null;

        $categorie->forceFill([
            'uuid' => $categorie->uuid ?? (string) Str::uuid(),
            // Figé : c'est l'adresse publique de la rubrique.
            'slug' => $categorie->slug ?? Str::slug($donnees['nom']).'-'.Str::lower(Str::random(4)),
            'name' => $donnees['nom'],
            'position' => (int) $donnees['ordre'],
        ])->save();

        $audit->record(
            action: $avant === null ? 'blog_category.create' : 'blog_category.update',
            module: 'blog',
            acteur: Auth::guard('staff')->user(),
            cible: $categorie,
            avant: $avant,
            apres: $categorie->only(['name', 'position']),
            cibleLibelle: $categorie->name,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Rubrique créée.' : 'Rubrique mise à jour.');
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
        $orphelins = $categorie->posts()->count();

        $audit->record(
            action: 'blog_category.delete',
            module: 'blog',
            acteur: Auth::guard('staff')->user(),
            cible: $categorie,
            avant: $categorie->only(['name']) + ['billets_orphelins' => $orphelins],
            cibleLibelle: $categorie->name,
        );

        $categorie->delete();

        $this->aSupprimer = null;
        $this->toastSuccess($orphelins === 0
            ? 'Rubrique supprimée.'
            : 'Rubrique supprimée — '.$orphelins.' billet(s) sont désormais sans rubrique.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:80'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['nom' => 'nom de la rubrique', 'ordre' => 'ordre d\'affichage'];
    }

    private function peutGerer(): bool
    {
        return Auth::guard('staff')->user()?->can('blog_category.manage') ?? false;
    }

    /**
     * @return LengthAwarePaginator<int, BlogCategory>
     */
    private function lignes(): LengthAwarePaginator
    {
        return BlogCategory::query()
            ->withCount('posts')
            ->when($this->recherche !== '',
                fn (Builder $q) => $q->where('name', 'like', '%'.$this->recherche.'%'))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * @return array{total: int, billets: int, sans_rubrique: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => BlogCategory::query()->count(),
            'billets' => BlogPost::query()->count(),
            'sans_rubrique' => BlogPost::query()->whereNull('blog_category_id')->count(),
        ];
    }

    private function categorie(string $uuid): BlogCategory
    {
        return BlogCategory::query()->withCount('posts')->where('uuid', $uuid)->firstOrFail();
    }
}
