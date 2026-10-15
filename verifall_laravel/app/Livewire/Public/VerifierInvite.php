<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\TypeContenu;
use App\Jobs\AnalyserVerification;
use App\Models\Verification;
use App\Support\Hashing\IdentityHasher;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Vérifiez avant de partager » — page publique verifier-une-information.html.
 *
 * Une vérification sans compte : c'est la porte d'entrée du produit. Quelqu'un
 * qui reçoit une rumeur sur WhatsApp doit pouvoir la soumettre en dix secondes,
 * sans formulaire d'inscription — sinon il partagera sans vérifier.
 *
 * Trois différences avec l'espace citoyen, toutes assumées :
 *
 *   · DEUX vérifications par adresse, pas plus. Sans compte, rien ne borne la
 *     dépense : le quota tient lieu de garde-fou, et il porte sur des services
 *     tiers facturés à l'appel.
 *   · TEXTE et LIEN seulement. Un média coûte cinq unités et mobilise des
 *     moteurs lourds : ce n'est pas ce qu'on offre à un visiteur anonyme.
 *   · Le verdict et la synthèse sont montrés ; le rapport détaillé — moteurs,
 *     sources, indice de haine — demande un compte. On ne cache pas la
 *     RÉPONSE, seulement le dossier.
 *
 * L'adresse IP n'est jamais stockée en clair : la clé de quota porte sur son
 * empreinte, comme partout ailleurs dans l'application.
 */
#[Layout('components.layouts.public', ['title' => "Vérifier une information · Vérif'All"])]
final class VerifierInvite extends Component
{
    use InteractsWithToasts;

    public string $onglet = 'url';

    public string $lien = '';

    public string $texte = '';

    /** Vérification en cours ou terminée, le temps de la visite. */
    public ?string $uuid = null;

    /** Vérifications offertes par adresse, sur 24 heures. */
    public const QUOTA_INVITE = 2;

    /** Interrogations sans résultat, pour repérer un blocage. */
    public int $attentes = 0;

    /** Au-delà, on cesse de laisser croire que ça avance. */
    private const ATTENTES_AVANT_ALERTE = 45;

    /** Clé de session où l'on retient l'analyse en cours du visiteur. */
    private const CLE_SESSION = 'verif-invite.analyse';

    /**
     * Retrouve l'analyse laissée en plan.
     *
     * Quand les moteurs sont saturés, on demande au visiteur de revenir dans
     * une dizaine de minutes — promesse creuse s'il retombe sur un formulaire
     * vide. La demande est retenue en SESSION et non sur l'empreinte de
     * l'adresse : derrière un même NAT, deux visiteurs partagent l'adresse mais
     * pas la session, et personne ne doit voir passer la demande d'un autre.
     */
    public function mount(): void
    {
        $this->uuid = session()->get(self::CLE_SESSION);

        // Analyse close ou effacée : on repart d'un formulaire propre plutôt
        // que d'un écran qui pointe vers rien.
        if ($this->uuid !== null && $this->verification() === null) {
            $this->oublierLAnalyse();
        }
    }

    public function render(SoumissionVerificationService $soumissions): View
    {
        $verification = $this->verification();

        return view('livewire.public.verifier-invite', [
            'verification' => $verification,
            'restantes' => $this->restantes(),
            'bloquee' => $this->attentes >= self::ATTENTES_AVANT_ALERTE
                && ! (bool) $verification?->attendUneReprise(),
            'reprise' => (bool) $verification?->attendUneReprise(),
            'minutes' => (int) $verification?->minutesAvantReprise(),
            'relancable' => (bool) $verification?->reprisePossible(),
        ]);
    }

    public function choisirOnglet(string $onglet): void
    {
        if (in_array($onglet, ['url', 'texte'], true)) {
            $this->onglet = $onglet;
            $this->resetErrorBag();
        }
    }

    public function lancer(SoumissionVerificationService $soumissions): void
    {
        // Déjà connecté : l'espace citoyen offre mieux, et sans quota.
        if (Auth::guard('web')->check()) {
            $this->redirectRoute('citizen.verify', navigate: true);

            return;
        }

        $type = $this->onglet === 'url' ? TypeContenu::Lien : TypeContenu::Texte;

        $this->validate(
            $type === TypeContenu::Lien
                ? ['lien' => ['required', 'url:http,https', 'max:500']]
                : ['texte' => ['required', 'string', 'min:15', 'max:1000']],
            [
                'lien.required' => 'Collez le lien à vérifier.',
                'lien.url' => 'Ce lien ne semble pas valide.',
                'texte.required' => 'Collez le message à vérifier.',
                'texte.min' => 'Donnez-nous un peu plus de matière : au moins 15 caractères.',
            ],
        );

        if ($this->restantes() <= 0) {
            $this->toastWarning(
                'Vous avez utilisé vos '.self::QUOTA_INVITE.' vérifications gratuites. '
                .'Créez un compte pour continuer.'
            );

            return;
        }

        $verification = $soumissions->soumettreEnInvite(
            type: $type,
            affirmation: $type === TypeContenu::Lien ? $this->lien : $this->texte,
            empreinteVisiteur: $this->empreinteVisiteur(),
        );

        RateLimiter::hit($this->cleQuota(), 86400);

        /*
         | Mise en FILE, pas exécutée dans la requête. L'exécution synchrone
         | gelait la page plusieurs secondes en simulation ; avec de vrais
         | moteurs — vingt secondes de délai chacun — un visiteur attendrait une
         | minute devant un écran figé, et partirait.
         |
         | La page suit l'avancement et affiche le résultat sur place. Le
         | visiteur n'a pas de compte où le retrouver : c'est dit clairement.
         */
        AnalyserVerification::dispatch($verification->id);

        $this->uuid = $verification->uuid;
        session()->put(self::CLE_SESSION, $verification->uuid);
        $this->reset(['lien', 'texte']);

        $this->toastInfo('Analyse lancée, quelques secondes…');
    }

    /**
     * Suit l'avancement — la vue appelle ceci toutes les 2 secondes tant que
     * l'analyse tourne.
     */
    public function suivre(): void
    {
        $verification = $this->verification();

        if ($verification === null) {
            return;
        }

        $this->attentes++;

        if ($verification->estTerminee()) {
            $this->toastSuccess('Analyse terminée.');
        }
    }

    /**
     * Relance l'analyse sans attendre le rendez-vous automatique.
     *
     * Offerte au visiteur comme au citoyen : ne pas avoir de compte ne justifie
     * pas d'attendre plus longtemps.
     */
    public function relancer(OrchestrateurVerification $orchestrateur): void
    {
        $verification = $this->verification();

        if ($verification === null || ! $orchestrateur->reprendreMaintenant($verification)) {
            $this->toastInfo('La reprise est déjà en route.');

            return;
        }

        $this->attentes = 0;

        $this->toastSuccess('Analyse relancée.');
    }

    public function recommencer(): void
    {
        $this->oublierLAnalyse();
        $this->reset(['attentes']);
        $this->resetErrorBag();
    }

    private function oublierLAnalyse(): void
    {
        session()->forget(self::CLE_SESSION);
        $this->uuid = null;
    }

    /** Vérifications gratuites restantes pour cette adresse. */
    public function restantes(): int
    {
        return max(0, RateLimiter::remaining($this->cleQuota(), self::QUOTA_INVITE));
    }

    private function verification(): ?Verification
    {
        if ($this->uuid === null) {
            return null;
        }

        // Bornée aux vérifications d'invité : un uuid deviné ne doit pas
        // ouvrir le rapport d'un compte.
        return Verification::with('report')
            ->where('uuid', $this->uuid)
            ->whereNull('user_id')
            ->first();
    }

    private function cleQuota(): string
    {
        return 'verif-invite:'.$this->empreinteVisiteur();
    }

    /**
     * Empreinte de l'adresse, jamais l'adresse elle-même.
     *
     * Le quota a besoin de distinguer deux visiteurs, pas de savoir qui ils
     * sont. Stocker l'adresse en clair ferait de ce garde-fou un fichier de
     * connexions.
     */
    private function empreinteVisiteur(): string
    {
        return app(IdentityHasher::class)->hash((string) request()->ip());
    }
}
