<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\DossiersKyc;
use App\Enums\StatutDossierKyc;
use App\Http\Controllers\Staff\PieceKycController;
use App\Models\KycSubmission;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * « Vérification KYC » — la file d'examen des dossiers d'identité.
 *
 * ## Ce que cet écran était, et ce qu'il est devenu
 *
 * Au lot 7, il ne savait que **lever ou retirer un drapeau** : la colonne
 * `users.kyc_verified_at` était la seule chose qui existait, et aucun citoyen
 * ne pouvait rien déposer. L'écran l'annonçait honnêtement en tête, comme une
 * demi-mesure assumée, et posait la question au porteur du projet : construire
 * la chaîne, ou retirer le badge.
 *
 * **Il a tranché le 1er septembre 2026 : construire.** L'écran examine
 * désormais de vrais dossiers.
 *
 * ## Le drapeau ne se lève plus À LA MAIN
 *
 * Approuver passe obligatoirement par un dossier. Garder un bouton « vérifier »
 * hors dossier rouvrirait exactement le trou qu'on vient de fermer : une
 * identité attestée sans que personne ne puisse dire sur quoi.
 *
 * **Le RETRAIT, lui, reste possible sans dossier** — et c'est nécessaire : une
 * vérification obtenue par fraude doit pouvoir tomber tout de suite, sans
 * attendre qu'un dossier existe. Il est journalisé et exige une justification.
 *
 * ## Les pièces ne s'affichent pas dans la page
 *
 * Chaque image passe par une **URL signée à durée limitée**, servie par
 * {@see PieceKycController} derrière quatre gardes.
 * Une balise `<img src>` sur un chemin de stockage exposerait des scans de
 * cartes d'identité à quiconque devine une adresse.
 */
#[Layout('components.layouts.staff', ['title' => "Vérification KYC · Vérif'All"])]
final class VerificationKyc extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    /** Dix minutes : le temps d'examiner un dossier, pas de le partager. */
    private const VALIDITE_LIEN_MINUTES = 10;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'pending' | 'approved' | 'rejected' */
    #[Url(as: 'etat', except: 'pending')]
    public string $filtreEtat = 'pending';

    #[Locked]
    public ?string $dossierVise = null;

    /** 'approuver' | 'refuser' | 'retirer' */
    #[Locked]
    public string $geste = 'approuver';

    public string $motif = '';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    /**
     * Ouvre le tiroir d'examen sur un dossier.
     *
     * La garde est ici AUSSI, et pas seulement sur `statuer()` : `kyc.view`
     * ouvre la page, `kyc.review` seul donne accès au dossier. Ouvrir le tiroir
     * met sous les yeux le nom, l'adresse et les liens vers les pièces — c'est
     * déjà la décision, pas sa lecture.
     */
    public function ouvrirLaDecision(string $uuid, string $geste): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('kyc.review') ?? false, 403);

        $this->dossierVise = $uuid;
        $this->geste = in_array($geste, ['approuver', 'refuser', 'retirer'], true) ? $geste : 'approuver';
        $this->motif = '';
        $this->resetValidation();
    }

    public function fermerLaDecision(): void
    {
        $this->dossierVise = null;
        $this->motif = '';
        $this->resetValidation();
    }

    /**
     * Tranche le dossier visé.
     *
     * La garde est ici ET dans la vue : la vue cache les boutons, mais un appel
     * Livewire se forge sans passer par elle.
     */
    public function statuer(string $geste, DossiersKyc $dossiers): void
    {
        /*
         | LE GESTE EST UN ARGUMENT, et ce n'est pas un détail de style.
         |
         | Les trois boutons étaient `type="submit"` dans un `wire:submit`, avec
         | un `wire:click="$set('geste', …)"` pour dire lequel. Les deux
         | mécanismes se marchent dessus : Livewire traite le `$set`, et la
         | soumission n'atteint jamais `statuer()`. **Rien ne se passait, sans
         | la moindre erreur** — trouvé au navigateur le 1er septembre 2026.
         |
         | Les tests ne pouvaient pas le voir : ils appellent `statuer()`
         | directement et ne touchent jamais aux boutons.
         */
        if (! in_array($geste, ['approuver', 'refuser', 'retirer'], true)) {
            return;
        }

        $this->geste = $geste;

        $examinateur = Auth::guard('staff')->user();

        if ($examinateur === null || $examinateur->cannot('kyc.review')) {
            $this->toastError("Vous n'avez pas le droit d'examiner un dossier.");

            return;
        }

        $dossier = KycSubmission::query()->where('uuid', $this->dossierVise)->first();

        if ($dossier === null) {
            $this->toastError('Ce dossier n’existe plus.');
            $this->fermerLaDecision();

            return;
        }

        try {
            match ($geste) {
                'approuver' => $dossiers->approuver($dossier, $examinateur),
                'refuser' => $dossiers->refuser($dossier, $examinateur, $this->motif),
                // Pas de `default` : le garde en tête de méthode a déjà écarté
                // tout autre geste, et une branche inatteignable ferait croire
                // qu'un quatrième cas existe.
                'retirer' => $this->retirerLaVerification($dossier, $examinateur),
            };
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        $this->fermerLaDecision();

        $this->toastSuccess(match ($geste) {
            'approuver' => 'Identité vérifiée. La personne est prévenue.',
            'refuser' => 'Dossier refusé, motif transmis à la personne.',
            default => 'Vérification retirée.',
        });
    }

    public function render(DossiersKyc $dossiers): View
    {
        $membre = Auth::guard('staff')->user();

        return view('livewire.staff.verification-kyc', [
            'dossiers' => $this->lignes(),
            'indicateurs' => $this->indicateurs(),
            'peutExaminer' => $membre?->can('kyc.review') ?? false,
            'retentionJours' => DossiersKyc::RETENTION_JOURS,
            'vise' => $this->dossierVise === null
                ? null
                : KycSubmission::query()->with('citoyen')->where('uuid', $this->dossierVise)->first(),
        ]);
    }

    /**
     * Une URL signée, valable dix minutes, pour UNE pièce.
     *
     * Appelée depuis la vue à chaque rendu : le lien se renouvelle donc, et un
     * lien copié dans un historique cesse de fonctionner. C'est exactement ce
     * qu'on veut d'un lien vers un scan de carte d'identité.
     */
    public function lienDeLaPiece(KycSubmission $dossier, string $piece): string
    {
        /*
         | `url()` et non la façade. PHP ne distingue pas la casse des noms de
         | classe : importer `Illuminate\Support\Facades\URL` entre en collision
         | avec l'attribut `Livewire\Attributes\Url` utilisé plus haut, et le
         | fichier ne se charge plus du tout. L'assistant évite l'import, donc
         | la collision.
         */
        return url()->temporarySignedRoute(
            'staff.kyc.piece',
            now()->addMinutes(self::VALIDITE_LIEN_MINUTES),
            ['dossier' => $dossier->uuid, 'piece' => $piece],
        );
    }

    /**
     * Retire une vérification déjà accordée.
     *
     * Sans dossier : une vérification obtenue par fraude doit pouvoir tomber
     * tout de suite. Le motif est obligatoire — c'est la seule trace de ce qui
     * a motivé le retrait.
     *
     * @throws RuntimeException
     */
    private function retirerLaVerification(KycSubmission $dossier, User $examinateur): void
    {
        $motif = trim($this->motif);

        if (mb_strlen($motif) < 10) {
            throw new RuntimeException('Justifiez le retrait : c’est la seule trace de la décision.');
        }

        $dossier->citoyen?->forceFill(['kyc_verified_at' => null])->save();

        $dossier->forceFill([
            'status' => StatutDossierKyc::Refuse->value,
            'rejection_reason' => $motif,
            'reviewed_by' => $examinateur->getKey(),
            'reviewed_at' => now(),
        ])->save();

        app(AuditLogService::class)->record(
            action: 'kyc.retrait',
            module: 'identity',
            acteur: $examinateur,
            cible: $dossier,
            apres: ['motif' => $motif],
        );
    }

    /**
     * @return LengthAwarePaginator<int, KycSubmission>
     */
    private function lignes(): LengthAwarePaginator
    {
        return KycSubmission::query()
            ->with('citoyen:id,uuid,name,email,kyc_verified_at')
            ->when($this->filtreEtat !== '', fn (Builder $q) => $q->where('status', $this->filtreEtat))
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.$this->recherche.'%';
                // La recherche porte sur la PERSONNE, jamais sur le numéro de
                // pièce : il n'existe que sous forme de haché, et le rendre
                // cherchable supposerait de le conserver en clair.
                $q->whereHas('citoyen', fn (Builder $u) => $u
                    ->where('name', 'like', $terme)
                    ->orWhere('email', 'like', $terme));
            })
            ->oldest('created_at')
            ->paginate(15);
    }

    /**
     * @return array{en_attente: int, approuves: int, refuses: int, purges: int}
     */
    private function indicateurs(): array
    {
        return [
            'en_attente' => KycSubmission::query()->enAttente()->count(),
            'approuves' => KycSubmission::query()->where('status', StatutDossierKyc::Approuve->value)->count(),
            'refuses' => KycSubmission::query()->where('status', StatutDossierKyc::Refuse->value)->count(),
            'purges' => KycSubmission::query()->whereNotNull('purged_at')->count(),
        ];
    }
}
