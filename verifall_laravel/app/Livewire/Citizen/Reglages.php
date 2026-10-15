<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Identity\Services\AuditLogService;
use App\Core\Identity\Services\ExportDonneesService;
use App\Core\Identity\Services\OtpService;
use App\Core\Identity\Services\PreferencesService;
use App\Core\Identity\Services\SecurityLogService;
use App\Core\Identity\Services\SessionsService;
use App\Core\Identity\Services\SuppressionCompteService;
use App\Core\Identity\Services\TwoFactorService;
use App\Core\Marketing\Services\Infolettre;
use App\Enums\OtpPurpose;
use App\Enums\PreferenceNotification;
use App\Jobs\PreparerExportDonnees;
use App\Mail\OtpCodeMail;
use App\Mail\SuppressionProgrammeeMail;
use App\Models\DataExport;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Réglages » — page reglages.html du prototype.
 *
 * Les interrupteurs de la maquette basculent tous de la même façon, d'un clic.
 * Deux d'entre eux ne le peuvent pas :
 *
 *   · ACTIVER la 2FA suppose d'enrôler une application d'authentification —
 *     un clic ne peut pas produire un secret partagé ;
 *   · la DÉSACTIVER affaiblit le compte. Si un clic suffisait, une session
 *     laissée ouverte suffirait à retirer la protection puis à s'emparer du
 *     compte à loisir. Mot de passe ET code en cours sont donc exigés.
 *
 * L'interrupteur reste à sa place et garde son apparence ; c'est ce qu'il
 * déclenche qui change.
 */
#[Layout('components.layouts.citizen', ['title' => "Réglages · Vérif'All"])]
final class Reglages extends Component
{
    use InteractsWithToasts;

    // ------------------------------------------------------ mot de passe
    public string $motDePasseActuel = '';

    public string $nouveauMotDePasse = '';

    public string $confirmation = '';

    public bool $modaleMotDePasse = false;

    // --------------------------------------------------------------- 2FA
    /** Étape d'enrôlement : '', 'enrolement' ou 'desactivation'. */
    public string $etape2fa = '';

    public string $code2fa = '';

    public string $motDePasse2fa = '';

    /** Codes de récupération, affichés UNE SEULE FOIS après confirmation. */
    public array $codesRecuperation = [];

    /** Clé de session portant les codes en attente d'enrôlement. */
    private const CLE_CODES = '2fa.codes.attente';

    // ------------------------------------------------------ suppression
    /** Étape : '', 'confirmation' ou 'code'. */
    public string $etapeSuppression = '';

    public string $motSuppression = '';

    public string $motDePasseSuppression = '';

    public string $codeSuppression = '';

    /**
     * La demande a-t-elle franchi mot de passe ET mot de confirmation ?
     *
     * En SESSION et non en propriété : une propriété Livewire revient du
     * navigateur à chaque requête, et celle-ci décide si l'on peut détruire un
     * compte au seul vu d'un code.
     */
    private const CLE_SUPPRESSION = 'suppression.verifiee';

    public function render(
        PreferencesService $preferences,
        SessionsService $sessions,
        TwoFactorService $deuxFacteurs,
    ): View {
        $utilisateur = $this->utilisateur();

        return view('livewire.citizen.reglages', [
            'utilisateur' => $utilisateur,
            'preferences' => $preferences->toutes($utilisateur),
            'newsletter' => $preferences->newsletterActive($utilisateur),
            'sessions' => $sessions->pour($utilisateur, Session::getId()),
            'deuxFacteursActive' => $deuxFacteurs->estActive($utilisateur),
            'canaux' => PreferenceNotification::cases(),
            'export' => DataExport::where('user_id', $utilisateur->id)->latest('id')->first(),
        ]);
    }

    // ============================================ export de mes données

    /**
     * Demande une archive de portabilité.
     *
     * La construction part en file d'attente : rassembler et compresser les
     * données d'un compte peut prendre plusieurs secondes, et la page ne doit
     * pas attendre le disque.
     */
    public function exporterMesDonnees(ExportDonneesService $exports): void
    {
        $utilisateur = $this->utilisateur();

        $recente = DataExport::where('user_id', $utilisateur->id)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->exists();

        if ($recente) {
            // Chaque archive est une copie complète de données personnelles :
            // en produire à la chaîne multiplie les objets à protéger, et
            // charge le serveur pour rien.
            $this->toastInfo('Une demande est déjà en cours. Patientez quelques minutes.');

            return;
        }

        PreparerExportDonnees::dispatch($exports->demander($utilisateur)->id);

        $this->toastSuccess('Préparation lancée. Vous recevrez un courriel dès que l\'archive sera prête.');
    }

    // ================================================ zone de danger

    public function ouvrirSuppression(): void
    {
        $this->etapeSuppression = 'confirmation';
        $this->reset(['motSuppression', 'motDePasseSuppression', 'codeSuppression']);
        Session::forget(self::CLE_SUPPRESSION);
    }

    public function fermerSuppression(): void
    {
        $this->reset(['etapeSuppression', 'motSuppression', 'motDePasseSuppression', 'codeSuppression']);
        Session::forget(self::CLE_SUPPRESSION);
        $this->resetErrorBag();
    }

    /**
     * Premier temps : le mot du prototype ET le mot de passe.
     *
     * Taper « SUPPRIMER » prouve l'intention, pas l'identité — un écran laissé
     * déverrouillé suffirait à détruire le compte de quelqu'un d'autre. Le
     * geste du prototype est conservé, le mot de passe s'y ajoute, et un code
     * envoyé par courriel vient ensuite prouver que la boîte est bien tenue.
     */
    public function verifierSuppression(OtpService $otp, SuppressionCompteService $suppressions): void
    {
        $this->validate([
            'motSuppression' => ['required'],
            'motDePasseSuppression' => ['required'],
        ], [
            'motSuppression.required' => __('compte.suppression_mot_requis'),
            'motDePasseSuppression.required' => __('compte.suppression_mot_de_passe_requis'),
        ]);

        if (! $suppressions->confirmationValide($this->motSuppression)) {
            $this->addError('motSuppression', __('compte.suppression_mot_exact'));

            return;
        }

        $utilisateur = $this->utilisateur();

        if (! $suppressions->motDePasseCorrect($utilisateur, $this->motDePasseSuppression)) {
            $this->addError('motDePasseSuppression', 'Ce mot de passe est incorrect.');

            return;
        }

        Session::put(self::CLE_SUPPRESSION, true);

        $code = $otp->issue($utilisateur->email, OtpPurpose::SuppressionCompte, request()->ip());
        Mail::to($utilisateur->email)->queue(new OtpCodeMail($code, OtpPurpose::SuppressionCompte));

        $this->etapeSuppression = 'code';
        $this->toastInfo('Un code de confirmation vient de vous être envoyé.');
    }

    /**
     * Second temps : le code, puis fermeture immédiate du compte.
     */
    public function confirmerSuppression(OtpService $otp, SuppressionCompteService $suppressions): void
    {
        $this->validate(['codeSuppression' => ['required', 'digits:6']], [
            'codeSuppression.required' => 'Saisissez le code reçu.',
        ]);

        // Sans le premier temps, le code seul ne vaut rien : il ne prouve que
        // l'accès à la boîte mail, pas la connaissance du mot de passe.
        if (Session::get(self::CLE_SUPPRESSION) !== true) {
            $this->fermerSuppression();
            $this->toastError('La demande a expiré. Recommencez.');

            return;
        }

        $utilisateur = $this->utilisateur();

        $resultat = $otp->verify($utilisateur->email, OtpPurpose::SuppressionCompte, $this->codeSuppression);

        if (! $resultat->estValide()) {
            $this->addError('codeSuppression', $resultat->message());

            return;
        }

        $suppressions->programmer($utilisateur);

        // Le courriel part AVANT la déconnexion : c'est la seule voie de
        // retour, et il alerte la vraie personne si la demande vient d'un
        // écran resté ouvert.
        Mail::to($utilisateur->email)->queue(new SuppressionProgrammeeMail($utilisateur));

        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        $this->redirectRoute('login', navigate: false);
    }

    // ==================================================== notifications

    public function basculer(string $canal, PreferencesService $preferences): void
    {
        $preference = PreferenceNotification::tryFrom($canal);

        // Une clé inconnue vient forcément du client : on ne devine pas ce
        // qu'elle voulait dire.
        if ($preference === null) {
            return;
        }

        $utilisateur = $this->utilisateur();
        $nouvelEtat = ! $preferences->estActive($utilisateur, $preference);

        $preferences->definir($utilisateur, $preference, $nouvelEtat);

        $this->toastSuccess($preference->libelle().($nouvelEtat ? ' : activé.' : ' : désactivé.'));
    }

    public function basculerNewsletter(PreferencesService $preferences, Infolettre $infolettre): void
    {
        $utilisateur = $this->utilisateur();
        $accepte = ! $preferences->newsletterActive($utilisateur);

        $preferences->definirNewsletter(
            $utilisateur, $accepte, request()->ip(), request()->userAgent(),
        );

        /*
         | LE CONSENTEMENT NE SUFFIT PAS À ABONNER.
         |
         | `definirNewsletter()` écrit un `Consent` — la preuve RGPD que la
         | personne a dit oui. Ce n'est pas la même chose qu'être sur la liste
         | d'envoi, qui vit dans `newsletter_subscribers`.
         |
         | Tant que les deux n'étaient pas reliés, cette bascule était un
         | interrupteur qui n'allumait rien : quelqu'un l'activait, voyait le
         | toast de confirmation, et ne recevait jamais rien. Le pire cas de
         | tous, parce qu'il ne produit aucune erreur et que la personne se
         | croit abonnée.
         |
         | Pas de double opt-in ici : l'adresse est déjà vérifiée par la
         | connexion, un courriel de confirmation n'apprendrait rien de plus et
         | ferait échouer un abonnement que la personne vient de demander.
         */
        $accepte
            ? $infolettre->abonnerCompteVerifie($utilisateur, request()->ip())
            : $infolettre->desabonnerCompteVerifie($utilisateur);

        $this->toastSuccess('Lettre d\'information'.($accepte ? ' : activée.' : ' : désactivée.'));
    }

    // ==================================================== mot de passe

    public function ouvrirModaleMotDePasse(): void
    {
        $this->modaleMotDePasse = true;
    }

    public function fermerModaleMotDePasse(): void
    {
        $this->reset(['motDePasseActuel', 'nouveauMotDePasse', 'confirmation', 'modaleMotDePasse']);
        $this->resetErrorBag();
    }

    public function changerMotDePasse(
        SessionsService $sessions,
        AuditLogService $audit,
        SecurityLogService $securite,
    ): void {
        $this->validate([
            'motDePasseActuel' => ['required'],
            // Password::defaults() — 12 caractères minimum et compromission
            // vérifiée, comme à l'inscription. Le prototype annonce 8 ; la
            // règle de sécurité l'emporte, et l'étiquette de l'écran a été
            // alignée pour ne pas promettre moins que ce qui sera exigé.
            'nouveauMotDePasse' => ['required', 'confirmed:confirmation', Password::defaults()],
        ], [
            'motDePasseActuel.required' => 'Entrez votre mot de passe actuel.',
            'nouveauMotDePasse.required' => 'Choisissez un nouveau mot de passe.',
            'nouveauMotDePasse.confirmed' => 'La confirmation ne correspond pas.',
        ]);

        $utilisateur = $this->utilisateur();

        if (! Hash::check($this->motDePasseActuel, $utilisateur->password)) {
            // Journalisé : une rafale d'échecs ici, sur une session ouverte,
            // ressemble à quelqu'un qui a trouvé un écran déverrouillé.
            $securite->record(
                SecurityLogService::LOGIN_ECHOUE, 'warning', $utilisateur->email, $utilisateur,
                ['contexte' => 'changement de mot de passe'],
            );

            $this->addError('motDePasseActuel', 'Ce mot de passe est incorrect.');

            return;
        }

        $utilisateur->forceFill(['password' => $this->nouveauMotDePasse])->save();

        /*
         | Toutes les AUTRES sessions tombent. Un mot de passe que l'on change
         | parce qu'on le croit compromis ne protège de rien si les sessions
         | ouvertes avec l'ancien restent valides.
         |
         | La session courante est épargnée : déconnecter quelqu'un du geste
         | même par lequel il se protège serait absurde.
         */
        $fermees = $sessions->revoquerLesAutres($utilisateur, Session::getId());

        $audit->record(
            AuditLogService::MODIFICATION, 'security', $utilisateur,
            cibleLibelle: 'Changement de mot de passe',
        );

        $this->fermerModaleMotDePasse();

        $this->toastSuccess($fermees > 0
            ? 'Mot de passe mis à jour. '.$fermees.' autre'.($fermees > 1 ? 's' : '').' session'.($fermees > 1 ? 's ont' : ' a').' été fermée'.($fermees > 1 ? 's' : '').'.'
            : 'Mot de passe mis à jour.');
    }

    // ============================================================== 2FA

    /**
     * Prépare un secret et affiche le QR code.
     *
     * Rien n'est actif à ce stade : tant que le premier code n'a pas été
     * fourni, on n'a aucune preuve que l'application a bien enregistré le
     * secret. Activer avant cette preuve enfermerait dehors.
     */
    public function activer2fa(TwoFactorService $deuxFacteurs): void
    {
        $prepare = $deuxFacteurs->preparer($this->utilisateur());

        /*
         | Les codes attendent en SESSION, côté serveur, jusqu'à ce que
         | l'enrôlement soit confirmé. Les garder dans une propriété Livewire
         | les ferait voyager dans chaque réponse de la page dès avant que la
         | 2FA ne soit active — y compris si l'utilisateur abandonne en route.
         */
        Session::put(self::CLE_CODES, $prepare['codes']);

        $this->etape2fa = 'enrolement';
        $this->code2fa = '';
        $this->codesRecuperation = [];
    }

    public function confirmer2fa(TwoFactorService $deuxFacteurs, AuditLogService $audit): void
    {
        $this->validate(['code2fa' => ['required', 'digits:6']], [
            'code2fa.required' => 'Saisissez le code affiché par votre application.',
            'code2fa.digits' => 'Le code comporte 6 chiffres.',
        ]);

        $utilisateur = $this->utilisateur();

        if (! $deuxFacteurs->confirmer($utilisateur, $this->code2fa)) {
            $this->addError('code2fa', 'Ce code ne correspond pas. Vérifiez l\'heure de votre téléphone.');

            return;
        }

        /*
         | Les codes de récupération ne sont montrés QU'ICI. Ils sont hachés en
         | base : ni nous ni personne ne pourra les réafficher plus tard, ce
         | qui est exactement le but — mais impose de le dire clairement.
         */
        $this->codesRecuperation = Session::pull(self::CLE_CODES, []);
        $this->etape2fa = 'codes';

        $audit->record(
            AuditLogService::MODIFICATION, 'security', $utilisateur,
            cibleLibelle: 'Double authentification activée',
        );

        $this->toastSuccess('Double authentification activée.');
    }

    public function ouvrirDesactivation2fa(): void
    {
        $this->etape2fa = 'desactivation';
        $this->code2fa = '';
        $this->motDePasse2fa = '';
    }

    public function desactiver2fa(TwoFactorService $deuxFacteurs, AuditLogService $audit): void
    {
        $this->validate([
            'motDePasse2fa' => ['required'],
            'code2fa' => ['required'],
        ], [
            'motDePasse2fa.required' => 'Entrez votre mot de passe.',
            'code2fa.required' => 'Saisissez un code en cours ou un code de récupération.',
        ]);

        $utilisateur = $this->utilisateur();

        if (! $deuxFacteurs->desactiver($utilisateur, $this->motDePasse2fa, $this->code2fa)) {
            // Message unique : préciser lequel des deux est faux renseignerait
            // qui essaie.
            $this->addError('code2fa', 'Mot de passe ou code incorrect.');

            return;
        }

        // Retirer une protection est aussi sensible que l'ajouter — plus, même.
        $audit->record(
            AuditLogService::MODIFICATION, 'security', $utilisateur,
            cibleLibelle: 'Double authentification désactivée',
        );

        $this->fermer2fa();
        $this->toastWarning('Double authentification désactivée.');
    }

    public function fermer2fa(): void
    {
        Session::forget(self::CLE_CODES);
        $this->reset(['etape2fa', 'code2fa', 'motDePasse2fa', 'codesRecuperation']);
        $this->resetErrorBag();
    }

    // ========================================================= sessions

    public function fermerSession(string $sessionId, SessionsService $sessions): void
    {
        if ($sessionId === Session::getId()) {
            // Fermer la sienne depuis cette page reviendrait à se déconnecter
            // sans le vouloir : le bouton n'existe pas sur la ligne courante.
            return;
        }

        $sessions->revoquer($this->utilisateur(), $sessionId)
            ? $this->toastSuccess('Session fermée.')
            : $this->toastError('Cette session n\'existe plus.');
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
