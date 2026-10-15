<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Models\Verification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Fait tourner le pipeline hors de la requête HTTP.
 *
 * Une analyse mobilise une douzaine de services et peut durer une minute :
 * l'exécuter pendant la requête ferait expirer la page, et la personne
 * croirait avoir perdu ses unités.
 */
final class AnalyserVerification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Une analyse ne doit pas tenir la file indéfiniment. */
    public int $timeout = 180;

    public function __construct(public readonly int $verificationId) {}

    public function handle(OrchestrateurVerification $orchestrateur): void
    {
        $verification = Verification::find($this->verificationId);

        // Supprimée entre-temps — compte effacé, par exemple.
        if ($verification === null) {
            return;
        }

        /*
         | Une analyse EN ATTENTE DE REPRISE est justement celle qu'on revient
         | faire : `estTerminee()` la déclare non finale, mais la garde ci-dessus
         | doit rester explicite — sans quoi une relance manuelle et la reprise
         | automatique pourraient se croiser et analyser deux fois.
         */
        if ($verification->estTerminee()) {
            return;
        }

        /*
         | Job périmé : une reprise est programmée plus tard, donc celle-ci a
         | déjà été doublée par une relance manuelle. Repasser maintenant
         | consommerait une tentative alors que les moteurs n'ont pas eu le
         | temps de se remettre.
         */
        if ($verification->repriseProgrammeePlusTard()) {
            return;
        }

        $orchestrateur->executer($verification);
    }

    /**
     * L'échec doit se VOIR — et ne jamais coûter d'unités.
     *
     * Un écran « analyse interrompue » sur un compte débité est la pire issue
     * possible : la personne a payé et repart les mains vides. Tant qu'il
     * reste des tentatives, l'orchestrateur reprogramme ; sinon il renonce et
     * rembourse.
     */
    public function failed(?Throwable $exception): void
    {
        $verification = Verification::find($this->verificationId);

        if ($verification === null) {
            return;
        }

        app(OrchestrateurVerification::class)->apresEchecTechnique($verification);
    }
}
