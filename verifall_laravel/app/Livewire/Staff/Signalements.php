<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\FactChecking\Services\AssignationDeVerification;
use App\Core\FactChecking\Services\FileDesSignalements;
use App\Enums\StatutVerification;
use App\Models\User;
use App\Models\Verification;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * « Signalements » — la file des vérifications citoyennes.
 *
 * ## Le dernier écran que la matrice promettait sans le tenir
 *
 * `signalement.publish` et `signalement.delete` étaient déclarées depuis
 * l'origine et employées **nulle part** : aucune page ne listait les
 * vérifications. Le tableau de bord en montrait les six dernières, et son lien
 * « Tout voir » menait à la base de connaissances — un autre écran, un autre
 * objet.
 *
 * ## Les plus ANCIENNES en tête
 *
 * À l'inverse de presque toutes les listes du back-office. Cet écran sert à
 * VIDER une file : trier par date décroissante enterrerait exactement ce qui
 * attend depuis le plus longtemps, c'est-à-dire ce dont quelqu'un attend encore
 * la réponse.
 *
 * ## Trois gestes, trois droits distincts
 *
 * Assigner (`signalement.assign`), verser dans la base de connaissances
 * (`signalement.publish`), retirer (`signalement.delete`). La matrice les
 * séparait déjà ; l'écran cesse de les confondre.
 *
 * Le retrait est une suppression DOUCE et exige un motif : une vérification a
 * été demandée par quelqu'un et lui a coûté une unité. La faire disparaître de
 * son historique sans raison écrite n'est pas contestable.
 */
#[Layout('components.layouts.staff', ['title' => "Signalements · Vérif'All"])]
final class Signalements extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    /** '' | 'non-assignees' | 'a-moi' */
    #[Url(as: 'file', except: '')]
    public string $filtreAssignation = '';

    /** La vérification visée par la modale de retrait. */
    #[Locked]
    public ?string $aRetirer = null;

    public string $motifDeRetrait = '';

    /** Celle dont on ouvre le sélecteur d'assignation. */
    #[Locked]
    public ?string $aAssigner = null;

    public string $destinataireUuid = '';

    public function mount(): void
    {
        abort_unless($this->acteur()->can('signalement.view'), 403);
    }

    public function render(FileDesSignalements $file, AssignationDeVerification $assignation): View
    {
        $membre = $this->acteur();

        return view('livewire.staff.signalements', [
            'lignes' => $file->file($membre, $this->recherche, $this->filtreStatut, $this->filtreAssignation),
            'chiffres' => $file->indicateurs(),
            'statuts' => StatutVerification::cases(),
            // Chargés seulement quand la modale est ouverte : la liste des
            // destinataires possibles est une requête, et elle ne sert à rien
            // tant que personne n'assigne.
            'destinataires' => $this->aAssigner !== null ? $assignation->destinatairesPossibles() : collect(),
            'peutAssigner' => $membre->can('signalement.assign'),
            'peutPublier' => $membre->can('signalement.publish'),
            'peutRetirer' => $membre->can('signalement.delete'),
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'filtreStatut', 'filtreAssignation'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiser(): void
    {
        $this->reset('recherche', 'filtreStatut', 'filtreAssignation');
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────────────── assigner

    public function ouvrirLAssignation(string $uuid): void
    {
        abort_unless($this->acteur()->can('signalement.assign'), 403);

        $this->aAssigner = $uuid;
        $this->destinataireUuid = '';
    }

    public function assigner(AssignationDeVerification $assignation): void
    {
        abort_unless($this->acteur()->can('signalement.assign'), 403);

        if ($this->aAssigner === null || $this->destinataireUuid === '') {
            return;
        }

        $verification = $this->verification($this->aAssigner);
        $destinataire = User::query()->where('uuid', $this->destinataireUuid)->first();

        if ($destinataire === null) {
            $this->toastError('Ce compte n’existe plus.');

            return;
        }

        try {
            // Le service vérifie que le destinataire détient bien
            // `signalement.verdict` : assigner à quelqu'un qui ne peut pas
            // trancher créerait une file qui n'avance jamais.
            $assignation->confier($verification, $destinataire, $this->acteur());
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        $this->fermerLesModales();
        $this->toastSuccess(sprintf('Vérification confiée à %s.', $destinataire->name));
    }

    // ─────────────────────────────────────────────────────────── publier

    public function publier(string $uuid, FileDesSignalements $file): void
    {
        abort_unless($this->acteur()->can('signalement.publish'), 403);

        try {
            $file->publier($this->verification($uuid), $this->acteur());
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        // « Versé » et non « publié » : l'entrée rejoint la base en BROUILLON,
        // et quelqu'un la relit. Le dire ici évite qu'on la croie déjà en ligne.
        $this->toastSuccess('Versé dans la base de connaissances, en brouillon. À relire avant publication.');
    }

    // ─────────────────────────────────────────────────────────── retirer

    public function ouvrirLeRetrait(string $uuid): void
    {
        abort_unless($this->acteur()->can('signalement.delete'), 403);

        $this->aRetirer = $uuid;
        $this->motifDeRetrait = '';
        $this->resetValidation();
    }

    public function fermerLesModales(): void
    {
        $this->aRetirer = null;
        $this->aAssigner = null;
        $this->motifDeRetrait = '';
        $this->destinataireUuid = '';
        $this->resetValidation();
    }

    public function retirer(FileDesSignalements $file): void
    {
        abort_unless($this->acteur()->can('signalement.delete'), 403);

        if ($this->aRetirer === null) {
            return;
        }

        $this->validate([
            'motifDeRetrait' => ['required', 'string', 'min:10', 'max:500'],
        ], attributes: ['motifDeRetrait' => 'motif']);

        try {
            $file->supprimer($this->verification($this->aRetirer), $this->acteur(), $this->motifDeRetrait);
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        $this->fermerLesModales();
        $this->toastSuccess('Vérification retirée de la file. Elle reste récupérable.');
    }

    private function verification(string $uuid): Verification
    {
        return Verification::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }
}
