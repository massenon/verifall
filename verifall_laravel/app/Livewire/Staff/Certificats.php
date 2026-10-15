<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\Certificate;
use App\Models\Course;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Certificats » — les attestations délivrées, et leur révocation.
 *
 * ## Aucune suppression, et c'est tout le dispositif
 *
 * La matrice déclare `certificate => ['view', 'issue', 'revoke']`. Pas de
 * `delete`, et l'écran n'en offre pas.
 *
 * Chaque certificat porte un code que n'importe qui contrôle sur
 * `/verifier-un-certificat`. Effacer une ligne rendrait ce contrôle
 * NÉGATIF : le porteur d'un certificat authentique passerait pour un
 * fraudeur, sans recours, et sans trace de ce qui s'est passé.
 *
 * La révocation est la réponse. Elle est motivée, datée, et le motif est
 * affiché sur la page publique — les CGU §7 s'y engagent par écrit :
 * « la plateforme peut révoquer un certificat obtenu par fraude ; la
 * révocation est motivée et visible sur la page de contrôle ».
 *
 * ## Une révocation se défait
 *
 * Un certificat révoqué par erreur doit pouvoir revenir. Le rétablissement est
 * lui aussi journalisé : sur un document opposable, on doit pouvoir raconter
 * l'aller ET le retour.
 *
 * ## Pas de délivrance manuelle depuis cet écran
 *
 * `certificate.issue` existe, mais la délivrance est le fait de
 * `DelivranceCertificat`, à l'achèvement réel d'un parcours. Un bouton
 * « délivrer » ici court-circuiterait les six niveaux de preuve que le
 * certificat affiche — progression, cours terminé, compétences — et
 * produirait une attestation que rien n'adosse.
 */
#[Layout('components.layouts.staff', ['title' => "Certificats · Vérif'All"])]
final class Certificats extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'cours', except: '')]
    public string $filtreCours = '';

    /** '' | 'valides' | 'revoques' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    #[Locked]
    public ?string $aRevoquer = null;

    public string $motifDeRevocation = '';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreCours(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreCours', 'filtreEtat');
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Certificate::class);
    }

    public function render(): View
    {
        return view('livewire.staff.certificats', [
            'certificats' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'cours' => $this->cours(),
            'certificatVise' => $this->aRevoquer !== null ? $this->certificat($this->aRevoquer) : null,
        ]);
    }

    // ————————————————————————————————————————————————— révocation

    public function demanderLaRevocation(string $uuid): void
    {
        $this->authorize('revoke', $this->certificat($uuid));

        $this->aRevoquer = $uuid;
        $this->motifDeRevocation = '';
        $this->resetValidation();
    }

    public function annulerLaRevocation(): void
    {
        $this->aRevoquer = null;
        $this->motifDeRevocation = '';
        $this->resetValidation();
    }

    public function revoquer(AuditLogService $audit): void
    {
        if ($this->aRevoquer === null) {
            return;
        }

        $certificat = $this->certificat($this->aRevoquer);
        $this->authorize('revoke', $certificat);

        /*
         | Le motif est OBLIGATOIRE, et il est PUBLIC : la page de contrôle
         | l'affiche. Un certificat annulé sans raison lisible laisse son
         | porteur sans moyen de contester — et la plateforme sans moyen
         | d'expliquer sa décision à un employeur qui vérifie.
         */
        $this->validate([
            'motifDeRevocation' => ['required', 'string', 'min:10', 'max:255'],
        ], attributes: ['motifDeRevocation' => 'motif']);

        $certificat->forceFill([
            'revoked_at' => now(),
            'revoked_reason' => $this->motifDeRevocation,
        ])->save();

        $audit->record(
            action: 'certificate.revoke',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $certificat,
            avant: ['revoked_at' => null],
            apres: ['revoked_at' => $certificat->revoked_at?->toIso8601String(), 'motif' => $this->motifDeRevocation],
            cibleLibelle: $certificat->code.' — '.$certificat->holder_name,
        );

        $this->annulerLaRevocation();
        $this->toastSuccess('Certificat révoqué. La page publique de contrôle l\'indique désormais.');
    }

    /**
     * Rétablit un certificat révoqué par erreur.
     */
    public function retablir(string $uuid, AuditLogService $audit): void
    {
        $certificat = $this->certificat($uuid);
        $this->authorize('revoke', $certificat);

        if ($certificat->revoked_at === null) {
            return;
        }

        $motifPrecedent = (string) $certificat->revoked_reason;

        $certificat->forceFill(['revoked_at' => null, 'revoked_reason' => null])->save();

        $audit->record(
            action: 'certificate.restore',
            module: 'academie',
            acteur: Auth::guard('staff')->user(),
            cible: $certificat,
            // Le motif de la révocation annulée est conservé AU JOURNAL : sur un
            // document opposable, on doit pouvoir raconter l'aller et le retour.
            avant: ['revoked_reason' => $motifPrecedent],
            apres: ['revoked_at' => null],
            cibleLibelle: $certificat->code.' — '.$certificat->holder_name,
        );

        $this->toastSuccess('Certificat rétabli.');
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * @return Collection<int, Course>
     */
    private function cours(): Collection
    {
        return Course::query()->withTrashed()->orderBy('title')->get(['id', 'uuid', 'title']);
    }

    /**
     * @return LengthAwarePaginator<int, Certificate>
     */
    private function lignes(): LengthAwarePaginator
    {
        return Certificate::query()
            /*
             | `withTrashed` sur le cours : un cours archivé n'efface pas les
             | certificats qu'il a délivrés, et la ligne doit continuer de dire
             | de quel cours il s'agissait. Sans cela, la colonne serait vide
             | pour les certificats les plus anciens — ceux qu'on vérifie le
             | plus souvent.
             */
            ->with(['user:id,uuid,name,email', 'course' => fn ($q) => $q->withTrashed()])
            ->when($this->recherche !== '', function (Builder $requete): void {
                $terme = '%'.$this->recherche.'%';
                $requete->where(fn (Builder $q) => $q
                    ->where('code', 'like', $terme)
                    ->orWhere('holder_name', 'like', $terme)
                    ->orWhere('course_title', 'like', $terme));
            })
            ->when($this->filtreCours !== '', function (Builder $requete): void {
                $requete->whereHas('course', fn (Builder $q) => $q->withTrashed()->where('uuid', $this->filtreCours));
            })
            ->when($this->filtreEtat === 'valides', fn (Builder $q) => $q->whereNull('revoked_at'))
            ->when($this->filtreEtat === 'revoques', fn (Builder $q) => $q->whereNotNull('revoked_at'))
            ->latest('issued_at')
            ->paginate(15);
    }

    /**
     * @return array{total: int, valides: int, revoques: int, cours: int}
     */
    private function indicateurs(): array
    {
        return [
            'total' => Certificate::query()->count(),
            'valides' => Certificate::query()->whereNull('revoked_at')->count(),
            'revoques' => Certificate::query()->whereNotNull('revoked_at')->count(),
            'cours' => (int) Certificate::query()->distinct()->count('course_id'),
        ];
    }

    private function certificat(string $uuid): Certificate
    {
        return Certificate::query()->where('uuid', $uuid)->firstOrFail();
    }
}
