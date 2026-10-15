<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Livewire\Citizen\Outils as OutilsCitoyen;
use App\Models\Tool;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Répertoire des outils » côté administration.
 *
 * ## Le même défaut que les Live Classes, et il n'avait pas été signalé
 *
 * L'entrée « Répertoire des outils » du menu latéral pointait sur
 * `route('citizen.tools')`. Comme pour les séances, l'administrateur basculait
 * du guard `staff` au guard `web` et sortait du back-office sans le vouloir.
 * Le défaut a été trouvé en auditant le gabarit, pas en le subissant — c'est
 * précisément le genre de lien qu'on n'essaie jamais soi-même.
 *
 * ## Pourquoi cet annuaire mérite un écran, et pas une ligne de configuration
 *
 * Il ne calcule rien. **Sa valeur tient entièrement à l'exactitude de ses
 * liens** — et un lien pourrit tout seul : le service ferme, le domaine est
 * racheté, l'outil devient payant. Une fiche périmée envoie un citoyen déposer
 * une capture d'écran sur un site qui n'est plus celui qu'on recommandait.
 *
 * Corriger cela demandait jusqu'ici un développeur et un déploiement, pour une
 * décision qui n'a rien de technique.
 *
 * ## Les catégories ne sont pas modifiables ici, et c'est voulu
 *
 * Elles sont une liste fermée, tenue par `Citizen\Outils` : ce sont les clés de
 * filtre du prototype, et elles servent aussi de clés de langue. Les rendre
 * libres ferait naître « Images », « images » et « Image » dans la même liste,
 * et casserait le filtrage citoyen sans qu'aucune erreur ne le signale.
 */
#[Layout('components.layouts.staff', ['title' => "Répertoire des outils · Vérif'All"])]
final class Outils extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'categorie', except: '')]
    public string $filtreCategorie = '';

    /** '' | 'publies' | 'brouillons' | 'recommandes' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $titre = '';

    public string $descriptif = '';

    public string $categorie = '';

    public string $etiquette = '';

    public string $icone = '';

    public string $lien = '';

    public string $libelleLien = '';

    public string $motsClefs = '';

    public bool $recommande = false;

    public bool $publie = false;

    public string $ordre = '0';

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
        $this->authorize('viewAny', Tool::class);
    }

    public function render(): View
    {
        return view('livewire.staff.outils', [
            'outils' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'categories' => $this->categories(),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', Tool::class);

        $this->reset('enEdition', 'titre', 'descriptif', 'etiquette', 'icone',
            'lien', 'libelleLien', 'motsClefs', 'recommande', 'publie');

        $this->categorie = (string) array_key_first($this->categories());
        $this->ordre = '0';
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $outil = $this->outil($uuid);
        $this->authorize('update', $outil);

        $this->enEdition = $uuid;
        $this->titre = (string) $outil->title;
        $this->descriptif = (string) $outil->description;
        $this->categorie = (string) $outil->category;
        $this->etiquette = (string) $outil->tag;
        $this->icone = (string) $outil->icon;
        $this->lien = (string) $outil->url;
        $this->libelleLien = (string) $outil->link_label;
        $this->motsClefs = (string) $outil->keywords;
        $this->recommande = (bool) $outil->is_recommended;
        $this->publie = (bool) $outil->is_published;
        $this->ordre = (string) $outil->sort_order;

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

        $outil = $this->enEdition !== null ? $this->outil($this->enEdition) : new Tool;

        $this->authorize($outil->exists ? 'update' : 'create', $outil->exists ? $outil : Tool::class);

        $suivies = [
            'title', 'description', 'category', 'tag', 'icon',
            'url', 'link_label', 'keywords', 'is_recommended', 'is_published', 'sort_order',
        ];

        $avant = $outil->exists ? $outil->only($suivies) : null;

        $outil->forceFill([
            'uuid' => $outil->uuid ?? (string) Str::uuid(),
            /*
             | Le slug est figé à la création et ne suit PAS le titre.
             |
             | C'est la clé de route de la fiche : la laisser suivre un titre
             | corrigé casserait tout lien déjà partagé, pour un gain nul —
             | personne ne lit le slug d'un outil.
             */
            'slug' => $outil->slug ?? Str::slug($donnees['titre']).'-'.Str::lower(Str::random(5)),
            'title' => $donnees['titre'],
            'description' => $donnees['descriptif'],
            'category' => $donnees['categorie'],
            'tag' => $donnees['etiquette'],
            'icon' => $donnees['icone'],
            'url' => $donnees['lien'],
            'link_label' => $donnees['libelleLien'],
            'keywords' => $donnees['motsClefs'],
            'is_recommended' => $donnees['recommande'],
            'is_published' => $donnees['publie'],
            'sort_order' => (int) $donnees['ordre'],
        ])->save();

        $audit->record(
            action: $avant === null ? 'tool.create' : 'tool.update',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $outil,
            avant: $avant,
            apres: $outil->only($suivies),
            cibleLibelle: $outil->title,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Outil ajouté.' : 'Fiche mise à jour.');
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        $outil = $this->outil($uuid);
        $this->authorize('update', $outil);

        $publie = ! $outil->is_published;

        $outil->forceFill(['is_published' => $publie])->save();

        $audit->record(
            action: $publie ? 'tool.publish' : 'tool.unpublish',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $outil,
            avant: ['is_published' => ! $publie],
            apres: ['is_published' => $publie],
            cibleLibelle: $outil->title,
        );

        $this->toastSuccess($publie ? 'Outil publié.' : 'Outil retiré du répertoire.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        $this->authorize('delete', $this->outil($uuid));
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

        $outil = $this->outil($this->aSupprimer);
        $this->authorize('delete', $outil);

        $audit->record(
            action: 'tool.delete',
            module: 'ressources',
            acteur: Auth::guard('staff')->user(),
            cible: $outil,
            avant: $outil->only(['title', 'url', 'is_published']),
            cibleLibelle: $outil->title,
        );

        $outil->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Fiche supprimée.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'min:2', 'max:120'],
            'descriptif' => ['required', 'string', 'min:20', 'max:1000'],
            /*
             | `in:` sur la liste fermée du composant citoyen. Une catégorie
             | libre ferait naître « Images » et « images » côte à côte, et le
             | filtrage citoyen — qui compare la chaîne exacte — cesserait de
             | trouver la moitié des fiches, sans erreur pour le signaler.
             */
            'categorie' => ['required', 'string', Rule::in(array_keys($this->categories()))],
            'etiquette' => ['nullable', 'string', 'max:40'],
            'icone' => ['nullable', 'string', 'max:40'],
            /*
             | Un outil recommandé à des citoyens qui y déposeront des captures
             | d'écran : `https` n'est pas négociable.
             */
            'lien' => ['required', 'url', 'starts_with:https://', 'max:255'],
            'libelleLien' => ['nullable', 'string', 'max:60'],
            'motsClefs' => ['nullable', 'string', 'max:255'],
            'recommande' => ['boolean'],
            'publie' => ['boolean'],
            'ordre' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'nom de l\'outil',
            'descriptif' => 'description',
            'categorie' => 'catégorie',
            'etiquette' => 'étiquette',
            'icone' => 'icône',
            'lien' => 'adresse',
            'libelleLien' => 'libellé du lien',
            'motsClefs' => 'mots-clefs',
            'ordre' => 'ordre d\'affichage',
        ];
    }

    /**
     * Les catégories, reprises telles quelles du composant citoyen.
     *
     * UNE SEULE SOURCE. Recopier la liste ici la ferait diverger au premier
     * ajout : l'administration proposerait une catégorie que l'écran citoyen ne
     * saurait pas filtrer, et la fiche deviendrait invisible.
     *
     * @return array<string, string>
     */
    private function categories(): array
    {
        return OutilsCitoyen::CATEGORIES;
    }

    /**
     * @return LengthAwarePaginator<int, Tool>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Tool::query()
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('title', 'like', $terme)
                    ->orWhere('description', 'like', $terme)
                    ->orWhere('keywords', 'like', $terme));
            })
            ->when($this->filtreCategorie !== '',
                fn (Builder $q) => $q->where('category', $this->filtreCategorie))
            ->when($this->filtreEtat === 'publies', fn (Builder $q) => $q->where('is_published', true))
            ->when($this->filtreEtat === 'brouillons', fn (Builder $q) => $q->where('is_published', false))
            ->when($this->filtreEtat === 'recommandes', fn (Builder $q) => $q->where('is_recommended', true))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(15);
    }

    /**
     * @return array{total: int, publies: int, recommandes: int, categories: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => Tool::query()->count(),
            'publies' => Tool::query()->where('is_published', true)->count(),
            'recommandes' => Tool::query()->where('is_recommended', true)->count(),
            'categories' => count($this->categories()),
        ];
    }

    private function outil(string $uuid): Tool
    {
        return Tool::query()->where('uuid', $uuid)->firstOrFail();
    }
}
