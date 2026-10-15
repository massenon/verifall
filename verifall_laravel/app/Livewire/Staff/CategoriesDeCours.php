<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Catégories » du catalogue de l'Académie.
 *
 * ## Ce que cet écran répare
 *
 * `courses.category` était une chaîne libre, et deux semeurs y avaient écrit
 * chacun dans son style : « Fact-Checking », « OSINT », « IA » d'un côté ;
 * « investigation », « securite », « sante », « methode » de l'autre. Le
 * catalogue citoyen construit son menu de filtres par `distinct()->pluck()` :
 * il affichait donc les deux conventions dans la même liste.
 *
 * Renommer une catégorie demandait jusqu'ici un `UPDATE` sur toutes les lignes
 * concernées — c'est-à-dire un développeur, pour une décision éditoriale.
 *
 * ## Le renommage écrit AUX DEUX ENDROITS, et c'est le point délicat
 *
 * La table est la source ; `courses.category` en reste une copie que trois
 * lecteurs consultent encore (catalogue citoyen, fiche de cours, API v1).
 * Renommer sans propager laisserait le rayon affiché sous l'ancien nom, sans
 * qu'aucune erreur ne le signale — la pire des deux moitiés.
 *
 * La propagation est donc faite dans la même transaction que le renommage.
 */
#[Layout('components.layouts.staff', ['title' => "Catégories de cours · Vérif'All"])]
final class CategoriesDeCours extends Component
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

    public string $icone = '';

    public string $ordre = '0';

    public bool $publiee = true;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', CourseCategory::class);
    }

    public function render(): View
    {
        return view('livewire.staff.categories-de-cours', [
            'categories' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'categorieASupprimer' => $this->aSupprimer !== null ? $this->categorie($this->aSupprimer) : null,
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', CourseCategory::class);

        $this->reset('enEdition', 'nom', 'descriptif', 'icone');
        $this->ordre = (string) ((int) CourseCategory::query()->max('sort_order') + 10);
        $this->publiee = true;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $categorie = $this->categorie($uuid);
        $this->authorize('update', $categorie);

        $this->enEdition = $uuid;
        $this->nom = (string) $categorie->name;
        $this->descriptif = (string) $categorie->description;
        $this->icone = (string) $categorie->icon;
        $this->ordre = (string) $categorie->sort_order;
        $this->publiee = (bool) $categorie->is_published;

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

        $categorie = $this->enEdition !== null ? $this->categorie($this->enEdition) : new CourseCategory;

        $this->authorize($categorie->exists ? 'update' : 'create',
            $categorie->exists ? $categorie : CourseCategory::class);

        $suivies = ['name', 'description', 'icon', 'sort_order', 'is_published'];
        $avant = $categorie->exists ? $categorie->only($suivies) : null;
        $ancienNom = $categorie->exists ? (string) $categorie->name : null;

        DB::transaction(function () use ($categorie, $donnees, $ancienNom): void {
            $categorie->forceFill([
                'uuid' => $categorie->uuid ?? (string) Str::uuid(),
                // Slug figé à la création : c'est la clé de route, et un rayon
                // renommé ne doit pas casser les liens déjà partagés.
                'slug' => $categorie->slug ?? Str::slug($donnees['nom']).'-'.Str::lower(Str::random(4)),
                'name' => $donnees['nom'],
                'description' => $donnees['descriptif'] !== '' ? $donnees['descriptif'] : null,
                'icon' => $donnees['icone'] !== '' ? $donnees['icone'] : null,
                'sort_order' => (int) $donnees['ordre'],
                'is_published' => $donnees['publiee'],
            ])->save();

            /*
             | LA PROPAGATION. Sans elle, le catalogue citoyen — qui lit encore
             | la colonne texte — continuerait d'afficher l'ancien nom pour
             | toujours, et le seul symptôme serait « le renommage n'a pas
             | marché ».
             */
            if ($ancienNom !== null && $ancienNom !== $donnees['nom']) {
                Course::query()
                    ->where('course_category_id', $categorie->id)
                    ->update(['category' => $donnees['nom']]);
            }
        });

        $audit->record(
            action: $avant === null ? 'lms_category.create' : 'lms_category.update',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $categorie,
            avant: $avant,
            apres: $categorie->only($suivies),
            cibleLibelle: $categorie->name,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Catégorie créée.' : 'Catégorie mise à jour.');
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        $categorie = $this->categorie($uuid);
        $this->authorize('update', $categorie);

        $publiee = ! $categorie->is_published;

        $categorie->forceFill(['is_published' => $publiee])->save();

        $audit->record(
            action: $publiee ? 'lms_category.publish' : 'lms_category.unpublish',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $categorie,
            avant: ['is_published' => ! $publiee],
            apres: ['is_published' => $publiee],
            cibleLibelle: $categorie->name,
        );

        $this->toastSuccess($publiee ? 'Catégorie affichée au catalogue.' : 'Catégorie masquée du catalogue.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        $this->authorize('delete', $this->categorie($uuid));
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

        $categorie = $this->categorie($this->aSupprimer);
        $this->authorize('delete', $categorie);

        $orphelins = $categorie->cours()->count();

        $audit->record(
            action: 'lms_category.delete',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $categorie,
            avant: $categorie->only(['name', 'is_published']) + ['cours_orphelins' => $orphelins],
            cibleLibelle: $categorie->name,
        );

        $categorie->delete();

        $this->aSupprimer = null;
        $this->toastSuccess($orphelins === 0
            ? 'Catégorie supprimée.'
            : 'Catégorie supprimée — '.$orphelins.' cours sont désormais sans catégorie.');
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
            'icone' => ['nullable', 'string', 'max:60'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
            'publiee' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nom' => 'nom de la catégorie',
            'descriptif' => 'description',
            'icone' => 'icône',
            'ordre' => 'ordre d\'affichage',
        ];
    }

    /**
     * @return LengthAwarePaginator<int, CourseCategory>
     */
    private function lignes(): LengthAwarePaginator
    {
        return CourseCategory::query()
            ->withCount('cours')
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
     * @return array{total: int, publiees: int, cours: int, sans_categorie: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => CourseCategory::query()->count(),
            'publiees' => CourseCategory::query()->where('is_published', true)->count(),
            'cours' => Course::query()->count(),
            /*
             | Les cours sans catégorie ne se voient nulle part ailleurs : ils
             | apparaissent au catalogue avec un rayon vide. Le compteur est le
             | seul endroit qui les signale.
             */
            'sans_categorie' => Course::query()->whereNull('course_category_id')->count(),
        ];
    }

    private function categorie(string $uuid): CourseCategory
    {
        return CourseCategory::query()->withCount('cours')->where('uuid', $uuid)->firstOrFail();
    }
}
