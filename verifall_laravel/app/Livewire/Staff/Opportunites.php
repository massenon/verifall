<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Enums\PorteeOpportunite;
use App\Enums\TypeOpportunite;
use App\Models\HubJob;
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
 * « Opportunités » — l'écran qui manquait pour alimenter le Hub.
 *
 * ## Pourquoi c'est le lot 2, juste après les faits vérifiés
 *
 * La couche métier existe : modèle, portées, dix natures d'opportunité,
 * fermeture automatique une semaine après l'échéance. Ce qui manquait, c'est
 * la porte d'entrée — **aucun écran ne permettait d'en créer une**. Le Hub
 * était un présentoir qu'on ne pouvait pas garnir.
 *
 * ## Le champ qui décide de tout : « vérifiée »
 *
 * `is_verified` n'est pas décoratif, et ce n'est pas non plus une case qu'on
 * coche en passant. Le Hub s'adresse à des gens qui cherchent du travail —
 * exactement le public que les fausses offres de recrutement visent, et qui
 * paie l'erreur en argent réel.
 *
 * Trois conséquences dans cet écran :
 *
 *   1. La vérification est un GESTE SÉPARÉ de l'enregistrement, comme la
 *      publication l'est pour un fait vérifié. On n'y arrive pas par accident.
 *   2. Elle est journalisée avec son auteur. Une offre frauduleuse publiée
 *      doit pouvoir être remontée à qui l'a validée.
 *   3. Modifier une offre déjà vérifiée **retire** la vérification. Le contenu
 *      a changé ; l'examen qui l'avait validé ne porte plus dessus.
 *
 * ## Ce que l'écran ne fait pas
 *
 * Il ne ferme pas les offres expirées : `FermerLesOpportunitesExpirees` s'en
 * charge chaque nuit, une semaine après l'échéance. Un bouton « fermer » ici
 * créerait un second chemin vers le même effet, et l'un des deux ne serait pas
 * testé.
 */
#[Layout('components.layouts.staff', ['title' => "Opportunités · Vérif'All"])]
final class Opportunites extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'benin' | 'international' */
    #[Url(as: 'portee', except: '')]
    public string $filtrePortee = '';

    /** '' ou une valeur de TypeOpportunite */
    #[Url(as: 'type', except: '')]
    public string $filtreType = '';

    /** '' | 'publiees' | 'brouillons' | 'a-verifier' | 'expirees' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    /** UUID de l'offre en cours d'édition ; null = création. */
    #[Locked]
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    /** UUID de l'offre dont on demande la suppression. */
    #[Locked]
    public ?string $aSupprimer = null;

    // — le formulaire
    public string $titre = '';

    public string $organisation = '';

    public string $portee = 'benin';

    public string $type = 'cdi';

    public string $lieu = '';

    public string $descriptif = '';

    public string $lienCandidature = '';

    public string $echeance = '';

    public bool $publiee = false;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltrePortee(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreType(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtrePortee', 'filtreType', 'filtreEtat');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.staff.opportunites', [
            'offres' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'portees' => PorteeOpportunite::cases(),
            'types' => TypeOpportunite::cases(),
        ]);
    }

    // ————————————————————————————————————————————————— le tiroir

    public function ouvrirCreation(): void
    {
        $this->authorize('create', HubJob::class);

        $this->reset('enEdition', 'titre', 'organisation', 'lieu', 'descriptif',
            'lienCandidature', 'echeance', 'publiee');

        $this->portee = PorteeOpportunite::Benin->value;
        $this->type = TypeOpportunite::Cdi->value;
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $offre = $this->offre($uuid);
        $this->authorize('update', $offre);

        $this->enEdition = $uuid;
        $this->titre = (string) $offre->title;
        $this->organisation = (string) $offre->organisation;
        $this->portee = $offre->scope->value;
        $this->type = $offre->type->value;
        $this->lieu = (string) $offre->location;
        $this->descriptif = (string) $offre->description;
        $this->lienCandidature = (string) $offre->apply_url;
        $this->echeance = $offre->deadline?->toDateString() ?? '';
        $this->publiee = (bool) $offre->is_published;

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

        $offre = $this->enEdition !== null ? $this->offre($this->enEdition) : new HubJob;

        $this->authorize($offre->exists ? 'update' : 'create', $offre->exists ? $offre : HubJob::class);

        $avant = $offre->exists ? $offre->only([
            'title', 'organisation', 'scope', 'type', 'location',
            'description', 'apply_url', 'deadline', 'is_published', 'is_verified',
        ]) : null;

        /*
         | Une modification RETIRE la vérification.
         |
         | Le contenu a changé ; l'examen qui l'avait validé ne porte plus
         | dessus. Garder la coche reviendrait à faire endosser à quelqu'un un
         | texte qu'il n'a pas lu — sur des offres d'emploi, dont les fausses
         | coûtent de l'argent à ceux qui y répondent.
         */
        $offre->forceFill([
            'uuid' => $offre->uuid ?? (string) Str::uuid(),
            'slug' => $offre->slug ?? Str::slug($donnees['titre']).'-'.Str::lower(Str::random(5)),
            'title' => $donnees['titre'],
            'organisation' => $donnees['organisation'],
            'scope' => $donnees['portee'],
            'type' => $donnees['type'],
            'location' => $donnees['lieu'],
            'description' => $donnees['descriptif'],
            'apply_url' => $donnees['lienCandidature'],
            'deadline' => $donnees['echeance'],
            'is_published' => $donnees['publiee'],
            'published_at' => $donnees['publiee'] ? ($offre->published_at ?? now()) : null,
            'is_verified' => false,
            'verified_by' => null,
        ])->save();

        $audit->record(
            action: $avant === null ? 'hub_job.create' : 'hub_job.update',
            module: 'hub',
            acteur: Auth::guard('staff')->user(),
            cible: $offre,
            avant: $avant,
            apres: $offre->only([
                'title', 'organisation', 'scope', 'type', 'location',
                'description', 'apply_url', 'deadline', 'is_published', 'is_verified',
            ]),
            cibleLibelle: $offre->title,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess($avant === null ? 'Opportunité créée.' : 'Opportunité mise à jour.');
    }

    /**
     * Marque une offre comme vérifiée — geste séparé, journalisé, nominatif.
     *
     * Séparé d'`enregistrer()` à dessein : la vérification engage la
     * plateforme auprès de gens qui cherchent du travail. On n'y arrive pas en
     * cliquant « Enregistrer ».
     */
    public function basculerLaVerification(string $uuid, AuditLogService $audit): void
    {
        $offre = $this->offre($uuid);
        $this->authorize('update', $offre);

        $membre = Auth::guard('staff')->user();
        $desormaisVerifiee = ! $offre->is_verified;

        $offre->forceFill([
            'is_verified' => $desormaisVerifiee,
            // L'auteur de la vérification, nominatif : une offre frauduleuse
            // publiée doit pouvoir être remontée à qui l'a validée.
            'verified_by' => $desormaisVerifiee ? $membre?->id : null,
        ])->save();

        $audit->record(
            action: $desormaisVerifiee ? 'hub_job.verify' : 'hub_job.unverify',
            module: 'hub',
            acteur: $membre,
            cible: $offre,
            avant: ['is_verified' => ! $desormaisVerifiee],
            apres: ['is_verified' => $desormaisVerifiee],
            cibleLibelle: $offre->title,
        );

        $this->toastSuccess($desormaisVerifiee
            ? 'Offre marquée comme vérifiée.'
            : 'Vérification retirée.');
    }

    public function basculerLaPublication(string $uuid, AuditLogService $audit): void
    {
        $offre = $this->offre($uuid);
        $this->authorize('update', $offre);

        $publiee = ! $offre->is_published;

        $offre->forceFill([
            'is_published' => $publiee,
            'published_at' => $publiee ? ($offre->published_at ?? now()) : null,
        ])->save();

        $audit->record(
            action: $publiee ? 'hub_job.publish' : 'hub_job.unpublish',
            module: 'hub',
            acteur: Auth::guard('staff')->user(),
            cible: $offre,
            avant: ['is_published' => ! $publiee],
            apres: ['is_published' => $publiee],
            cibleLibelle: $offre->title,
        );

        $this->toastSuccess($publiee ? 'Offre publiée.' : 'Offre retirée du Hub.');
    }

    public function demanderLaSuppression(string $uuid): void
    {
        $this->authorize('delete', $this->offre($uuid));
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

        $offre = $this->offre($this->aSupprimer);
        $this->authorize('delete', $offre);

        $audit->record(
            action: 'hub_job.delete',
            module: 'hub',
            acteur: Auth::guard('staff')->user(),
            cible: $offre,
            avant: $offre->only(['title', 'organisation', 'is_published', 'is_verified']),
            cibleLibelle: $offre->title,
        );

        $offre->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Opportunité supprimée.');
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'min:8', 'max:160'],
            'organisation' => ['required', 'string', 'max:120'],
            'portee' => ['required', Rule::enum(PorteeOpportunite::class)],
            'type' => ['required', Rule::enum(TypeOpportunite::class)],
            'lieu' => ['required', 'string', 'max:120'],
            'descriptif' => ['required', 'string', 'min:40', 'max:5000'],
            /*
             | `url` ET `starts_with:https` : une candidature se dépose sur une
             | page qui demande un nom, une adresse et souvent un document
             | d'identité. En clair, ces données passent en clair.
             */
            'lienCandidature' => ['required', 'url', 'starts_with:https://', 'max:255'],
            /*
             | Une échéance passée est refusée à la SAISIE — pas à l'affichage.
             | Publier une offre déjà close fait perdre son temps à quelqu'un
             | qui cherche du travail.
             */
            'echeance' => ['required', 'date', 'after_or_equal:today'],
            'publiee' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titre' => 'intitulé',
            'organisation' => 'organisation',
            'portee' => 'portée',
            'type' => 'nature',
            'lieu' => 'lieu',
            'descriptif' => 'descriptif',
            'lienCandidature' => 'lien de candidature',
            'echeance' => 'date limite',
        ];
    }

    private function offre(string $uuid): HubJob
    {
        return HubJob::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @return LengthAwarePaginator<int, HubJob>
     */
    private function lignes(): LengthAwarePaginator
    {
        return HubJob::query()
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $ou) => $ou
                    ->where('title', 'like', $terme)
                    ->orWhere('organisation', 'like', $terme)
                    ->orWhere('location', 'like', $terme));
            })
            ->when($this->filtrePortee !== '', fn (Builder $q) => $q->where('scope', $this->filtrePortee))
            ->when($this->filtreType !== '', fn (Builder $q) => $q->where('type', $this->filtreType))
            ->when($this->filtreEtat !== '', function (Builder $q): void {
                match ($this->filtreEtat) {
                    'publiees' => $q->where('is_published', true),
                    'brouillons' => $q->where('is_published', false),
                    // Le filtre qui sert tous les jours : ce qui est en ligne
                    // sans avoir été contrôlé.
                    'a-verifier' => $q->where('is_published', true)->where('is_verified', false),
                    'expirees' => $q->whereNotNull('deadline')->where('deadline', '<', today()),
                    default => null,
                };
            })
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * @return array{total: int, publiees: int, aVerifier: int, echeanceProche: int}
     */
    private function indicateurs(): array
    {
        $compte = HubJob::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_published = 1 then 1 else 0 end) as publiees')
            ->selectRaw('sum(case when is_published = 1 and is_verified = 0 then 1 else 0 end) as a_verifier')
            ->selectRaw('sum(case when deadline is not null and deadline between ? and ? then 1 else 0 end) as echeance_proche', [
                today()->toDateString(),
                today()->addDays(7)->toDateString(),
            ])
            ->first();

        return [
            'total' => (int) ($compte->total ?? 0),
            'publiees' => (int) ($compte->publiees ?? 0),
            /*
             | Le chiffre à regarder en premier. Une offre en ligne et non
             | vérifiée est exactement ce que le Hub promet de ne pas faire :
             | son public est celui que visent les arnaques au recrutement.
             */
            'aVerifier' => (int) ($compte->a_verifier ?? 0),
            'echeanceProche' => (int) ($compte->echeance_proche ?? 0),
        ];
    }
}
