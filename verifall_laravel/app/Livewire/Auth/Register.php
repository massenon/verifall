<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Core\Identity\Services\InscriptionCitoyen;
use App\Core\Marketing\Services\Parrainage;
use App\Enums\ConsentType;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Inscription citoyenne — stepper 4 étapes du prototype.
 *
 *   1. Identité   : prénom, nom, téléphone, profession
 *   2. Sécurité   : e-mail, mot de passe, confirmation, CONSENTEMENTS
 *   3. Vérification : code OTP à 6 chiffres reçu par courriel
 *   4. Bienvenue  : accès à l'espace citoyen
 *
 * Le compte est créé à la fin de l'étape 2 mais reste INACTIF
 * (`otp_verified_at` nul) jusqu'à la validation du code : sans cela, une
 * adresse e-mail dont on n'a pas la maîtrise pourrait servir à créer un compte.
 */
#[Layout('components.layouts.auth', ['title' => "Inscription — Verif'All"])]
final class Register extends Component
{
    use InteractsWithToasts;

    public int $etape = 1;

    // Étape 1 — identité
    public string $prenom = '';

    public string $nom = '';

    public string $telephone = '';

    public string $profession = '';

    // Étape 2 — sécurité
    public string $email = '';

    public string $motDePasse = '';

    public string $motDePasseConfirmation = '';

    /*
     | Consentements — ABSENTS du prototype, imposés par le RGPD.
     | Jamais pré-cochés : un consentement pré-coché n'est pas un consentement.
     */
    public bool $accepteCgu = false;

    public bool $acceptePrivacy = false;

    public bool $accepteNewsletter = false;

    // Étape 3 — vérification
    public string $code = '';

    /** UUID du compte en attente de vérification. */
    public ?string $compteUuid = null;

    /**
     * Le nom du parrain, retenu avant que la session ne l'oublie.
     *
     * `#[Locked]` : c'est un fait établi côté serveur, pas une saisie. Sans
     * cela, une console de navigateur pourrait faire dire à l'écran qu'on a été
     * invité par n'importe qui.
     */
    #[Locked]
    public ?string $parrainNom = null;

    /**
     * Les unités réellement créditées à la création — lues sur le compte, pas
     * sur le réglage : si le crédit a échoué, l'écran ne promet rien.
     */
    #[Locked]
    public int $bonusRecu = 0;

    /**
     * Clé de session où la page d'invitation dépose le code de parrainage.
     *
     * Partagée avec le contrôleur d'invitation : une chaîne recopiée à deux
     * endroits finirait par diverger d'une lettre, et le parrainage cesserait
     * silencieusement de fonctionner.
     */
    public const CLE_PARRAINAGE = 'parrainage.code';

    /**
     * Le parrain derrière le lien d'invitation, s'il y en a un.
     *
     * ## Ce que son absence coûtait
     *
     * Le code déposé en session par `/invitation/{code}` était consommé
     * SILENCIEUSEMENT : le formulaire ne disait ni par qui l'on avait été
     * invité, ni que des unités attendaient. Quelqu'un arrivant d'un lien
     * WhatsApp voyait une inscription ordinaire.
     *
     * C'est le maillon le plus fragile du programme de recommandation. Celui
     * qui partage promet quelque chose ; si la page d'arrivée n'en dit rien, la
     * promesse paraît fausse — et c'est l'ami qui l'a faite qui en porte le
     * doute.
     *
     * Rendu à CHAQUE affichage plutôt que mémorisé dans une propriété : la
     * lecture est bornée par la présence du code en session, donc gratuite dans
     * le cas ordinaire, et une propriété publique exposerait le parrain dans
     * l'état Livewire, où elle n'a rien à faire.
     */
    public function parrain(Parrainage $parrainage): ?User
    {
        $code = (string) Session::get(self::CLE_PARRAINAGE, '');

        return $code === '' ? null : $parrainage->parrainDuCode($code);
    }

    /** Les unités qui attendent le filleul, telles qu'elles sont réglées. */
    public function unitesFilleul(Parrainage $parrainage): int
    {
        return $parrainage->unitesFilleul();
    }

    public function render(): View
    {
        return view('livewire.auth.register', [
            'consentements' => ConsentType::cases(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'prenom' => ['required', 'string', 'min:2', 'max:60'],
            'nom' => ['required', 'string', 'min:2', 'max:60'],
            'telephone' => ['required', 'string', 'min:8', 'max:20'],
            'profession' => ['required', 'string', 'max:80'],

            'email' => ['required', 'email:rfc,dns', 'max:180', 'unique:users,email'],
            'motDePasse' => [
                'required', 'confirmed:motDePasseConfirmation',
                // 12 caractères, casse mixte, chiffre, symbole, et absence des
                // fuites connues (SECURITE_RGPD §1).
                // La politique est définie une seule fois, dans
                // AppServiceProvider : la répéter ici l'aurait fait diverger.
                Password::defaults(),
            ],

            'accepteCgu' => ['accepted'],
            'acceptePrivacy' => ['accepted'],
            'accepteNewsletter' => ['boolean'],

            'code' => ['required', 'digits:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'prenom.required' => 'Indiquez votre prénom.',
            'nom.required' => 'Indiquez votre nom.',
            'telephone.required' => 'Indiquez un numéro de téléphone.',
            'profession.required' => 'Indiquez votre profession.',
            'email.required' => 'Indiquez votre adresse e-mail.',
            'email.email' => "Cette adresse e-mail n'est pas valide.",
            'email.unique' => 'Un compte existe déjà avec cette adresse.',
            'motDePasse.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'accepteCgu.accepted' => "Vous devez accepter les conditions générales d'utilisation.",
            'acceptePrivacy.accepted' => 'Vous devez avoir lu la politique de confidentialité.',
            'code.required' => 'Saisissez le code reçu par courriel.',
            'code.digits' => 'Le code comporte 6 chiffres.',
        ];
    }

    /**
     * Étape 1 → 2. Aucune donnée n'est persistée à ce stade.
     */
    public function versEtape2(): void
    {
        $this->validate([
            'prenom' => $this->rules()['prenom'],
            'nom' => $this->rules()['nom'],
            'telephone' => $this->rules()['telephone'],
            'profession' => $this->rules()['profession'],
        ]);

        $this->etape = 2;
    }

    public function retour(): void
    {
        // Depuis l'étape 3, revenir en arrière n'a pas de sens : le compte est
        // créé et le code envoyé. On ne recule que jusqu'à l'étape 2.
        $this->etape = max(1, min($this->etape - 1, 2));
        $this->resetValidation();
    }

    /**
     * Étape 2 → 3 : création du compte inactif + envoi du code.
     */
    public function versEtape3(InscriptionCitoyen $inscriptions): void
    {
        $this->validate([
            'email' => $this->rules()['email'],
            'motDePasse' => $this->rules()['motDePasse'],
            'accepteCgu' => $this->rules()['accepteCgu'],
            'acceptePrivacy' => $this->rules()['acceptePrivacy'],
        ]);

        // Anti-abus : 3 inscriptions par heure et par IP.
        if (RateLimiter::tooManyAttempts($this->cleDebit(), 3)) {
            $this->toastWarning('Trop de tentatives. Reessayez dans quelques minutes.');

            return;
        }
        RateLimiter::hit($this->cleDebit(), 3600);

        /*
         | Parrainage. Le code a ete depose en session par la page
         | d'invitation : le porter dans l'URL de l'inscription le ferait
         | disparaitre au premier retour en arriere, et le parrain perdrait sa
         | recommandation sans que personne comprenne pourquoi.
         |
         | Tout le reste — creation, role, consentements, rattachement — vit
         | dans InscriptionCitoyen, partage avec l'API mobile.
         */
        $utilisateur = $inscriptions->creer([
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'telephone' => $this->telephone,
            'profession' => $this->profession,
            'email' => $this->email,
            'motDePasse' => $this->motDePasse,
            'newsletter' => $this->accepteNewsletter,
        ], (string) Session::get(self::CLE_PARRAINAGE, ''), request()->ip(), request()->userAgent());

        /*
         | RETENU AVANT D'OUBLIER LA SESSION.
         |
         | Le code de parrainage y vit, et il est consommé ici. L'étape 4 doit
         | pourtant encore nommer le parrain — c'est là qu'on dit au filleul ce
         | qui l'attend et grâce à qui. Sans cette copie, la mention disparaît
         | au moment précis où elle sert.
         |
         | Le NOM et pas l'objet : c'est tout ce dont la vue a besoin, et l'état
         | Livewire n'a pas à transporter un compte entier.
         */
        $this->parrainNom = $this->parrain(app(Parrainage::class))?->name;

        Session::forget(self::CLE_PARRAINAGE);

        // Le bonus est crédité dans `creer()`. On lit le solde RÉEL plutôt que
        // le réglage : si le crédit a échoué, l'écran ne promet rien.
        $this->bonusRecu = (int) $utilisateur->units;

        $this->compteUuid = $utilisateur->uuid;

        $inscriptions->envoyerLeCode($utilisateur, request()->ip());

        $this->etape = 3;
    }

    /**
     * Étape 3 → 4 : vérification du code, activation, connexion.
     */
    public function verifier(InscriptionCitoyen $inscriptions): void
    {
        $this->validate(['code' => $this->rules()['code']]);

        $utilisateur = User::where('uuid', $this->compteUuid)->first();

        if ($utilisateur === null) {
            $this->toastError('Session expiree. Reprenez votre inscription.');
            $this->etape = 1;

            return;
        }

        $resultat = $inscriptions->activer($utilisateur, $this->code);

        if (! $resultat->estValide()) {
            $this->addError('code', $resultat->message());

            return;
        }

        // Session regeneree a l'elevation de privilege (SECURITE_LOGIN).
        Auth::login($utilisateur, remember: true);
        // Facade plutot que request()->session() : celle-ci n'est pas garantie
        // presente hors d'un cycle HTTP complet.
        Session::regenerate();

        /*
         | LE DRAPEAU DE PREMIÈRE VÉRIFICATION.
         |
         | Il est posé APRÈS `Session::regenerate()`, et l'ordre n'est pas
         | indifférent : régénérer la session après l'avoir écrit perdrait la
         | valeur, et la modale ne s'ouvrirait jamais.
         |
         | Une clé de session plutôt qu'une colonne : c'est une invitation à
         | faire un premier essai, pas un fait à conserver. Si elle se perd —
         | onglet fermé, session expirée — on n'a rien perdu d'important, alors
         | qu'une colonne de plus dans `users` serait à porter pour toujours.
         */
        Session::put('verifall.premiere_verification', true);

        $this->etape = 4;
    }

    /**
     * Renvoi du code — bouton « Vous n'avez pas recu le code ? ».
     */
    public function renvoyer(InscriptionCitoyen $inscriptions): void
    {
        $utilisateur = User::where('uuid', $this->compteUuid)->first();

        if ($utilisateur === null) {
            /*
             | Il sortait ici SANS RIEN DIRE. Le bouton « Renvoyer le code »
             | devenait inerte : on cliquait, la page ne bougeait pas, et rien
             | n'indiquait qu'il fallait reprendre l'inscription. Le même
             | diagnostic dit pourtant déjà cette phrase quelques lignes plus
             | haut, dans `verifier()`.
             */
            $this->toastError('Session expiree. Reprenez votre inscription.');
            $this->etape = 1;

            return;
        }

        $cle = 'otp-resend:'.$utilisateur->uuid;

        // 3 envois par 10 minutes (SECURITE_LOGIN) : sans plafond, le bouton
        // devient un outil d'envoi massif de courriels.
        if (RateLimiter::tooManyAttempts($cle, 3)) {
            $this->toastWarning('Vous avez demande trop de codes. Patientez quelques minutes.');

            return;
        }
        RateLimiter::hit($cle, 600);

        $inscriptions->envoyerLeCode($utilisateur, request()->ip());
        /*
         | « Le precedent ne marche plus » est la moitie utile du message.
         |
         | `OtpService::issue()` invalide l'ancien code — c'est voulu, deux codes
         | valides en parallele doubleraient la surface d'attaque. Mais rien ne
         | le disait : qui cliquait « Renvoyer » puis saisissait le premier code
         | recu lisait « Code incorrect » sur un code parfaitement recopie, et
         | concluait que la verification est cassee.
         */
        $this->toastSuccess("Un nouveau code vous a ete envoye. Le precedent n'est plus valable.");
    }

    private function cleDebit(): string
    {
        return 'register:'.(request()->ip() ?? 'inconnu');
    }
}
