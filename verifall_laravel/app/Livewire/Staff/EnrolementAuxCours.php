<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Enums\StatutInscription;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
 * « Enrôlement aux Cours » — qui suit quoi, et où il en est.
 *
 * ## L'écran qui répond à la question la plus posée du support
 *
 * « Je me suis inscrit et le cours n'apparaît pas », « j'ai payé et je n'ai pas
 * accès ». Personne ne pouvait le vérifier : la table `enrollments` n'avait
 * aucun lecteur côté administration. La réponse tenait à un `tinker`.
 *
 * ## Une inscription se DONNE, elle ne se force pas
 *
 * `InscriptionAuCours::inscrire()` débite les unités quand le cours est payant.
 * Inscrire quelqu'un depuis cet écran contournerait ce débit — c'est
 * exactement ce qu'on veut pour un geste commercial ou un rattrapage
 * d'incident, et c'est pourquoi l'inscription manuelle est marquée
 * `paid_units = 0` et journalisée : on doit pouvoir distinguer plus tard un
 * accès offert d'un accès acheté.
 *
 * ## La progression ne se modifie PAS
 *
 * Elle est calculée par les leçons réellement terminées
 * (`LessonCompletion`). Un champ « progression » modifiable ici la ferait
 * mentir dès la leçon suivante, quand le calcul reprendrait la main — et
 * surtout, une progression forcée à 100 % déclencherait un certificat que rien
 * n'adosse. L'écran l'affiche, il ne l'écrit pas.
 */
#[Layout('components.layouts.staff', ['title' => "Enrôlement aux cours · Vérif'All"])]
final class EnrolementAuxCours extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cours', except: '')]
    public string $filtreCours = '';

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    public bool $formulaireOuvert = false;

    #[Locked]
    public ?string $aRetirer = null;

    // — inscription manuelle
    public string $compte = '';

    #[Locked]
    public ?string $compteTrouve = null;

    public string $coursUuid = '';

    public string $motif = '';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreCours(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreStatut(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCours', 'filtreStatut');
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('enrolment.view') ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.staff.enrolement-aux-cours', [
            'inscriptions' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'cours' => $this->cours(),
            'statuts' => StatutInscription::cases(),
            'peutInscrire' => $this->peutInscrire(),
            'peutRetirer' => $this->peutRetirer(),
        ]);
    }

    // ————————————————————————————————————————————————— inscription

    public function ouvrirInscription(): void
    {
        abort_unless($this->peutInscrire(), 403);

        $this->reset('compte', 'compteTrouve', 'coursUuid', 'motif');
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function fermerLeTiroir(): void
    {
        $this->formulaireOuvert = false;
        $this->resetValidation();
    }

    /**
     * Nomme le compte AVANT le geste — même précaution que le journal des
     * unités : deux adresses voisines se confondent, et l'erreur ne se voit
     * qu'après.
     */
    public function updatedCompte(string $valeur): void
    {
        $valeur = trim($valeur);

        $this->compteTrouve = $valeur === '' ? null : $this->chercherLeCompte($valeur)?->uuid;
    }

    public function inscrire(AuditLogService $audit): void
    {
        abort_unless($this->peutInscrire(), 403);

        $donnees = $this->validate([
            'compte' => ['required', 'string', 'max:255'],
            'coursUuid' => ['required', 'string'],
            /*
             | La justification est obligatoire parce que ce geste CONTOURNE le
             | paiement : sans elle, un accès offert ressemble à un accès acheté
             | dans six mois.
             */
            'motif' => ['required', 'string', 'min:10', 'max:255'],
        ], attributes: [
            'compte' => 'compte',
            'coursUuid' => 'cours',
            'motif' => 'justification',
        ]);

        $utilisateur = $this->chercherLeCompte(trim($donnees['compte']));
        $cours = $this->cours()->firstWhere('uuid', $donnees['coursUuid']);

        if ($utilisateur === null) {
            $this->addError('compte', 'Aucun compte ne correspond.');

            return;
        }

        if ($cours === null) {
            $this->addError('coursUuid', 'Choisissez un cours.');

            return;
        }

        $existante = Enrollment::query()
            ->where('user_id', $utilisateur->id)
            ->where('course_id', $cours->id)
            ->first();

        if ($existante !== null) {
            // Réinscrire quelqu'un remettrait sa progression à zéro sans le
            // dire. Le refus est plus utile qu'un doublon silencieux.
            $this->addError('compte', 'Ce compte est déjà inscrit à ce cours.');

            return;
        }

        $inscription = DB::transaction(fn (): Enrollment => Enrollment::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $utilisateur->id,
            'course_id' => $cours->id,
            'status' => StatutInscription::Inscrit->value,
            'progress' => 0,
            /*
             | ZÉRO unité, et c'est la trace qui compte : cet accès a été donné,
             | pas acheté. Y écrire le tarif du cours ferait apparaître un
             | encaissement qui n'a pas eu lieu dans toute mesure ultérieure.
             */
            'paid_units' => 0,
            'started_at' => now(),
        ]));

        $audit->record(
            action: 'enrolment.create',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $inscription,
            apres: [
                'user' => $utilisateur->email,
                'course' => $cours->title,
                'paid_units' => 0,
                'justification' => $donnees['motif'],
            ],
            cibleLibelle: $utilisateur->name.' → '.$cours->title,
        );

        $this->fermerLeTiroir();
        $this->toastSuccess('Accès donné à '.$utilisateur->name.'. Aucune unité n\'a été débitée.');
    }

    // ————————————————————————————————————————————————— retrait

    public function demanderLeRetrait(string $uuid): void
    {
        abort_unless($this->peutRetirer(), 403);

        $this->aRetirer = $uuid;
    }

    public function annulerLeRetrait(): void
    {
        $this->aRetirer = null;
    }

    public function retirer(AuditLogService $audit): void
    {
        if ($this->aRetirer === null) {
            return;
        }

        abort_unless($this->peutRetirer(), 403);

        $inscription = $this->inscription($this->aRetirer);

        $audit->record(
            action: 'enrolment.delete',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $inscription,
            avant: [
                'user' => $inscription->user?->email,
                'course' => $inscription->course?->title,
                'progress' => $inscription->progress,
                'paid_units' => $inscription->paid_units,
            ],
            cibleLibelle: ($inscription->user?->name ?? '?').' → '.($inscription->course?->title ?? '?'),
        );

        $inscription->delete();

        $this->aRetirer = null;

        /*
         | Le message dit ce qui N'EST PAS fait : aucun remboursement. Le retrait
         | et le recréditement sont deux gestes, et les confondre ferait croire
         | qu'un citoyen a été remboursé alors que non.
         */
        $this->toastSuccess('Inscription retirée. Aucune unité n\'a été recréditée — passez par le journal des unités pour cela.');
    }

    // ————————————————————————————————————————————————— lectures

    private function peutInscrire(): bool
    {
        return Auth::guard('staff')->user()?->can('enrolment.create') ?? false;
    }

    private function peutRetirer(): bool
    {
        return Auth::guard('staff')->user()?->can('enrolment.delete') ?? false;
    }

    private function chercherLeCompte(string $valeur): ?User
    {
        return User::query()
            ->where('email', $valeur)
            ->orWhere('uuid', $valeur)
            ->first();
    }

    /**
     * @return Collection<int, Course>
     */
    private function cours(): Collection
    {
        return Course::query()->orderBy('title')->get(['id', 'uuid', 'title', 'cost_units']);
    }

    /**
     * @return LengthAwarePaginator<int, Enrollment>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Enrollment::query()
            ->with(['user:id,uuid,name,email', 'course:id,uuid,title,cost_units'])
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->whereHas('user', fn (Builder $q) => $q
                    ->where('name', 'like', $terme)
                    ->orWhere('email', 'like', $terme));
            })
            ->when($this->filtreCours !== '', function (Builder $requete): void {
                $requete->whereHas('course', fn (Builder $q) => $q->where('uuid', $this->filtreCours));
            })
            ->when($this->filtreStatut !== '', fn (Builder $q) => $q->where('status', $this->filtreStatut))
            ->latest('id')
            ->paginate(20);
    }

    /**
     * @return array{total: int, en_cours: int, termines: int, offerts: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => Enrollment::query()->count(),
            'en_cours' => Enrollment::query()->where('status', StatutInscription::EnCours->value)->count(),
            'termines' => Enrollment::query()->where('status', StatutInscription::Termine->value)->count(),
            /*
             | Les accès DONNÉS : inscription à zéro unité sur un cours payant.
             | Personne d'autre ne les compte, et c'est précisément le chiffre
             | qu'on cherche quand les recettes ne tombent pas juste.
             */
            'offerts' => Enrollment::query()
                ->where('paid_units', 0)
                ->whereHas('course', fn (Builder $q) => $q->where('cost_units', '>', 0))
                ->count(),
        ];
    }

    private function inscription(string $uuid): Enrollment
    {
        return Enrollment::query()->with(['user', 'course'])->where('uuid', $uuid)->firstOrFail();
    }
}
