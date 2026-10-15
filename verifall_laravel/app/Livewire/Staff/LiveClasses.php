<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Live Classes » côté administration — l'écran qui n'existait pas.
 *
 * ## Ce qu'il corrige
 *
 * L'entrée « Live Classes » du menu latéral pointait sur
 * `route('citizen.live-classes')`. Un administrateur cliquait et basculait du
 * guard `staff` au guard `web` : s'il n'avait pas de session citoyenne
 * ouverte — le cas ordinaire — il tombait sur l'écran de connexion CITOYEN,
 * depuis le back-office. Il n'y avait aucune façon de programmer une séance.
 *
 * ## Le lot 1 avait laissé une porte sans poignée
 *
 * `live_classes` a reçu ses trois colonnes de rediffusion (`replay_url`,
 * `replay_published_at`, `recording_minutes`), et l'espace citoyen sait les
 * lire — mais **rien ne permettait de les remplir**. Le bouton « Voir le
 * replay » aurait dit « rediffusion à venir » pour toujours. C'est ici que la
 * poignée se pose, et c'est la raison d'être du second bloc du formulaire.
 *
 * ## Ce que l'écran ne fait pas
 *
 * Il ne calcule pas d'état. `upcoming` · `live` · `past` se déduisent de
 * l'heure, dans le modèle, et nulle part ailleurs : une colonne d'état
 * mentirait dès le lendemain si personne ne repassait derrière.
 */
#[Layout('components.layouts.staff', ['title' => "Live Classes · Vérif'All"])]
final class LiveClasses extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'upcoming' | 'live' | 'past' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    /** '' | 'publiees' | 'brouillons' | 'avec-replay' | 'sans-replay' */
    #[Url(as: 'filtre', except: '')]
    public string $filtre = '';

    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire : la séance
    public string $titre = '';

    public string $descriptif = '';

    public string $coursId = '';

    public string $animateurId = '';

    public string $debut = '';

    public string $duree = '60';

    public string $lienVisio = '';

    public string $places = '';

    public bool $publiee = false;

    // — le formulaire : la rediffusion
    public string $lienReplay = '';

    public string $replayPublieLe = '';

    public string $dureeEnregistrement = '';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function updatingFiltre(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreEtat', 'filtre');
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', LiveClass::class);
    }

    public function render(): View
    {
        return view('livewire.staff.live-classes', [
            'seances' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'cours' => Course::query()->orderBy('title')->get(['id', 'title']),
            'animateurs' => $this->animateurs(),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', LiveClass::class);

        $this->reset('enEdition', 'titre', 'descriptif', 'coursId', 'animateurId',
            'debut', 'lienVisio', 'places', 'publiee',
            'lienReplay', 'replayPublieLe', 'dureeEnregistrement');

        $this->duree = '60';
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $seance = $this->seance($uuid);
        $this->authorize('update', $seance);

        $this->enEdition = $uuid;
        $this->titre = (string) $seance->title;
        $this->descriptif = (string) $seance->description;
        $this->coursId = (string) ($seance->course_id ?? '');
        $this->animateurId = (string) ($seance->host_id ?? '');
        // `Y-m-d\TH:i` : la forme exacte qu'attend `<input type="datetime-local">`.
        $this->debut = $seance->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->duree = (string) $seance->minutes;
        $this->lienVisio = (string) $seance->join_url;
        $this->places = (string) ($seance->seats ?? '');
        $this->publiee = (bool) $seance->is_published;

        $this->lienReplay = (string) ($seance->replay_url ?? '');
        $this->replayPublieLe = $seance->replay_published_at?->format('Y-m-d\TH:i') ?? '';
        $this->dureeEnregistrement = (string) ($seance->recording_minutes ?? '');

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

        $seance = $this->enEdition !== null ? $this->seance($this->enEdition) : new LiveClass;

        $this->authorize($seance->exists ? 'update' : 'create', $seance->exists ? $seance : LiveClass::class);

        $suivies = [
            'title', 'course_id', 'host_id', 'starts_at', 'minutes',
            'join_url', 'seats', 'is_published',
            'replay_url', 'replay_published_at', 'recording_minutes',
        ];

        $avant = $seance->exists ? $seance->only($suivies) : null;

        /*
         | UNE ADRESSE DE REDIFFUSION SANS DATE SE PUBLIE TOUT DE SUITE.
         |
         | C'est le contraire de ce que la migration voulait permettre — déposer
         | l'enregistrement le mardi, le diffuser le jeudi une fois relu — mais
         | c'est l'attente raisonnable de qui remplit le champ et n'en remplit
         | qu'un : il vient de coller un lien, il s'attend à ce qu'il serve.
         |
         | Le champ de date reste donc facultatif, et il retarde ; il ne
         | conditionne pas.
         */
        $datePublicationReplay = null;

        if ($donnees['lienReplay'] !== '') {
            $datePublicationReplay = $donnees['replayPublieLe'] !== ''
                ? $donnees['replayPublieLe']
                : ($seance->replay_published_at ?? now());
        }

        $seance->forceFill([
            'uuid' => $seance->uuid ?? (string) Str::uuid(),
            'title' => $donnees['titre'],
            'description' => $donnees['descriptif'],
            'course_id' => $donnees['coursId'] !== '' ? (int) $donnees['coursId'] : null,
            'host_id' => $donnees['animateurId'] !== '' ? (int) $donnees['animateurId'] : null,
            'starts_at' => $donnees['debut'],
            'minutes' => (int) $donnees['duree'],
            'join_url' => $donnees['lienVisio'],
            'seats' => $donnees['places'] !== '' ? (int) $donnees['places'] : null,
            'is_published' => $donnees['publiee'],
            'replay_url' => $donnees['lienReplay'] !== '' ? $donnees['lienReplay'] : null,
            'replay_published_at' => $datePublicationReplay,
            'recording_minutes' => $donnees['dureeEnregistrement'] !== ''
                ? (int) $donnees['dureeEnregistrement']
                : null,
        ])->save();

        $audit->record(
            action: $avant === null ? 'live_class.create' : 'live_class.update',
            module: 'lms',
            acteur: Auth::guard('staff')->user(),
            cible: $seance,
            avant: $avant,
            apres: $seance->only($suivies),
            cibleLibelle: $seance->title,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Séance programmée.' : 'Séance mise à jour.');
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        $seance = $this->seance($uuid);
        $this->authorize('update', $seance);

        $publiee = ! $seance->is_published;

        $seance->forceFill(['is_published' => $publiee])->save();

        $audit->record(
            action: $publiee ? 'live_class.publish' : 'live_class.unpublish',
            module: 'lms',
            acteur: Auth::guard('staff')->user(),
            cible: $seance,
            avant: ['is_published' => ! $publiee],
            apres: ['is_published' => $publiee],
            cibleLibelle: $seance->title,
        );

        $this->toastSuccess($publiee ? 'Séance publiée.' : 'Séance retirée du calendrier.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        $this->authorize('delete', $this->seance($uuid));
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

        $seance = $this->seance($this->aSupprimer);
        $this->authorize('delete', $seance);

        $audit->record(
            action: 'live_class.delete',
            module: 'lms',
            acteur: Auth::guard('staff')->user(),
            cible: $seance,
            avant: $seance->only(['title', 'starts_at', 'is_published']),
            cibleLibelle: $seance->title,
        );

        $seance->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Séance supprimée.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'min:6', 'max:160'],
            'descriptif' => ['nullable', 'string', 'max:2000'],
            'coursId' => ['nullable', 'string'],
            'animateurId' => ['nullable', 'string'],
            'debut' => ['required', 'date'],
            'duree' => ['required', 'integer', 'min:5', 'max:480'],
            /*
             | `starts_with:https` comme pour les candidatures du Hub : un lien
             | de visioconférence en clair est un lien qu'un réseau partagé
             | peut lire, et une salle où n'importe qui entre.
             */
            'lienVisio' => ['required', 'url', 'starts_with:https://', 'max:500'],
            'places' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'publiee' => ['boolean'],

            'lienReplay' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'replayPublieLe' => ['nullable', 'date'],
            'dureeEnregistrement' => ['nullable', 'integer', 'min:1', 'max:480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'titre',
            'debut' => 'date et heure',
            'duree' => 'durée',
            'lienVisio' => 'lien de visioconférence',
            'places' => 'nombre de places',
            'lienReplay' => 'lien de rediffusion',
            'replayPublieLe' => 'date de mise en ligne',
            'dureeEnregistrement' => "durée de l'enregistrement",
        ];
    }

    /**
     * @return LengthAwarePaginator<int, LiveClass>
     */
    private function lignes(): LengthAwarePaginator
    {
        return LiveClass::query()
            ->with(['course:id,title', 'host:id,name'])
            ->withCount('registrations')
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('title', 'like', $terme)
                    ->orWhere('description', 'like', $terme));
            })
            ->when($this->filtre === 'publiees', fn (Builder $q) => $q->where('is_published', true))
            ->when($this->filtre === 'brouillons', fn (Builder $q) => $q->where('is_published', false))
            ->when($this->filtre === 'avec-replay', fn (Builder $q) => $q->whereNotNull('replay_url'))
            ->when($this->filtre === 'sans-replay', fn (Builder $q) => $q->whereNull('replay_url'))
            /*
             | Le filtre d'ÉTAT ne peut pas s'écrire en SQL sans recopier la
             | règle du modèle — « passée » se calcule sur `starts_at + minutes`,
             | pas sur `starts_at` seul. Deux copies d'une même règle finissent
             | par ne plus dire la même chose, et c'est le back-office qui
             | mentirait en premier. On borne donc en SQL, grossièrement, et le
             | modèle tranche.
             */
            ->when($this->filtreEtat === 'past', fn (Builder $q) => $q->where('starts_at', '<', now()))
            ->when($this->filtreEtat === 'upcoming', fn (Builder $q) => $q->where('starts_at', '>', now()))
            ->orderByDesc('starts_at')
            ->paginate(15);
    }

    /**
     * @return array{total: int, aVenir: int, publiees: int, avecReplay: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => LiveClass::query()->count(),
            'aVenir' => LiveClass::query()->where('starts_at', '>', now())->count(),
            'publiees' => LiveClass::query()->where('is_published', true)->count(),
            /*
             | Compte les séances PASSÉES sans rediffusion : c'est le chiffre
             | qui appelle une action. « Combien en ont une » ne dit rien ;
             | « combien n'en ont pas » dit ce qu'il reste à déposer.
             */
            'avecReplay' => LiveClass::query()->whereNotNull('replay_url')->count(),
        ];
    }

    /**
     * Les comptes qui peuvent animer : personnel et mentors.
     *
     * @return Collection<int, User>
     */
    private function animateurs(): Collection
    {
        return User::query()
            ->role([UserRole::Mentor->value, UserRole::Admin->value])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function seance(string $uuid): LiveClass
    {
        return LiveClass::query()->where('uuid', $uuid)->firstOrFail();
    }
}
