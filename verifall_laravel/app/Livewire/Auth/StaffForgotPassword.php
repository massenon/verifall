<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Core\Identity\Exceptions\CodeRefuse;
use App\Core\Identity\Services\ReinitialisationMotDePasse;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Mot de passe oublié — console.
 *
 * Même mécanisme que côté citoyen (code à 6 chiffres envoyé par courriel,
 * message neutre que le compte existe ou non), avec deux différences voulues :
 *
 *   - la personne n'est PAS connectée à l'issue : elle repasse par la
 *     connexion, donc par la double authentification. Réinitialiser le mot de
 *     passe ne doit jamais dispenser du second facteur ;
 *   - toutes ses sessions sont fermées par le service (console comprise).
 */
#[Layout('components.layouts.console-connexion', ['title' => "Mot de passe oublié — Vérif'All"])]
final class StaffForgotPassword extends Component
{
    public int $etape = 1;

    public string $email = '';

    public string $code = '';

    public string $motDePasse = '';

    public string $motDePasseConfirmation = '';

    public function render(): View
    {
        return view('livewire.auth.staff-forgot-password');
    }

    public function demander(ReinitialisationMotDePasse $reinitialisations): void
    {
        $this->validate(['email' => ['required', 'string', 'email', 'max:180']]);

        $reinitialisations->demander($this->email, request()->ip());

        $this->etape = 2;
    }

    public function reinitialiser(ReinitialisationMotDePasse $reinitialisations): void
    {
        $this->validate([
            'code' => ['required', 'digits:6'],
            'motDePasse' => ['required', 'confirmed:motDePasseConfirmation', Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
        ], attributes: ['motDePasse' => 'mot de passe']);

        try {
            $reinitialisations->reinitialiser($this->email, $this->code, $this->motDePasse);
        } catch (CodeRefuse $refus) {
            $this->addError('code', $refus->resultat->message());

            return;
        }

        session()->flash('mot_de_passe_change', true);
        $this->redirectRoute('staff.login');
    }
}
