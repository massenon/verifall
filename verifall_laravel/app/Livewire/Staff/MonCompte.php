<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\JournalDesConnexions;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Mon compte » — la page de la personne connectée à la console.
 *
 * Trois onglets : profil, sécurité (mot de passe, double authentification),
 * historique des connexions (12 mois, voir `docs/RGPD_HISTORIQUES.md`).
 *
 * Elle ne montre JAMAIS le compte d'un autre : l'identité vient de la garde,
 * pas d'un paramètre. Changer son mot de passe exige l'actuel — une session
 * laissée ouverte ne suffit pas à s'approprier le compte.
 */
#[Layout('components.layouts.staff', ['title' => "Mon compte · Vérif'All"])]
final class MonCompte extends Component
{
    use InteractsWithToasts;

    #[Url(as: 'onglet')]
    public string $onglet = 'profil';

    public string $prenom = '';

    public string $nom = '';

    public string $telephone = '';

    public string $ville = '';

    public string $motDePasseActuel = '';

    public string $nouveauMotDePasse = '';

    public string $nouveauMotDePasse_confirmation = '';

    public function mount(): void
    {
        $membre = $this->membre();
        $this->prenom = (string) $membre->first_name;
        $this->nom = (string) $membre->last_name;
        $this->telephone = (string) $membre->phone;
        $this->ville = (string) $membre->city;

        if (! in_array($this->onglet, ['profil', 'securite', 'connexions'], true)) {
            $this->onglet = 'profil';
        }
    }

    public function enregistrerLeProfil(AuditLogService $journal): void
    {
        $donnees = $this->validate([
            'prenom' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'ville' => ['nullable', 'string', 'max:80'],
        ], attributes: ['prenom' => 'prénom', 'telephone' => 'téléphone']);

        $membre = $this->membre();
        $avant = $membre->only(['first_name', 'last_name', 'phone', 'city']);

        $membre->forceFill([
            'first_name' => $donnees['prenom'],
            'last_name' => $donnees['nom'],
            'name' => trim($donnees['prenom'].' '.$donnees['nom']),
            'phone' => $donnees['telephone'] ?: null,
            'city' => $donnees['ville'] ?: null,
        ])->save();

        $journal->record(
            action: 'account.profile_update',
            module: 'identity',
            acteur: $membre,
            avant: $avant,
            apres: $membre->only(['first_name', 'last_name', 'phone', 'city']),
            cibleLibelle: 'Mon compte',
        );

        $this->toastSuccess('Profil enregistré.');
    }

    public function changerLeMotDePasse(AuditLogService $journal): void
    {
        $this->validate([
            'motDePasseActuel' => ['required', 'string'],
            'nouveauMotDePasse' => ['required', 'confirmed:nouveauMotDePasse_confirmation', Password::defaults()],
        ], attributes: ['motDePasseActuel' => 'mot de passe actuel', 'nouveauMotDePasse' => 'nouveau mot de passe']);

        $membre = $this->membre();

        if (! Hash::check($this->motDePasseActuel, (string) $membre->password)) {
            $this->addError('motDePasseActuel', 'Le mot de passe actuel est incorrect.');

            return;
        }

        $membre->forceFill(['password' => $this->nouveauMotDePasse])->save();

        $journal->record(action: 'account.password_change', module: 'identity', acteur: $membre, cibleLibelle: 'Mon compte');

        $this->reset('motDePasseActuel', 'nouveauMotDePasse', 'nouveauMotDePasse_confirmation');
        $this->toastSuccess('Mot de passe modifié.');
    }

    public function render(JournalDesConnexions $connexions): View
    {
        $membre = $this->membre();

        return view('livewire.staff.mon-compte', [
            'membre' => $membre,
            'roles' => $membre->getRoleNames(),
            'deuxFacteurs' => $membre->two_factor_confirmed_at !== null,
            'connexions' => $this->onglet === 'connexions' ? $connexions->pour($membre, 30) : collect(),
        ]);
    }

    private function membre(): User
    {
        /** @var User $membre */
        $membre = Auth::guard('staff')->user();

        return $membre;
    }
}
