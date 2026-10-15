<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Core\Identity\Exceptions\CompteSuspendu;
use App\Core\Identity\Exceptions\CompteVerrouille;
use App\Core\Identity\Exceptions\IdentifiantsInvalides;
use App\Core\Identity\Services\AuthentificationCitoyen;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Connexion citoyenne.
 *
 * Deux principes gouvernent cet écran :
 *
 *  1. ANTI-ÉNUMÉRATION. Le message est identique que le compte existe ou non,
 *     et le temps de réponse aussi — sans quoi la page devient un oracle
 *     permettant de dresser la liste des adresses inscrites.
 *  2. VERROUILLAGE PROGRESSIF plutôt que blocage sec, pour ne pas punir la
 *     faute de frappe tout en rendant le forçage inopérant.
 *
 * Les deux vivent dans `AuthentificationCitoyen`, partagé avec l'API mobile :
 * ce composant n'ajoute que ce qui est propre au navigateur — la session et la
 * redirection selon le rôle.
 */
#[Layout('components.layouts.auth', ['title' => "Connexion — Verif'All"])]
final class Login extends Component
{
    use InteractsWithToasts;

    public string $email = '';

    public string $motDePasse = '';

    public bool $seSouvenir = false;

    public function render(): View
    {
        return view('livewire.auth.login');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:180'],
            'motDePasse' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => 'Saisissez votre adresse e-mail.',
            'email.email' => "Cette adresse e-mail n'est pas valide.",
            'motDePasse.required' => 'Saisissez votre mot de passe.',
        ];
    }

    public function seConnecter(AuthentificationCitoyen $authentification): void
    {
        $this->validate();

        $ip = (string) (request()->ip() ?? '0.0.0.0');

        try {
            $utilisateur = $authentification->authentifier($this->email, $this->motDePasse, $ip);
        } catch (CompteVerrouille $verrou) {
            throw ValidationException::withMessages([
                'email' => $this->messageVerrouillage($verrou->secondes),
            ]);
        } catch (IdentifiantsInvalides) {
            // Message strictement identique quelle que soit la cause : ne
            // jamais préciser lequel des deux champs est en cause.
            throw ValidationException::withMessages([
                'email' => 'Identifiants invalides.',
            ]);
        } catch (CompteSuspendu $suspension) {
            /*
             | Ici, et SEULEMENT ici, on nomme la cause : l'exception n'est levée
             | qu'après vérification du mot de passe, donc la personne connaît
             | déjà le compte. Elle n'apprend rien — mais elle cesse de chercher
             | une panne là où il y a une décision.
             |
             | Le motif est repris tel quel : il a été écrit pour être lu par
             | cette personne, et le paraphraser ici ferait deux versions d'une
             | même sanction.
             */
            throw ValidationException::withMessages([
                'email' => $suspension->motif !== null && trim($suspension->motif) !== ''
                    ? 'Ce compte est suspendu : '.$suspension->motif
                    : 'Ce compte est suspendu. Écrivez-nous si vous pensez qu\'il s\'agit d\'une erreur.',
            ]);
        }

        Auth::login($utilisateur, remember: $this->seSouvenir);

        // Régénération à l'élévation de privilège : neutralise la fixation de
        // session, où un attaquant impose son identifiant de session avant la
        // connexion pour en hériter ensuite.
        Session::regenerate();

        $this->redirectRoute($this->destination($utilisateur), navigate: true);
    }

    /**
     * Route d'arrivée selon le rôle.
     */
    private function destination(User $utilisateur): string
    {
        foreach (UserRole::cases() as $role) {
            if ($utilisateur->hasRole($role->value)) {
                return $role->routeApresConnexion();
            }
        }

        return UserRole::Citoyen->routeApresConnexion();
    }

    private function messageVerrouillage(int $secondes): string
    {
        $minutes = (int) ceil($secondes / 60);

        return $minutes <= 1
            ? 'Trop de tentatives. Réessayez dans une minute.'
            : "Trop de tentatives. Réessayez dans {$minutes} minutes.";
    }
}
