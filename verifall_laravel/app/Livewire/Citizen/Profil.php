<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Identity\Services\OtpService;
use App\Core\Identity\Services\ProfilService;
use App\Enums\OtpPurpose;
use App\Mail\OtpCodeMail;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Mon profil » — page profil.html du prototype.
 *
 * Le formulaire de la maquette met l'adresse e-mail au milieu des autres
 * champs, comme s'il s'agissait d'une information parmi d'autres. Elle est ici
 * traitée à part : c'est l'identifiant de connexion ET le canal de
 * récupération du mot de passe. Sa modification passe donc par un code envoyé
 * à la NOUVELLE adresse — sans quoi une session laissée ouverte suffirait à
 * s'emparer d'un compte, en deux clics et sans mot de passe.
 */
#[Layout('components.layouts.citizen', ['title' => "Mon profil · Vérif'All"])]
final class Profil extends Component
{
    use InteractsWithToasts;

    public string $prenom = '';

    public string $nom = '';

    public string $telephone = '';

    public string $ville = '';

    public string $bio = '';

    /** Adresse demandée, en attente de vérification. */
    public string $nouvelEmail = '';

    public string $codeEmail = '';

    /** Le formulaire de changement d'adresse est-il déployé ? */
    public bool $changementEmail = false;

    /** Le code a-t-il été envoyé ? */
    public bool $codeEnvoye = false;

    /** Clé de session portant l'adresse en attente — jamais le code. */
    private const CLE_ATTENTE = 'profil.email.attente';

    public function mount(): void
    {
        $utilisateur = $this->utilisateur();

        $this->prenom = (string) $utilisateur->first_name;
        $this->nom = (string) $utilisateur->last_name;
        $this->telephone = (string) $utilisateur->phone;
        $this->ville = (string) $utilisateur->city;
        $this->bio = (string) $utilisateur->bio;
    }

    public function render(): View
    {
        return view('livewire.citizen.profil', [
            'utilisateur' => $this->utilisateur(),
            'villes' => config('verifall.villes'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    private function regles(): array
    {
        return [
            'prenom' => ['required', 'string', 'min:2', 'max:60'],
            'nom' => ['required', 'string', 'min:2', 'max:60'],

            // Format béninois ou international. Facultatif : l'exiger
            // empêcherait de corriger son nom faute d'avoir un numéro.
            'telephone' => ['nullable', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'],

            // Liste fermée : une ville libre ouvrirait un champ de texte
            // réaffiché tel quel sur le forum.
            'ville' => ['nullable', Rule::in(config('verifall.villes'))],

            // 240 caractères, comme le compteur du prototype.
            'bio' => ['nullable', 'string', 'max:240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'prenom.required' => 'Indiquez votre prénom.',
            'nom.required' => 'Indiquez votre nom.',
            'telephone.regex' => 'Ce numéro ne ressemble pas à un numéro de téléphone.',
            'bio.max' => 'Votre bio dépasse 240 caractères.',
            'nouvelEmail.unique' => 'Cette adresse est déjà utilisée.',
            'codeEmail.required' => 'Saisissez le code reçu.',
        ];
    }

    public function enregistrer(ProfilService $profils): void
    {
        $donnees = $this->validate($this->regles(), $this->messages());

        $profils->mettreAJour($this->utilisateur(), [
            'first_name' => $donnees['prenom'],
            'last_name' => $donnees['nom'],
            'phone' => $donnees['telephone'] !== '' ? $donnees['telephone'] : null,
            'city' => $donnees['ville'] !== '' ? $donnees['ville'] : null,
            'bio' => $donnees['bio'] !== '' ? $donnees['bio'] : null,
        ]);

        $this->toastSuccess('Profil enregistré. Vos informations sont à jour.');
    }

    public function ouvrirChangementEmail(): void
    {
        $this->changementEmail = true;
    }

    public function annulerChangementEmail(): void
    {
        $this->reset(['nouvelEmail', 'codeEmail', 'changementEmail', 'codeEnvoye']);
        Session::forget(self::CLE_ATTENTE);
    }

    /**
     * Envoie un code à la NOUVELLE adresse.
     *
     * L'adresse demandée est retenue en SESSION et non dans une propriété
     * Livewire : celles-ci font l'aller-retour par le navigateur à chaque
     * requête, et une valeur qui décide d'un changement de compte ne doit pas
     * être modifiable par le client entre l'envoi du code et sa validation.
     */
    public function envoyerCodeEmail(OtpService $otp): void
    {
        $this->validate([
            'nouvelEmail' => ['required', 'email:rfc,dns', 'max:180',
                Rule::unique('users', 'email')->ignore($this->utilisateur()->id)],
        ], $this->messages());

        $adresse = mb_strtolower(trim($this->nouvelEmail));

        Session::put(self::CLE_ATTENTE, $adresse);

        $code = $otp->issue($adresse, OtpPurpose::Registration, request()->ip());

        // Mise en file : l'écran ne doit pas attendre le serveur SMTP.
        Mail::to($adresse)->queue(new OtpCodeMail($code, OtpPurpose::Registration));

        $this->codeEnvoye = true;
        $this->toastInfo('Un code à 6 chiffres vient d\'être envoyé à '.$adresse.'.');
    }

    public function confirmerEmail(OtpService $otp, ProfilService $profils): void
    {
        $this->validate(['codeEmail' => ['required', 'digits:6']], $this->messages());

        $adresse = Session::get(self::CLE_ATTENTE);

        if (! is_string($adresse) || $adresse === '') {
            $this->annulerChangementEmail();
            $this->toastError('La demande a expiré. Recommencez.');

            return;
        }

        $resultat = $otp->verify($adresse, OtpPurpose::Registration, $this->codeEmail);

        if (! $resultat->estValide()) {
            $this->addError('codeEmail', $resultat->message());

            return;
        }

        $profils->changerEmail($this->utilisateur(), $adresse);

        $this->annulerChangementEmail();
        $this->toastSuccess('Adresse e-mail mise à jour.');
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
