<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Enums\NiveauCours;
use App\Enums\UserRole;
use App\Jobs\AnnoncerLeNouveauCours;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

/**
 * « Tous les Cours » — la fiche d'un cours, sans son contenu.
 *
 * ## Le partage avec le constructeur, et pourquoi il est net
 *
 * Cet écran tient ce qui décrit le cours : titre, rayon, niveau, coût, durée,
 * formateur, publication. Le CONTENU — leçons, quiz — appartient au
 * constructeur (`Staff\ConstructeurDeCours`), qui s'ouvre depuis une ligne.
 *
 * Réunir les deux donnerait un formulaire à quinze champs et une liste de
 * quarante-six leçons dans le même tiroir. Ce sont aussi deux gestes de nature
 * différente : on décrit un cours une fois, on en réécrit le contenu pendant
 * des semaines.
 *
 * ## `publish` est séparé de `update`, et la matrice l'a voulu
 *
 * `course => ['view', 'create', 'update', 'publish', 'archive', 'delete']`.
 * Le mentor porte `create` et `update`, jamais `publish` : il écrit ses cours,
 * l'administrateur les met en ligne. Publier engage la plateforme.
 *
 * L'écran s'y tient — le bouton de publication est gardé séparément, et la
 * case « publier » ne figure PAS dans le formulaire d'édition. La mettre
 * là rendrait `publish` contournable par quiconque peut modifier.
 *
 * ## Le mentor ne voit que SES cours
 *
 * La matrice le dit : « production pédagogique, cantonnée à SES ressources ».
 * Le filtre est dans la requête, pas dans la Policy : une Policy dit qui peut
 * agir sur une ligne qu'on lui montre, elle n'empêche pas de la LISTER.
 */
#[Layout('components.layouts.staff', ['title' => "Tous les cours · Vérif'All"])]
final class Cours extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'rayon', except: '')]
    public string $filtreCategorie = '';

    #[Url(as: 'niveau', except: '')]
    public string $filtreNiveau = '';

    /** '' | 'publies' | 'brouillons' | 'archives' */
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

    public string $categorieUuid = '';

    public string $niveau = 'beginner';

    public string $duree = '';

    public string $cout = '0';

    public string $formateurUuid = '';

    public bool $delivreUnCertificat = true;

    /**
     * L'image de couverture. Nulle tant que rien n'est déposé : le cours garde
     * alors celle qu'il avait, au lieu de la perdre à chaque enregistrement.
     */
    public ?TemporaryUploadedFile $couverture = null;

    /**
     * Prérequis et objectifs, saisis UNE LIGNE PAR ENTRÉE.
     *
     * Le stockage est un tableau ; le formulaire, un `textarea`. Un champ par
     * ligne obligerait à décider d'avance combien il en faut, et un tableau
     * stocké en bloc de texte se remet en page de travers dès qu'on change de
     * gabarit.
     */
    public string $prerequis = '';

    public string $objectifs = '';

    public string $langue = 'fr';

    /**
     * La prime versée à 100 % d'avancement.
     *
     * Sur le COURS et non sur la leçon : une récompense attribuée à
     * l'achèvement ne peut pas vivre sur une partie qui ignore l'ensemble.
     */
    public string $xp = '0';

    // — référencement
    public string $metaTitre = '';

    public string $metaDescription = '';

    public string $metaMotsCles = '';

    /**
     * Les langues d'enseignement.
     *
     * Liste FERMÉE et volontairement courte : la plateforme est bilingue, et un
     * champ libre laisserait entrer « Français », « francais », « FR » — trois
     * valeurs pour une langue, et un filtre qui n'en trouve aucune.
     *
     * Le fon et le yoruba n'y sont pas parce qu'aucun cours n'existe dans ces
     * langues ; les ajouter le jour venu est une ligne.
     *
     * @var array<string, string>
     */
    public const LANGUES = [
        'fr' => 'Français',
        'en' => 'English',
    ];

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreCategorie(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreNiveau(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCategorie', 'filtreNiveau', 'filtreEtat');
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Course::class);
    }

    public function render(): View
    {
        return view('livewire.staff.cours', [
            'langues' => self::LANGUES,
            // L'URL de la couverture actuelle, pour l'aperçu à l'édition. Nulle
            // à la création : il n'y a rien à montrer.
            'couvertureActuelle' => $this->enEdition !== null
                ? ($this->cours($this->enEdition)->cover_path
                    ? Storage::disk('public')->url($this->cours($this->enEdition)->cover_path)
                    : null)
                : null,
            'cours' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'categories' => $this->categories(),
            'niveaux' => NiveauCours::cases(),
            'formateurs' => $this->formateurs(),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', Course::class);

        $this->reset('enEdition', 'titre', 'descriptif', 'duree', 'formateurUuid');
        $this->categorieUuid = '';
        $this->niveau = NiveauCours::Debutant->value;
        $this->cout = '0';
        $this->delivreUnCertificat = true;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $cours = $this->cours($uuid);
        $this->authorize('update', $cours);

        $this->enEdition = $uuid;
        $this->titre = (string) $cours->title;
        $this->descriptif = (string) $cours->description;
        $this->categorieUuid = (string) ($cours->categorie?->uuid ?? '');
        $this->niveau = $cours->level instanceof NiveauCours ? $cours->level->value : NiveauCours::Debutant->value;
        $this->duree = (string) $cours->duration_label;
        $this->cout = (string) $cours->cost_units;
        $this->formateurUuid = (string) ($cours->instructor?->uuid ?? '');
        $this->langue = (string) ($cours->language ?: 'fr');
        $this->xp = (string) ((int) $cours->xp_reward);
        $this->prerequis = implode('
', (array) ($cours->prerequisites ?? []));
        $this->objectifs = implode('
', (array) ($cours->outcomes ?? []));
        $this->metaTitre = (string) $cours->meta_title;
        $this->metaDescription = (string) $cours->meta_description;
        $this->metaMotsCles = (string) $cours->meta_keywords;
        $this->couverture = null;
        $this->delivreUnCertificat = (bool) $cours->delivers_certificate;

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

        $cours = $this->enEdition !== null ? $this->cours($this->enEdition) : new Course;

        $this->authorize($cours->exists ? 'update' : 'create', $cours->exists ? $cours : Course::class);

        $suivies = [
            'title', 'description', 'category', 'course_category_id', 'level',
            'duration_label', 'cost_units', 'instructor_id', 'delivers_certificate',
            'language', 'xp_reward', 'prerequisites', 'outcomes',
            'meta_title', 'meta_description', 'meta_keywords',
        ];
        $avant = $cours->exists ? $cours->only($suivies) : null;

        $categorie = $donnees['categorieUuid'] !== ''
            ? CourseCategory::query()->where('uuid', $donnees['categorieUuid'])->first()
            : null;

        DB::transaction(function () use ($cours, $donnees, $categorie): void {
            $cours->forceFill([
                'uuid' => $cours->uuid ?? (string) Str::uuid(),
                // Figé à la création : c'est la clé de route publique du cours,
                // et un titre corrigé ne doit pas casser les liens partagés.
                'slug' => $cours->slug ?? Str::slug($donnees['titre']).'-'.Str::lower(Str::random(5)),
                'title' => $donnees['titre'],
                'description' => $donnees['descriptif'],
                'course_category_id' => $categorie?->id,
                /*
                 | LA COLONNE TEXTE EST TENUE À JOUR EN MÊME TEMPS. Elle reste
                 | ce que lisent le catalogue citoyen, la fiche de cours et
                 | l'API v1. Écrire l'une sans l'autre les ferait diverger sans
                 | qu'aucune erreur ne le signale.
                 */
                'category' => $categorie?->name ?? '',
                'level' => $donnees['niveau'],
                'duration_label' => $donnees['duree'],
                'cost_units' => (int) $donnees['cout'],
                'instructor_id' => $this->formateurId($donnees['formateurUuid']),
                'delivers_certificate' => $donnees['delivreUnCertificat'],
                'language' => $donnees['langue'],
                'xp_reward' => (int) $donnees['xp'],
                'prerequisites' => $this->enLignes($donnees['prerequis'] ?? ''),
                'outcomes' => $this->enLignes($donnees['objectifs'] ?? ''),
                'meta_title' => $this->ouNull($donnees['metaTitre'] ?? ''),
                'meta_description' => $this->ouNull($donnees['metaDescription'] ?? ''),
                'meta_keywords' => $this->ouNull($donnees['metaMotsCles'] ?? ''),
            ])->save();

            /*
             | La couverture APRÈS l'enregistrement : le fichier est rangé sous
             | l'uuid du cours, qui n'existe pas encore à la création.
             |
             | Et seulement si un fichier est déposé — sinon le cours garderait
             | une couverture vide dès qu'on corrige une virgule.
             */
            if ($this->couverture !== null) {
                $cours->forceFill(['cover_path' => $this->rangerLaCouverture($cours)])->save();
            }
        });

        $audit->record(
            action: $avant === null ? 'course.create' : 'course.update',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $cours,
            avant: $avant,
            apres: $cours->only($suivies),
            cibleLibelle: $cours->title,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null
            ? 'Cours créé. Ajoutez-lui des leçons depuis le constructeur.'
            : 'Fiche mise à jour.');
    }

    /**
     * Publier ou dépublier — geste distinct, droit distinct.
     */
    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        $cours = $this->cours($uuid);
        $this->authorize('publish', $cours);

        $publie = ! $cours->is_published;

        /*
         | Un cours SANS LEÇON ne se publie pas. Le catalogue l'afficherait,
         | quelqu'un s'y inscrirait, et tomberait sur une page vide — en ayant
         | peut-être dépensé des unités si le cours est premium.
         */
        if ($publie && $cours->lessons()->count() === 0) {
            $this->toastError('Ce cours n\'a aucune leçon : le publier enverrait les inscrits sur une page vide.');

            return;
        }

        $cours->forceFill([
            'is_published' => $publie,
            // La date de première mise en ligne ne se réécrit pas à chaque
            // republication : c'est elle qui ordonne les nouveautés.
            'published_at' => $publie ? ($cours->published_at ?? now()) : $cours->published_at,
        ])->save();

        $audit->record(
            action: $publie ? 'course.publish' : 'course.unpublish',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $cours,
            avant: ['is_published' => ! $publie],
            apres: ['is_published' => $publie],
            cibleLibelle: $cours->title,
        );

        /*
         | L'annonce part APRÈS la trace d'audit, et seulement à la PREMIÈRE
         | publication : `announced_at` est vérifié dans le job, pas ici, pour
         | que la règle vive au même endroit que l'envoi.
         |
         | Éteinte par défaut au catalogue — la mise en file coûte une ligne
         | dans `jobs`, et le job se tait si l'administration n'a pas armé
         | l'envoi. Vérifier ici aussi dupliquerait la décision.
         */
        if ($publie && $cours->announced_at === null) {
            AnnoncerLeNouveauCours::dispatch($cours->id);
        }

        $this->toastSuccess($publie ? 'Cours publié au catalogue.' : 'Cours retiré du catalogue.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        $this->authorize('delete', $this->cours($uuid));
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

        $cours = $this->cours($this->aSupprimer);
        $this->authorize('delete', $cours);

        $audit->record(
            action: 'course.delete',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $cours,
            avant: $cours->only(['title', 'is_published', 'cost_units'])
                + ['inscrits' => $cours->enrollments()->count()],
            cibleLibelle: $cours->title,
        );

        /*
         | `SoftDeletes` sur `Course` : la suppression est réversible, et c'est
         | délibéré — des inscriptions, des progressions et des certificats
         | pointent dessus. Un effacement dur laisserait des certificats dont
         | le cours n'existe plus, alors que la page publique de contrôle doit
         | pouvoir les afficher.
         */
        $cours->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Cours archivé.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    /**
     * Un texte à une entrée par ligne, ramené à une liste propre.
     *
     * Les lignes vides sont écartées et les clés renumérotées : sans cela, un
     * retour à la ligne de trop produirait une puce vide dans la fiche
     * publique, et un `array_filter` sans `array_values` donnerait un objet
     * JSON aux clés trouées au lieu d'un tableau.
     *
     * @return list<string>
     */
    private function enLignes(string $texte): array
    {
        return array_values(array_filter(array_map(
            static fn (string $ligne): string => trim($ligne),
            preg_split('/
|
|
/', $texte) ?: [],
        ), static fn (string $ligne): bool => $ligne !== ''));
    }

    /** Une chaîne vide vaut NULL en base : « renseigné à vide » n'existe pas. */
    private function ouNull(string $valeur): ?string
    {
        return trim($valeur) === '' ? null : trim($valeur);
    }

    /**
     * Range la couverture sur le disque PUBLIC.
     *
     * Contrairement aux supports de leçon, une couverture est faite pour être
     * vue : elle s'affiche dans le catalogue, dans les partages, dans les
     * aperçus de réseaux sociaux. La cacher derrière une route signée la
     * rendrait invisible là où elle sert.
     */
    private function rangerLaCouverture(Course $cours): string
    {
        return $this->couverture->storeAs(
            'courses',
            $cours->uuid.'.'.$this->couverture->extension(),
            ['disk' => 'public'],
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'min:3', 'max:180'],
            'descriptif' => ['required', 'string', 'min:20', 'max:2000'],
            'categorieUuid' => ['nullable', 'string', Rule::in($this->categories()->pluck('uuid')->all())],
            'niveau' => ['required', Rule::enum(NiveauCours::class)],
            'duree' => ['required', 'string', 'max:40'],
            /*
             | Plafonné à 500. `cost_units` est un `smallInteger` non signé, mais
             | la vraie borne est éditoriale : un cours à 5 000 unités serait
             | plus cher que tous les packs vendus, donc inachetable — et rien
             | ne le dirait à l'inscrit avant l'échec du débit.
             */
            'cout' => ['required', 'integer', 'min:0', 'max:500'],
            'formateurUuid' => ['nullable', 'string', Rule::in($this->formateurs()->pluck('uuid')->all())],
            'delivreUnCertificat' => ['boolean'],

            /*
             | `image` et non `file` : refuse un PDF ou un exécutable renommé.
             | `nullable` : sans dépôt, le cours GARDE sa couverture — la vider
             | à chaque enregistrement ferait disparaître l'image dès qu'on
             | corrige une virgule dans le descriptif.
             */
            'couverture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            'prerequis' => ['nullable', 'string', 'max:2000'],
            'objectifs' => ['nullable', 'string', 'max:2000'],
            'langue' => ['required', Rule::in(array_keys(self::LANGUES))],

            /*
             | Plafonné à 1 000, la même logique que le coût : une prime de
             | 50 000 XP ferait sauter dix paliers d'un coup et viderait la
             | progression de son sens.
             */
            'xp' => ['required', 'integer', 'min:0', 'max:1000'],

            'metaTitre' => ['nullable', 'string', 'max:180'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
            'metaMotsCles' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'titre du cours',
            'descriptif' => 'description',
            'categorieUuid' => 'catégorie',
            'niveau' => 'niveau',
            'duree' => 'durée annoncée',
            'cout' => 'coût en unités',
            'formateurUuid' => 'formateur',
        ];
    }

    /**
     * @return Collection<int, CourseCategory>
     */
    private function categories(): Collection
    {
        return CourseCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Les comptes qui peuvent porter un cours.
     *
     * Mentors et administrateurs seulement : attribuer un cours à un citoyen
     * afficherait son nom en « formateur » sur la fiche publique.
     *
     * Le guard est `web` et NON `staff`, même sur un écran d'administration.
     * `User::getDefaultGuardName()` l'explique : l'application expose deux
     * guards mais un seul jeu de rôles, tous semés sur `web`. Passer `staff`
     * ici fait lever `RoleDoesNotExist` au rendu — un écran blanc, pas un 403.
     *
     * @return Collection<int, User>
     */
    private function formateurs(): Collection
    {
        return User::query()
            ->role([UserRole::Mentor->value, UserRole::Admin->value], 'web')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);
    }

    private function formateurId(string $uuid): ?int
    {
        return $uuid === '' ? null : $this->formateurs()->firstWhere('uuid', $uuid)?->id;
    }

    /**
     * @return LengthAwarePaginator<int, Course>
     */
    private function lignes(): LengthAwarePaginator
    {
        $membre = Auth::guard('staff')->user();

        return Course::query()
            ->with(['categorie', 'instructor:id,uuid,name'])
            ->withCount(['lessons', 'enrollments'])
            /*
             | LA LISTE se borne au mentor, comme la Policy borne l'écriture.
             |
             | `CoursePolicy::update()` empêchait déjà un mentor de modifier le
             | cours d'un collègue — mais la liste, elle, les affichait tous. Il
             | voyait donc les inscrits, la note et le tarif de cours qui ne le
             | regardent pas, et découvrait le refus au clic.
             |
             | Le discriminant est `course.publish`, comme dans la Policy : le
             | mentor ne l'a pas, l'administrateur si. Le répéter ici plutôt que
             | d'appeler la Policy est délibéré — une Policy tranche sur UN
             | modèle, elle ne sait pas filtrer une requête.
             */
            ->when(
                $membre !== null
                    && $membre->hasRole(UserRole::Mentor->value)
                    && ! $membre->can('course.publish'),
                fn (Builder $q) => $q->where('instructor_id', $membre->id),
            )
            ->when($this->filtreEtat === 'archives',
                fn (Builder $q) => $q->onlyTrashed())
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('title', 'like', $terme)
                    ->orWhere('description', 'like', $terme));
            })
            ->when($this->filtreCategorie !== '', function (Builder $requete): void {
                $requete->whereHas('categorie',
                    fn (Builder $q) => $q->where('uuid', $this->filtreCategorie));
            })
            ->when($this->filtreNiveau !== '', fn (Builder $q) => $q->where('level', $this->filtreNiveau))
            ->when($this->filtreEtat === 'publies', fn (Builder $q) => $q->where('is_published', true))
            ->when($this->filtreEtat === 'brouillons', fn (Builder $q) => $q->where('is_published', false))
            /*
             | LE MENTOR NE VOIT QUE SES COURS. Le filtre est ici, dans la
             | requête : une Policy dit qui peut agir sur une ligne qu'on
             | montre, elle n'empêche pas de la lister.
             */
            ->when($this->limiteAuxSiens(), function (Builder $requete): void {
                $requete->where('instructor_id', Auth::guard('staff')->id());
            })
            ->latest('id')
            ->paginate(15);
    }

    /**
     * Le lecteur est-il un mentor sans droit global ?
     */
    private function limiteAuxSiens(): bool
    {
        $utilisateur = Auth::guard('staff')->user();

        return $utilisateur !== null
            && $utilisateur->hasRole(UserRole::Mentor->value)
            && ! $utilisateur->can('course.publish');
    }

    /**
     * @return array{total: int, publies: int, brouillons: int, inscrits: int}
     */
    private function indicateurs(): array
    {
        $base = fn (): Builder => Course::query()
            ->when($this->limiteAuxSiens(),
                fn (Builder $q) => $q->where('instructor_id', Auth::guard('staff')->id()));

        return [
            'total' => $base()->count(),
            'publies' => $base()->where('is_published', true)->count(),
            'brouillons' => $base()->where('is_published', false)->count(),
            'inscrits' => (int) $base()->withCount('enrollments')->get()->sum('enrollments_count'),
        ];
    }

    private function cours(string $uuid): Course
    {
        return Course::query()->withTrashed()->where('uuid', $uuid)->firstOrFail();
    }
}
