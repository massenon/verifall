<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Enums\VerdictFactCheck;
use App\Jobs\AlerterSurUneIntox;
use App\Models\HubArticle;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mews\Purifier\Facades\Purifier;

/**
 * « Actualités » du Hub d'infos — la rédaction des démentis publiés.
 *
 * ## Pourquoi Eloquent direct, alors que 21 lecteurs partagent ce modèle
 *
 * Le critère 2 de `CLAUDE.md` (lecture cachée) semble réuni : le flux
 * ClaimReview est mis en cache une heure. Mais **l'invalidation n'est pas dans
 * un dépôt, elle est dans un observateur** — `#[ObservedBy(ObservateurHubArticle)]`
 * sur le modèle lui-même.
 *
 * C'est plus fort qu'un dépôt : l'observateur se déclenche sur TOUT chemin
 * d'écriture, y compris `tinker`, un seeder ou une commande. Un dépôt qui
 * oublierait `oublier()` laisserait le flux servir un verdict FAUX pendant une
 * heure à un moteur qui l'ingère sans relecture — soit précisément le scénario
 * que la correction visait à empêcher.
 *
 * Ajouter un dépôt ici ne protégerait donc rien de plus, et donnerait un
 * vingt-deuxième lecteur avec un vingt-deuxième style. Le chantier
 * d'unification est consigné au journal, sa taille mesurée.
 *
 * ## Deux effets de bord automatiques, et il faut les connaître
 *
 * Enregistrer un article MET EN FILE la fabrication de sa carte de démenti et
 * OUBLIE le cache du flux ClaimReview. Les deux passent par l'observateur.
 * La carte exige donc `queue:work` — sans worker, elle attend en table `jobs`
 * sans que rien ne le signale.
 *
 * Supprimer un article emporte sa carte : sans cela, l'image resterait servie
 * par son URL publique, et WhatsApp continuerait d'afficher un démenti que le
 * site ne montre plus.
 */
#[Layout('components.layouts.staff', ['title' => "Actualités du Hub · Vérif'All"])]
final class ActualitesHub extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'verdict', except: '')]
    public string $filtreVerdict = '';

    /** '' | 'publies' | 'brouillons' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $titre = '';

    /**
     * Ce démenti mérite-t-il une alerte par courriel ?
     *
     * Séparé de « à la une » : mettre en avant sur le site et écrire à trois
     * mille personnes ne sont pas le même engagement. Et séparé de la
     * catégorie : celle-ci décrit le SUJET, l'urgence décrit la CIRCULATION.
     */
    public bool $urgent = false;

    public string $affirmation = '';

    public string $chapeau = '';

    public string $corps = '';

    public string $categorie = '';

    public string $verdict = '';

    public string $nomDeLaSource = '';

    public string $lienDeLaSource = '';

    /*
     | RÉFÉRENCEMENT. `Referencement.php` lisait `meta_title` et
     | `meta_description` sur `HubArticle` DEPUIS L'ORIGINE — et les colonnes
     | n'existaient pas. Eloquent rendait `null` sans lever, le `?:` se
     | rabattait sur le titre, et la surcharge SEO du Hub n'a jamais rien fait.
     | Les colonnes sont posées le 2 septembre 2026 ; voici leurs champs.
     */
    public string $metaTitre = '';

    public string $metaDescription = '';

    public string $metaMotsCles = '';

    /**
     * L'image de couverture. Nulle tant que rien n'est déposé : l'article garde
     * alors la sienne, au lieu de la perdre à chaque correction de virgule.
     */
    public ?TemporaryUploadedFile $couverture = null;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreVerdict(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreVerdict', 'filtreEtat');
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('hub_article.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.actualites-hub', [
            'articles' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'verdicts' => VerdictFactCheck::cases(),
            'categories' => $this->categoriesExistantes(),
            'peutCreer' => $this->peut('hub_article.create'),
            'peutModifier' => $this->peut('hub_article.update'),
            'peutPublier' => $this->peut('hub_article.publish'),
            'peutSupprimer' => $this->peut('hub_article.delete'),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        abort_unless($this->peut('hub_article.create'), 403);

        $this->reset('enEdition', 'titre', 'urgent', 'affirmation', 'chapeau', 'corps',
            'categorie', 'nomDeLaSource', 'lienDeLaSource');
        $this->verdict = VerdictFactCheck::Faux->value;
        $this->resetValidation();
        $this->formulaireOuvert = true;

        $this->dispatch('corps-article-charge', html: '');
    }

    public function ouvrirEdition(string $uuid): void
    {
        abort_unless($this->peut('hub_article.update'), 403);

        $article = $this->article($uuid);

        $this->enEdition = $uuid;
        $this->titre = (string) $article->title;
        $this->urgent = (bool) $article->is_urgent;
        $this->affirmation = (string) $article->claim;
        $this->chapeau = (string) $article->excerpt;
        $this->corps = (string) $article->body;
        $this->categorie = (string) $article->category;
        $this->verdict = $article->verdict instanceof VerdictFactCheck
            ? $article->verdict->value
            : (string) $article->verdict;
        $this->nomDeLaSource = (string) $article->source_name;
        $this->lienDeLaSource = (string) $article->source_url;
        $this->metaTitre = (string) $article->meta_title;
        $this->metaDescription = (string) $article->meta_description;
        $this->metaMotsCles = (string) $article->meta_keywords;
        $this->couverture = null;

        $this->resetValidation();
        $this->formulaireOuvert = true;

        // L'hôte de Quill est en `wire:ignore` : sans cet événement, ouvrir un
        // second article afficherait le corps du premier — et l'enregistrer
        // l'écraserait par lui.
        $this->dispatch('corps-article-charge', html: $this->corps);
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

        $article = $this->enEdition !== null ? $this->article($this->enEdition) : new HubArticle;

        abort_unless($this->peut($article->exists ? 'hub_article.update' : 'hub_article.create'), 403);

        $suivies = ['title', 'claim', 'category', 'verdict', 'source_name', 'source_url', 'is_published', 'is_urgent',
            'meta_title', 'meta_description', 'meta_keywords', 'cover_path'];
        $avant = $article->exists ? $article->only($suivies) : null;

        $article->forceFill([
            'uuid' => $article->uuid ?? (string) Str::uuid(),
            // Slug figé à la création : c'est l'adresse publique de l'article,
            // et un démenti se partage — le lien doit survivre à une correction
            // de titre.
            'slug' => $article->slug ?? Str::slug($donnees['titre']).'-'.Str::lower(Str::random(5)),
            'title' => $donnees['titre'],
            'claim' => $donnees['affirmation'],
            'excerpt' => $donnees['chapeau'],
            /*
             | PURIFIÉ AVANT PERSISTANCE. Le corps vient de Quill et sera rendu
             | au public — y compris à des moteurs qui l'ingèrent. La règle 3 du
             | chantier l'exige aux deux bouts.
             */
            'body' => $this->purifier($donnees['corps']),
            'category' => $donnees['categorie'],
            'verdict' => $donnees['verdict'],
            'source_name' => $donnees['nomDeLaSource'] !== '' ? $donnees['nomDeLaSource'] : null,
            'source_url' => $donnees['lienDeLaSource'] !== '' ? $donnees['lienDeLaSource'] : null,
            'author_id' => $article->author_id ?? Auth::guard('staff')->id(),
            'is_urgent' => $this->urgent,
            'meta_title' => $donnees['metaTitre'] !== '' ? $donnees['metaTitre'] : null,
            'meta_description' => $donnees['metaDescription'] !== '' ? $donnees['metaDescription'] : null,
            'meta_keywords' => $donnees['metaMotsCles'] !== '' ? $donnees['metaMotsCles'] : null,
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
            $article->forceFill(['cover_path' => $this->couverture->storeAs(
                'hub',
                $article->uuid.'.'.$this->couverture->extension(),
                ['disk' => 'public'],
            )])->save();
        }

        $audit->record(
            action: $avant === null ? 'hub_article.create' : 'hub_article.update',
            module: 'hub',
            acteur: Auth::guard('staff')->user(),
            cible: $article,
            avant: $avant,
            apres: $article->only($suivies),
            cibleLibelle: $article->title,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null
            ? 'Article créé, en brouillon.'
            : 'Article mis à jour. La carte de démenti se refabrique en file.');
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        abort_unless($this->peut('hub_article.publish'), 403);

        $article = $this->article($uuid);
        $publie = ! $article->is_published;

        $article->forceFill([
            'is_published' => $publie,
            // La date de première mise en ligne ne se réécrit pas : c'est elle
            // qui ordonne le fil d'actualités et qui date le démenti.
            'published_at' => $publie ? ($article->published_at ?? now()) : $article->published_at,
        ])->save();

        $audit->record(
            action: $publie ? 'hub_article.publish' : 'hub_article.unpublish',
            module: 'hub',
            acteur: Auth::guard('staff')->user(),
            cible: $article,
            avant: ['is_published' => ! $publie],
            apres: ['is_published' => $publie],
            cibleLibelle: $article->title,
        );

        /*
         | L'alerte ne part QUE si l'article est marqué urgent, et seulement à
         | la première publication. Le job revérifie les deux, plus
         | l'interrupteur du catalogue : entre la mise en file et l'exécution,
         | l'administration a pu l'éteindre — sur le message le plus intrusif du
         | produit, on relit.
         */
        if ($publie && $article->is_urgent && $article->alerted_at === null) {
            AlerterSurUneIntox::dispatch($article->id);
        }

        $this->toastSuccess($publie
            ? 'Article publié. Sa carte de démenti part en file de fabrication.'
            : 'Article dépublié.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        abort_unless($this->peut('hub_article.delete'), 403);

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

        abort_unless($this->peut('hub_article.delete'), 403);

        $article = $this->article($this->aSupprimer);

        $audit->record(
            action: 'hub_article.delete',
            module: 'hub',
            acteur: Auth::guard('staff')->user(),
            cible: $article,
            avant: $article->only(['title', 'claim', 'verdict', 'is_published']),
            cibleLibelle: $article->title,
        );

        // L'observateur emporte la carte de démenti : sans cela, l'image
        // resterait servie par son URL publique après le retrait.
        $article->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Article supprimé, carte de démenti comprise.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'min:5', 'max:200'],
            'urgent' => ['boolean'],
            'couverture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            /*
             | 70 et 160 : les longueurs au-delà desquelles Google tronque. Un
             | titre coupé au milieu d'un mot dessert le démenti qu'il annonce.
             */
            'metaTitre' => ['nullable', 'string', 'max:70'],
            'metaDescription' => ['nullable', 'string', 'max:160'],
            'metaMotsCles' => ['nullable', 'string', 'max:255'],
            /*
             | L'AFFIRMATION est le cœur d'un démenti : c'est la phrase qui
             | circule, reprise mot pour mot. Sans elle, l'article explique une
             | rumeur que personne ne reconnaît — et le flux ClaimReview, qui la
             | publie comme `claimReviewed`, n'a rien à donner aux moteurs.
             */
            'affirmation' => ['required', 'string', 'min:10', 'max:500'],
            'chapeau' => ['required', 'string', 'min:20', 'max:500'],
            'corps' => ['required', 'string', 'min:50'],
            'categorie' => ['required', 'string', 'max:60'],
            'verdict' => ['required', Rule::enum(VerdictFactCheck::class)],
            'nomDeLaSource' => ['nullable', 'string', 'max:160'],
            'lienDeLaSource' => ['nullable', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'titre',
            'affirmation' => 'affirmation vérifiée',
            'chapeau' => 'chapeau',
            'corps' => 'corps de l\'article',
            'categorie' => 'catégorie',
            'verdict' => 'verdict',
            'nomDeLaSource' => 'nom de la source',
            'lienDeLaSource' => 'lien de la source',
        ];
    }

    private function peut(string $permission): bool
    {
        return Auth::guard('staff')->user()?->can($permission) ?? false;
    }

    private function purifier(string $html): string
    {
        return trim((string) Purifier::clean($html, 'default'));
    }

    /**
     * Les catégories déjà employées, pour éviter d'en inventer une variante.
     *
     * Chaîne libre au schéma — comme l'étaient les catégories de cours avant le
     * lot 6, avec le même risque. La liste des valeurs existantes ne l'empêche
     * pas, mais elle rend la divergence visible au moment de la saisie.
     *
     * @return list<string>
     */
    private function categoriesExistantes(): array
    {
        return HubArticle::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, HubArticle>
     */
    private function lignes(): LengthAwarePaginator
    {
        return HubArticle::query()
            ->with('author:id,uuid,name')
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('title', 'like', $terme)
                    ->orWhere('claim', 'like', $terme)
                    ->orWhere('excerpt', 'like', $terme));
            })
            ->when($this->filtreVerdict !== '', fn (Builder $q) => $q->where('verdict', $this->filtreVerdict))
            ->when($this->filtreEtat === 'publies', fn (Builder $q) => $q->where('is_published', true))
            ->when($this->filtreEtat === 'brouillons', fn (Builder $q) => $q->where('is_published', false))
            ->latest('id')
            ->paginate(15);
    }

    /**
     * @return array{total: int, publies: int, brouillons: int, faux: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => HubArticle::query()->count(),
            'publies' => HubArticle::query()->where('is_published', true)->count(),
            'brouillons' => HubArticle::query()->where('is_published', false)->count(),
            'faux' => HubArticle::query()
                ->where('is_published', true)
                ->where('verdict', VerdictFactCheck::Faux->value)
                ->count(),
        ];
    }

    private function article(string $uuid): HubArticle
    {
        return HubArticle::query()->where('uuid', $uuid)->firstOrFail();
    }
}
