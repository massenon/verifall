<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\FicheDuCompte;
use App\Core\Identity\Services\SuspensionDeCompte;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La fiche d'un compte — citoyen, mentor ou membre du personnel.
 *
 * À gauche, la carte d'identité ; à droite, un onglet DataTables par historique
 * (vérifications, cours, quiz, devoirs, journal…), chacun servi par
 * `staff.users.show.data`. Toute la lecture est dans `FicheDuCompte`.
 *
 * ## Les actions ne sont pas réécrites
 *
 * - **Suspendre / rétablir** appellent `SuspensionDeCompte`, le service qui
 *   pose l'état, ferme les sessions, journalise et prévient — exactement comme
 *   l'écran Utilisateurs, sous la même Policy (`suspend`) et la même règle de
 *   validation du motif.
 * - **Modifier** renvoie au tiroir EXISTANT de l'écran Utilisateurs
 *   (`?modifier=<uuid>`) : il porte les trois garde-fous du lot 3 (propres
 *   rôles, dernier administrateur, adresse vérifiée), qu'une copie ferait
 *   diverger au premier correctif.
 */
#[Layout('components.layouts.staff', ['title' => "Fiche du compte · Vérif'All"])]
final class FicheUtilisateur extends Component
{
    use InteractsWithToasts;

    /** Événement qui fait recharger l'onglet Journal après une écriture. */
    public const EVENEMENT_TABLE_A_RECHARGER = 'fiche-compte-modifiee';

    #[Locked]
    public string $uuid = '';

    public bool $suspensionOuverte = false;

    public bool $retablissementOuvert = false;

    public string $motifDeSuspension = '';

    public function mount(User $user): void
    {
        $this->authorize('view', $user);

        $this->uuid = (string) $user->uuid;
    }

    public function render(FicheDuCompte $fiche): View
    {
        $compte = $this->compte($fiche);
        $acteur = $this->acteur();

        return view('livewire.staff.fiche-utilisateur', [
            'compte' => $compte,
            'identite' => $fiche->identite($compte),
            'onglets' => $fiche->onglets($acteur, $compte),
            'peutModifier' => $acteur->can('update', $compte),
            'peutSuspendre' => $acteur->can('suspend', $compte),
            'peutVoirMentors' => $acteur->can('mentor.view'),
        ]);
    }

    // ————————————————————————————————————————————————— suspension

    public function ouvrirLaSuspension(FicheDuCompte $fiche): void
    {
        $this->authorize('suspend', $this->compte($fiche));

        $this->suspensionOuverte = true;
        $this->retablissementOuvert = false;
        $this->motifDeSuspension = '';
        $this->resetValidation();
    }

    public function ouvrirLeRetablissement(FicheDuCompte $fiche): void
    {
        $this->authorize('suspend', $this->compte($fiche));

        $this->retablissementOuvert = true;
        $this->suspensionOuverte = false;
    }

    public function annulerLaSuspension(): void
    {
        $this->suspensionOuverte = false;
        $this->retablissementOuvert = false;
        $this->motifDeSuspension = '';
        $this->resetValidation();
    }

    public function suspendre(SuspensionDeCompte $suspension): void
    {
        if (! $this->suspensionOuverte) {
            return;
        }

        $cible = $this->compteAEcrire();
        $this->authorize('suspend', $cible);

        // La règle de l'écran Utilisateurs, à l'identique : un motif lisible
        // est ce qui rend une sanction contestable.
        $this->validate([
            'motifDeSuspension' => ['required', 'string', 'min:10', 'max:'.SuspensionDeCompte::MOTIF_MAXIMUM],
        ], attributes: ['motifDeSuspension' => 'motif']);

        $suspension->suspendre($cible, $this->acteur(), $this->motifDeSuspension);

        $this->annulerLaSuspension();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Compte suspendu. Les sessions ouvertes ont été fermées et la personne est prévenue.');
    }

    public function retablir(SuspensionDeCompte $suspension): void
    {
        if (! $this->retablissementOuvert) {
            return;
        }

        $cible = $this->compteAEcrire();
        $this->authorize('suspend', $cible);

        $suspension->retablir($cible, $this->acteur());

        $this->annulerLaSuspension();
        $this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER);
        $this->toastSuccess('Compte rétabli. La personne retrouve ses accès et en est informée.');
    }

    // ————————————————————————————————————————————————— lectures

    private function compte(FicheDuCompte $fiche): User
    {
        $compte = $fiche->compte($this->uuid);

        abort_if($compte === null, 404);

        // Rejouée à chaque aller-retour : une habilitation retirée pendant que
        // la fiche est ouverte ne laisse pas la page continuer de répondre.
        $this->authorize('view', $compte);

        return $compte;
    }

    /**
     * Le compte ENTIER, pour une écriture.
     *
     * La carte d'identité lit un `select` partiel (sans secrets) ; le service
     * de suspension, lui, prévient la personne selon ses préférences et
     * enregistre le modèle — il reçoit la ligne complète, comme depuis l'écran
     * Utilisateurs.
     */
    private function compteAEcrire(): User
    {
        return User::query()->where('uuid', $this->uuid)->firstOrFail();
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }
}
