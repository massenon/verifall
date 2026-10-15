<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Enums\EtapePipeline;
use App\Enums\StatutVerification;
use App\Models\User;
use App\Models\Verification;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Analyse en cours » — page verification-en-cours.html du prototype.
 *
 * La barre et les témoins reflètent l'activité réelle du pipeline.
 * Le seuil d'alerte laisse le temps nécessaire aux modèles IA (DeepSeek, OpenAI, Gemini)
 * de s'exécuter sans afficher prématurément un faux message de blocage.
 */
#[Layout('components.layouts.citizen', ['title' => "Analyse en cours · Vérif'All"])]
final class VerificationEnCours extends Component
{
    use InteractsWithToasts;

    public string $uuid = '';

    /** Interrogations sans changement, pour repérer un blocage réel. */
    public int $attentes = 0;

    /** Étape mémorisée pour détecter si le pipeline avance. */
    public ?string $derniereEtapeVue = null;

    /**
     * Au-delà, on avertit l'utilisateur qu'un moteur tarde.
     * Soixante interrogations à ~2,5 secondes représentent plus de 2 minutes et demie,
     * ce qui offre la marge nécessaire pour les appels d'API externes successifs.
     */
    private const ATTENTES_AVANT_ALERTE = 60;

    public function mount(string $verification): void
    {
        $this->uuid = $verification;
        $this->verification();
    }

    public function render(): View
    {
        $verification = $this->verification();

        return view('livewire.citizen.verification-en-cours', [
            'verification' => $verification,
            'etapes' => EtapePipeline::cases(),
            'etapeCourante' => $verification->pipeline_step,
            'etapeCouranteValeur' => $verification->pipeline_step?->value ?? 0,
            'avancement' => $verification->avancement(),
            'bloquee' => $this->attentes >= self::ATTENTES_AVANT_ALERTE
                && ! $verification->attendUneReprise()
                && ! $verification->estTerminee(),
            'reprise' => $verification->attendUneReprise(),
            'minutes' => $verification->minutesAvantReprise(),
            'relancable' => $verification->reprisePossible(),
        ]);
    }

    /**
     * Interroge l'avancement — la vue appelle ceci via wire:poll.
     */
    public function suivre(): void
    {
        $verification = $this->verification();

        if ($verification->estTerminee()) {
            $this->redirectRoute('citizen.report', ['verification' => $verification->uuid], navigate: true);

            return;
        }

        // Si l'étape a avancé en base, le pipeline progresse : on réinitialise le compteur d'attente
        $etapeActuelle = (string) ($verification->pipeline_step?->value ?? '');
        if ($this->derniereEtapeVue !== $etapeActuelle) {
            $this->derniereEtapeVue = $etapeActuelle;
            $this->attentes = 0;
        } else {
            $this->attentes++;
        }
    }

    /**
     * Relance l'analyse sans attendre le délai automatique.
     */
    public function relancer(OrchestrateurVerification $orchestrateur): void
    {
        $verification = $this->verification();

        if (! $orchestrateur->reprendreMaintenant($verification)) {
            $this->toastInfo('La reprise est déjà en route.');

            return;
        }

        $this->attentes = 0;
        $this->derniereEtapeVue = null;

        $this->toastSuccess('Analyse relancée. Le rapport arrivera sur cette page.');
    }

    private function verification(): Verification
    {
        return Verification::where('uuid', $this->uuid)
            ->where('user_id', $this->utilisateur()->id)
            ->firstOrFail();
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }

    /** Statut affiché en clair. */
    public function messageDeStatut(): string
    {
        $verification = $this->verification();

        return match ($verification->status) {
            StatutVerification::EnFile => 'Votre demande attend son tour.',
            StatutVerification::EnCours => 'Votre contenu traverse le laboratoire Vérif\'All.',
            StatutVerification::EnAttenteDeReprise => 'Analyse mise en attente.',
            default => 'Analyse terminée.',
        };
    }
}
