<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mews\Purifier\Facades\Purifier;

/**
 * « Articles du Blog » — la publication éditoriale.
 *
 * ## Ce qui distingue le blog du Hub d'infos
 *
 * Un article du Hub est un DÉMENTI : il porte une affirmation vérifiée, un
 * verdict, et alimente le flux ClaimReview que les moteurs ingèrent. Un billet
 * de blog est un texte de fond — méthode, coulisses, tribune — sans verdict et
 * sans obligation de source.
 *
 * Les confondre serait grave dans un sens : un texte d'opinion qui entrerait
 * dans le flux ClaimReview y serait lu comme une vérification. C'est pourquoi
 * ce sont deux tables, deux écrans, deux jeux de droits.
 *
 * ## `publish` séparé de `update`, comme pour les cours
 *
 * `post => ['view', 'create', 'update', 'publish', 'delete']`. La case
 * « publier » ne figure PAS au formulaire : l'y mettre rendrait le droit
 * contournable par quiconque peut modifier. Publier engage la plateforme —
 * le billet part au plan du site, au référencement et à l'infolettre.
 *
 * ## Les métadonnées SEO sont facultatives, et se replient sur le contenu
 *
 * `meta_title` et `meta_description` laissés vides ne produisent pas une page
 * sans métadonnées : `Referencement` se rabat sur le titre et le chapeau. Les
 * champs servent aux cas où l'on veut s'en écarter — un titre éditorial
 * accrocheur et un titre de recherche explicite ne sont pas le même texte.
 */
#[Layout('components.layouts.staff', ['title' => "Articles du blog · Vérif'All"])]
final class ArticlesDuBlog extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'rubrique', except: '')]
    public string $filtreCategorie = '';

    /** '' | 'publies' | 'brouillons' | 'une' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $titre = '';

    public string $chapeau = '';

    public string $corps = '';

    public string $categorieUuid = '';

    public bool $aLaUne = false;

    public string $metaTitre = '';

    public string $metaDescription = '';

    public string $metaMotsCles = '';

    /**
     * L'image de couverture. Nulle tant que rien n'est déposé : l'article garde
     * alors la sienne, au lieu de la perdre à chaque correction de virgule.
     */
    public ?TemporaryUploadedFile $couverture = null;

    /** Heure du dernier enregistrement automatique (HH:MM), pour le dire à l'écran. */
    public string $dernierEnregistrement = '';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreCategorie(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCategorie', 'filtreEtat');
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('post.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.articles-du-blog', [
            'estPublie' => $this->enEdition !== null && (bool) BlogPost::query()->where('uuid', $this->enEdition)->value('is_published'),
            'billets' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'categories' => $this->categories(),
            'peutCreer' => $this->peut('post.create'),
            'peutModifier' => $this->peut('post.update'),
            'peutPublier' => $this->peut('post.publish'),
            'peutSupprimer' => $this->peut('post.delete'),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        abort_unless($this->peut('post.create'), 403);

        $this->reset('enEdition', 'titre', 'chapeau', 'corps', 'categorieUuid',
            'metaTitre', 'metaDescription');
        $this->aLaUne = false;
        $this->dernierEnregistrement = '';
        $this->resetValidation();
        $this->formulaireOuvert = true;

        $this->dispatch('corps-billet-charge', html: '');
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peut('post.update'), 403);

        $billet = $this->billet($uuid);

        $this->enEdition = $uuid;
        $this->titre = (string) $billet->title;
        $this->chapeau = (string) $billet->excerpt;
        $this->corps = (string) $billet->body;
        $this->categorieUuid = (string) ($billet->category?->uuid ?? '');
        $this->aLaUne = (bool) $billet->is_featured;
        $this->metaTitre = (string) $billet->meta_title;
        $this->metaDescription = (string) $billet->meta_description;
        $this->metaMotsCles = (string) $billet->meta_keywords;
        $this->couverture = null;

        $this->resetValidation();
        $this->formulaireOuvert = true;

        $this->dispatch('corps-billet-charge', html: $this->corps);
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->enEdition = null;
        $this->resetValidation();
    }

    /**
     * Enregistrement automatique, en brouillon.
     *
     * Jamais sur un billet PUBLIÉ : il écrirait dans le texte en ligne sans que
     * personne n'ait cliqué « Enregistrer » — et sans la trace d'audit que ce
     * geste laisse. Sur un brouillon, rien n'est visible du public : le risque
     * est nul. Silencieux si le brouillon n'est pas encore valide (titre ou
     * corps vide, champ trop long) : on n'affiche pas d'erreur pendant la frappe.
     */
    public function autoEnregistrer(AuditLogService $audit): void
    {
        if (! $this->formulaireOuvert || trim($this->titre) === '' || trim(strip_tags($this->corps)) === '') {
            return;
        }

        if ($this->enEdition !== null && $this->billet($this->enEdition)->is_published) {
            return;
        }

        try {
            $this->enregistrer($audit, fermer: false);
        } catch (ValidationException) {
            $this->resetValidation();

            return;
        }

        $this->dernierEnregistrement = now()->format('H:i');
    }

    public function enregistrer(AuditLogService $audit, bool $fermer = true): void
    {
        $donnees = $this->validate();

        $billet = $this->enEdition !== null ? $this->billet($this->enEdition) : new BlogPost;

        abort_unless($this->peut($billet->exists ? 'post.update' : 'post.create'), 403);

        $categorie = $donnees['categorieUuid'] !== ''
            ? $this->categories()->firstWhere('uuid', $donnees['categorieUuid'])
            : null;

        $suivies = ['title', 'blog_category_id', 'is_featured', 'is_published',
            'meta_title', 'meta_description', 'meta_keywords', 'cover_path'];
        $avant = $billet->exists ? $billet->only($suivies) : null;

        $billet->forceFill([
            'uuid' => $billet->uuid ?? (string) Str::uuid(),
            // Figé : c'est l'adresse publique du billet, et un texte se partage.
            'slug' => $billet->slug ?? Str::slug($donnees['titre']).'-'.Str::lower(Str::random(5)),
            'title' => $donnees['titre'],
            'excerpt' => $donnees['chapeau'],
            // PURIFIÉ AVANT PERSISTANCE : le corps vient de Quill et sera rendu
            // au public. Règle 3 du chantier, aux deux bouts.
            'body' => trim((string) Purifier::clean($donnees['corps'], 'default')),
            'blog_category_id' => $categorie?->id,
            'is_featured' => $donnees['aLaUne'],
            'meta_title' => $donnees['metaTitre'] !== '' ? $donnees['metaTitre'] : null,
            'meta_description' => $donnees['metaDescription'] !== '' ? $donnees['metaDescription'] : null,
            'meta_keywords' => $donnees['metaMotsCles'] !== '' ? $donnees['metaMotsCles'] : null,
            'author_id' => $billet->author_id ?? Auth::guard('staff')->id(),
        ])->save();

        /*
         | La couverture APRÈS l'enregistrement : le fichier est rangé sous
         | l'uuid, qui n'existe pas encore à la création. Et seulement si un
         | fichier est déposé — sinon l'article perdrait son image dès qu'on
         | corrige une virgule.
         |
         | Disque PUBLIC : une couverture est faite pour être vue, y compris par
         | les aperçus de partage. La cacher la rendrait invisible là où elle sert.
         */
        if ($this->couverture !== null) {
            $billet->forceFill(['cover_path' => $this->couverture->storeAs(
                'blog',
                $billet->uuid.'.'.$this->couverture->extension(),
                ['disk' => 'public'],
            )])->save();
        }

        $audit->record(
            action: $avant === null ? 'post.create' : 'post.update',
            module: 'blog',
            acteur: Auth::guard('staff')->user(),
            cible: $billet,
            avant: $avant,
            apres: $billet->only($suivies),
            cibleLibelle: $billet->title,
        );

        if (! $fermer) {
            // Le billet existe désormais : l'enregistrement suivant le met à jour
            // au lieu d'en créer un second.
            $this->enEdition = $billet->uuid;
            $this->couverture = null;

            return;
        }

        $this->dernierEnregistrement = '';
        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Billet créé, en brouillon.' : 'Billet mis à jour.');
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        abort_unless($this->peut('post.publish'), 403);

        $billet = $this->billet($uuid);
        $publie = ! $billet->is_published;

        $billet->forceFill([
            'is_published' => $publie,
            // Ne se réécrit pas : c'est la date qui ordonne le blog et qui
            // s'affiche au lecteur.
            'published_at' => $publie ? ($billet->published_at ?? now()) : $billet->published_at,
        ])->save();

        $audit->record(
            action: $publie ? 'post.publish' : 'post.unpublish',
            module: 'blog',
            acteur: Auth::guard('staff')->user(),
            cible: $billet,
            avant: ['is_published' => ! $publie],
            apres: ['is_published' => $publie],
            cibleLibelle: $billet->title,
        );

        $this->toastSuccess($publie
            ? 'Billet publié : il entre au plan du site et au référencement.'
            : 'Billet dépublié.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        abort_unless($this->peut('post.delete'), 403);

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

        abort_unless($this->peut('post.delete'), 403);

        $billet = $this->billet($this->aSupprimer);

        $audit->record(
            action: 'post.delete',
            module: 'blog',
            acteur: Auth::guard('staff')->user(),
            cible: $billet,
            avant: $billet->only(['title', 'is_published', 'is_featured']),
            cibleLibelle: $billet->title,
        );

        $billet->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Billet supprimé.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'min:5', 'max:200'],
            'chapeau' => ['required', 'string', 'min:20', 'max:500'],
            'corps' => ['required', 'string', 'min:50'],
            'categorieUuid' => ['nullable', 'string', Rule::in($this->categories()->pluck('uuid')->all())],
            'aLaUne' => ['boolean'],
            'metaTitre' => ['nullable', 'string', 'max:70'],
            /*
             | Bornées aux longueurs que les moteurs affichent réellement :
             | 70 caractères pour un titre, 160 pour une description. Au-delà,
             | la fin est tronquée — et une phrase coupée au milieu vaut moins
             | qu'une phrase courte écrite exprès.
             */
            'metaDescription' => ['nullable', 'string', 'max:160'],
            'couverture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'metaMotsCles' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'titre',
            'chapeau' => 'chapeau',
            'corps' => 'corps du billet',
            'categorieUuid' => 'rubrique',
            'metaTitre' => 'titre de recherche',
            'metaDescription' => 'description de recherche',
        ];
    }

    private function peut(string $permission): bool
    {
        return Auth::guard('staff')->user()?->can($permission) ?? false;
    }

    /**
     * @return Collection<int, BlogCategory>
     */
    private function categories(): Collection
    {
        return BlogCategory::query()->orderBy('position')->orderBy('name')->get();
    }

    /**
     * @return LengthAwarePaginator<int, BlogPost>
     */
    private function lignes(): LengthAwarePaginator
    {
        return BlogPost::query()
            ->with(['category:id,uuid,name', 'author:id,uuid,name'])
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('title', 'like', $terme)
                    ->orWhere('excerpt', 'like', $terme));
            })
            ->when($this->filtreCategorie !== '', function (Builder $requete): void {
                $requete->whereHas('category', fn (Builder $q) => $q->where('uuid', $this->filtreCategorie));
            })
            ->when($this->filtreEtat === 'publies', fn (Builder $q) => $q->where('is_published', true))
            ->when($this->filtreEtat === 'brouillons', fn (Builder $q) => $q->where('is_published', false))
            ->when($this->filtreEtat === 'une', fn (Builder $q) => $q->where('is_featured', true))
            ->latest('id')
            ->paginate(15);
    }

    /**
     * @return array{total: int, publies: int, brouillons: int, une: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => BlogPost::query()->count(),
            'publies' => BlogPost::query()->where('is_published', true)->count(),
            'brouillons' => BlogPost::query()->where('is_published', false)->count(),
            'une' => BlogPost::query()->where('is_featured', true)->where('is_published', true)->count(),
        ];
    }

    private function billet(string $uuid): BlogPost
    {
        return BlogPost::query()->with('category')->where('uuid', $uuid)->firstOrFail();
    }
}
