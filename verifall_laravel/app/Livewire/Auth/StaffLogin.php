<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\LoginThrottleService;
use App\Core\Identity\Services\SecurityLogService;
use App\Core\Identity\Services\TwoFactorService;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Connexion au back-office — guard `staff`, 2FA TOTP OBLIGATOIRE.
 *
 * Deux étapes :
 *   1. identifiants  → aucune connexion n'est encore établie ;
 *   2. code TOTP     → connexion effective sur le guard `staff`.
 *
 * Le compte n'est JAMAIS connecté après la seule étape 1, même si les
 * identifiants sont bons : c'est ce qui fait de la 2FA un second facteur et
 * non une formalité contournable en fermant l'onglet.
 *
 * L'identifiant de l'utilisateur en attente transite par la session serveur,
 * jamais par une propriété publique du composant : celles-ci voyagent dans la
 * charge utile Livewire et sont modifiables côté client.
 */
#[Layout('components.layouts.console-connexion', ['title' => "Administration — Vérif'All"])]
final class StaffLogin extends Component
{
    use InteractsWithToasts;

    public string $email = '';

    public string $motDePasse = '';

    public string $code = '';

    /** true dès que les identifiants sont validés. */
    public bool $attenteCode = false;

    /**
     * true quand le compte n'a pas encore de second facteur.
     *
     * L'écran bascule alors en ENRÔLEMENT : QR code, secret en clair, codes de
     * récupération, puis un premier code à saisir pour prouver que
     * l'application a bien été configurée. Sans cette étape, un compte staff
     * neuf ne pouvait pas entrer du tout — le back-office réclamait un code
     * qu'aucun écran ne permettait d'obtenir.
     */
    #[Locked]
    public bool $enrolement = false;

    /** Codes de récupération, montrés UNE SEULE FOIS, à l'enrôlement. */
    #[Locked]
    public array $codesRecuperation = [];

    private const CLE_SESSION = 'staff.2fa.pending';

    public function render(TwoFactorService $deuxFacteurs): View
    {
        /*
         | Le secret ne s'affiche QUE si le serveur constate lui-même que ce
         | compte n'a pas encore de second facteur confirmé.
         |
         | La version précédente se fiait à `$this->enrolement`, une propriété
         | publique non verrouillée : un client qui la forçait à `true` obtenait
         | le secret TOTP DÉJÀ CONFIRMÉ du compte, et dérivait donc le second
         | facteur du premier.
         */
        $enAttente = $this->enrolementRequis($deuxFacteurs);

        return view('livewire.auth.staff-login', [
            // Le QR est rendu par le SERVEUR : un code dessiné en JavaScript
            // disparaît quand le script échoue, et on ne peut pas le
            // photographier avec le téléphone qu'on est en train de configurer.
            'qrCode' => $enAttente !== null ? $deuxFacteurs->qrCodeSvg($enAttente) : null,
            'secret' => $enAttente?->two_factor_secret,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:180'],
            'motDePasse' => ['required', 'string'],
            'code' => ['required', 'string', 'min:6', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => "Saisissez l'adresse e-mail administrateur.",
            'motDePasse.required' => 'Saisissez votre mot de passe.',
            'code.required' => 'Saisissez le code de votre application d\'authentification.',
        ];
    }

    /**
     * Étape 1 — identifiants.
     */
    public function verifierIdentifiants(
        LoginThrottleService $throttle,
        SecurityLogService $journal,
        TwoFactorService $deuxFacteurs,
        AuditLogService $audit,
    ): void {
        $this->validate([
            'email' => $this->rules()['email'],
            'motDePasse' => $this->rules()['motDePasse'],
        ]);

        $ip = (string) (request()->ip() ?? '0.0.0.0');
        $identite = mb_strtolower(trim($this->email));

        if ($throttle->estVerrouille($identite, $ip)) {
            $journal->loginVerrouille($identite, $throttle->secondesRestantes($identite, $ip));
            $this->echouer($journal, $identite, $throttle, $ip);
        }

        $utilisateur = User::where('email', $identite)->first();

        $motDePasseValide = $utilisateur !== null
            && Hash::check($this->motDePasse, $utilisateur->password);

        // Un citoyen qui connaîtrait ses identifiants ne doit pas franchir cet
        // écran : le back-office se refuse par rôle, pas seulement par mot de
        // passe. Le message reste identique dans tous les cas.
        /*
         | La suspension compte ICI comme les autres refus, sans message
         | particulier — à l'inverse de la connexion citoyenne.
         |
         | La raison tient à qui est en face. Un citoyen suspendu a besoin de
         | comprendre, sinon il prend la sanction pour une panne. Un membre du
         | personnel suspendu, lui, l'apprend par son responsable ; et l'écran
         | d'administration est justement celui où l'on refuse de distinguer les
         | causes d'échec, parce qu'il est exposé et que chaque nuance y devient
         | un indice.
         */
        if (! $motDePasseValide
            || ! $utilisateur->estActive()
            || $utilisateur->estSuspendu()
            || ! $this->estStaff($utilisateur)) {
            $this->echouer($journal, $identite, $throttle, $ip);
        }

        // Aucune connexion à ce stade : seule une intention est mémorisée,
        // côté serveur, et pour cinq minutes.
        Session::put(self::CLE_SESSION, [
            'id' => $utilisateur->id,
            'expire' => now()->addMinutes(5)->timestamp,
        ]);

        /*
         | ROLE DISPENSE DE SECOND FACTEUR : la connexion s'acheve ici.
         |
         | La dispense se lit dans la configuration, pas dans l'etat du compte
         | (voir `TwoFactorService::exigePour()`). On ne prepare aucun secret et
         | on n'affiche pas l'ecran du code : proposer un enrolement facultatif
         | a cet endroit ferait croire a une protection que personne n'a
         | demandee, et laisserait un secret a moitie pose en base.
         */
        if (! $deuxFacteurs->exigePour($utilisateur)) {
            $this->connecter($utilisateur, $throttle, $journal, $audit, $ip);

            return;
        }

        /*
         | Pas encore de second facteur : on l'enrole plutot que de refuser.
         |
         | Le secret est prepare mais PAS confirme — la 2FA ne devient
         | effective qu'apres un premier code valide, sinon une erreur de scan
         | enfermerait la personne dehors, sur un compte administrateur.
         */
        if (! $deuxFacteurs->estActive($utilisateur)) {
            $prepare = $deuxFacteurs->preparer($utilisateur);
            $this->codesRecuperation = $prepare['codes'];
            $this->enrolement = true;
        }

        $this->attenteCode = true;
        $this->motDePasse = '';
    }

    /**
     * Le compte dont les identifiants viennent d'être validés.
     *
     * Il n'est PAS connecté à ce stade : seule une intention est mémorisée en
     * session, pour cinq minutes.
     */
    /**
     * Le compte à enrôler — ou `null` s'il n'y a rien à enrôler.
     *
     * C'est LA méthode qui fait autorité sur l'affichage du secret. Elle exige
     * deux conditions indépendantes :
     *
     *   1. une intention de connexion valide en session (mot de passe franchi) ;
     *   2. l'ABSENCE de second facteur confirmé, constatée en base.
     *
     * La seconde est celle qui manquait. Sans elle, la première suffisait à
     * faire afficher le secret d'un compte déjà protégé.
     */
    private function enrolementRequis(TwoFactorService $deuxFacteurs): ?User
    {
        $utilisateur = $this->utilisateurEnAttente();

        if ($utilisateur === null || $deuxFacteurs->estActive($utilisateur)) {
            return null;
        }

        /*
         | Troisieme condition, ajoutee avec la dispense par role : un compte
         | dispense n'a rien a enroler. Sans elle, une intention restee en
         | session — un onglet ouvert avant que la dispense soit posee — ferait
         | afficher un code QR a quelqu'un dont le second facteur ne sera jamais
         | demande.
         */
        if (! $deuxFacteurs->exigePour($utilisateur)) {
            return null;
        }

        return $utilisateur;
    }

    private function utilisateurEnAttente(): ?User
    {
        $enAttente = Session::get(self::CLE_SESSION);

        if (! is_array($enAttente) || $enAttente['expire'] < now()->timestamp) {
            return null;
        }

        return User::find($enAttente['id']);
    }

    /**
     * Étape 2 — code TOTP ou code de récupération.
     */
    public function verifierCode(
        TwoFactorService $deuxFacteurs,
        LoginThrottleService $throttle,
        SecurityLogService $journal,
        AuditLogService $audit,
    ): void {
        $this->validate(['code' => $this->rules()['code']]);

        $enAttente = Session::get(self::CLE_SESSION);
        $ip = (string) (request()->ip() ?? '0.0.0.0');

        if (! is_array($enAttente) || $enAttente['expire'] < now()->timestamp) {
            Session::forget(self::CLE_SESSION);
            $this->attenteCode = false;

            throw ValidationException::withMessages([
                'code' => 'Session expirée. Recommencez la connexion.',
            ]);
        }

        $utilisateur = User::find($enAttente['id']);

        /*
         | À l'enrôlement, le premier code ne se contente pas de prouver
         | l'identité : il CONFIRME le secret. Sans cette confirmation, un
         | secret préparé mais jamais validé laisserait le compte dans un état
         | où la 2FA est à moitié posée — la porte se referme sans que personne
         | ait la clé.
         */
        /*
         | Le CHOIX de la méthode vient du serveur, jamais du drapeau.
         |
         | En branchant sur `$this->enrolement`, il suffisait de le remettre à
         | `false` et de saisir l'un des codes de récupération affichés juste
         | au-dessus pour entrer sans jamais poser `two_factor_confirmed_at` :
         | le compte restait à mot de passe seul, indéfiniment.
         */
        $valide = $utilisateur !== null && (! $deuxFacteurs->estActive($utilisateur)
            ? $deuxFacteurs->confirmer($utilisateur, $this->code)
            : $deuxFacteurs->verifier($utilisateur, $this->code));

        if (! $valide) {
            $echecs = $throttle->enregistrerEchec(mb_strtolower(trim($this->email)), $ip);
            $journal->loginEchoue(mb_strtolower(trim($this->email)), $echecs);

            throw ValidationException::withMessages([
                'code' => 'Code invalide.',
            ]);
        }

        $this->connecter($utilisateur, $throttle, $journal, $audit, $ip);
    }

    /**
     * Ce qui reste a faire une fois l'identite etablie, quel que soit le chemin.
     *
     * Deux chemins y menent desormais : le mot de passe suivi d'un code, et le
     * mot de passe seul quand le role est dispense de second facteur. Les
     * ecrire deux fois serait la garantie qu'un jour l'un des deux oublie la
     * regeneration de session ou le journal d'audit — et c'est precisement le
     * genre d'oubli qui ne se voit pas a l'ecran.
     */
    private function connecter(
        User $utilisateur,
        LoginThrottleService $throttle,
        SecurityLogService $journal,
        AuditLogService $audit,
        string $ip,
    ): void {
        Session::forget(self::CLE_SESSION);
        $throttle->reinitialiser(mb_strtolower(trim($this->email)), $ip);

        // Les codes de récupération ne sont montrés qu'une fois : ils quittent
        // la mémoire du composant dès que la connexion aboutit.
        $this->enrolement = false;
        $this->codesRecuperation = [];
        $this->attenteCode = false;
        $this->motDePasse = '';

        // Connexion sur le guard STAFF uniquement : l'espace citoyen reste
        // fermé tant qu'on ne s'y connecte pas séparément.
        Auth::guard('staff')->login($utilisateur);
        Session::regenerate();

        // L'horodatage d'activité alimente l'expiration d'inactivité à 30 min.
        Session::put('staff.last_activity', now()->timestamp);

        $utilisateur->forceFill([
            'last_login_at' => now(),
            'last_login_ip_hash' => app(IdentityHasher::class)->hash($ip),
        ])->save();

        // Connexion au back-office : journal de sécurité ET journal d'audit.
        // Le premier sert la détection d'intrusion, le second la traçabilité
        // des actes d'administration — ce ne sont pas les mêmes lecteurs.
        $journal->loginReussi($utilisateur);
        $audit->connexionStaff($utilisateur);

        $this->redirectRoute('staff.dashboard', navigate: true);
    }

    /**
     * Retour à l'étape 1.
     */
    public function annuler(): void
    {
        $this->enrolement = false;
        $this->codesRecuperation = [];

        Session::forget(self::CLE_SESSION);
        $this->reset(['code', 'motDePasse']);
        $this->attenteCode = false;
    }

    private function estStaff(User $utilisateur): bool
    {
        foreach (UserRole::cases() as $role) {
            if ($role->accedeAuBackOffice() && $utilisateur->hasRole($role->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Échec uniforme : jamais de précision sur la cause.
     */
    private function echouer(
        SecurityLogService $journal,
        string $identite,
        LoginThrottleService $throttle,
        string $ip,
    ): never {
        $echecs = $throttle->enregistrerEchec($identite, $ip);
        $journal->loginEchoue($identite, $echecs);

        throw ValidationException::withMessages([
            'email' => 'Identifiants invalides.',
        ]);
    }
}
