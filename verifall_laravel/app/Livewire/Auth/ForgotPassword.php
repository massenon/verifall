<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Core\Identity\Exceptions\CodeRefuse;
use App\Core\Identity\Services\ReinitialisationMotDePasse;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Mot de passe oublié — demande puis réinitialisation.
 *
 * ÉCART AVEC LE PROTOTYPE : celui-ci annonce « un lien de réinitialisation ».
 * LOGIN_DECONNEXION et SECURITE_RGPD imposent le MÊME CONTRAT OTP que
 * l'inscription — code à 6 chiffres, 10 minutes, usage unique. Les documents
 * de sécurité l'emportent, et le choix se défend : un lien cliquable transite
 * dans les transferts de courriel et les historiques, là où un code se saisit
 * et se périme. Seule la formulation de l'écran est adaptée.
 *
 * Anti-énumération : la réponse est rigoureusement la même que l'adresse
 * corresponde ou non à un compte. « Si un compte existe, un code a été
 * envoyé. » Le service le garantit, y compris quand le débit est saturé.
 */
#[Layout('components.layouts.auth', ['title' => "Mot de passe oublié — Verif'All"])]
final class ForgotPassword extends Component
{
    use InteractsWithToasts;

    /** 1 = demande du code · 2 = saisie du code et du nouveau mot de passe. */
    public int $etape = 1;

    public string $email = '';

    public string $code = '';

    public string $motDePasse = '';

    public string $motDePasseConfirmation = '';

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:180'],
            'code' => ['required', 'digits:6'],
            'motDePasse' => [
                'required', 'confirmed:motDePasseConfirmation',
                Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
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
            'code.required' => 'Saisissez le code reçu par courriel.',
            'code.digits' => 'Le code comporte 6 chiffres.',
            'motDePasse.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ];
    }

    /**
     * Étape 1 : demande d'un code.
     */
    public function demander(ReinitialisationMotDePasse $reinitialisations): void
    {
        $this->validate(['email' => $this->rules()['email']]);

        $reinitialisations->demander($this->email, request()->ip());

        // Toujours l'étape 2, quoi qu'il se soit passé : bifurquer ici
        // rendrait l'écran bavard sur l'existence du compte.
        $this->etape = 2;
    }

    /**
     * Étape 2 : vérification du code et changement de mot de passe.
     */
    public function reinitialiser(ReinitialisationMotDePasse $reinitialisations): void
    {
        $this->validate([
            'code' => $this->rules()['code'],
            'motDePasse' => $this->rules()['motDePasse'],
        ]);

        try {
            $utilisateur = $reinitialisations->reinitialiser($this->email, $this->code, $this->motDePasse);
        } catch (CodeRefuse $refus) {
            $this->addError('code', $refus->resultat->message());

            return;
        }

        Auth::login($utilisateur);
        Session::regenerate();

        $this->toastSuccess('Mot de passe modifié. Vos autres sessions ont été fermées.');
        $this->redirectRoute('citizen.dashboard', navigate: true);
    }

    /**
     * Message d'attente, identique quoi qu'il arrive.
     */
    public function messageNeutre(): string
    {
        return 'Si un compte existe pour cette adresse, un code à 6 chiffres vient d\'être envoyé.';
    }
}
