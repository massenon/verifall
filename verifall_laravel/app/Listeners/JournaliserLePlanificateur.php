<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Atteste que le planificateur tourne — ou que personne ne l'appelle.
 *
 * ## Ce que `verifall:diagnostic` ne pouvait pas dire
 *
 * La commande de diagnostic compte les tâches DÉCLARÉES et rappelle qu'aucune
 * ne s'exécute sans une entrée cron appelant `schedule:run` chaque minute. Elle
 * s'arrête là, et son propre commentaire l'admet : « rien n'atteste qu'un cron
 * l'appelle ».
 *
 * C'est une lacune sérieuse. Six tâches en dépendent, dont la dotation
 * mensuelle d'unités, la purge RGPD et la réconciliation des paiements. Un cron
 * absent ne casse rien : il ne fait rien, et personne ne le sait.
 *
 * ## L'ABSENCE de ligne est l'information
 *
 * Une ligne ici prouve qu'une tâche s'est exécutée. Une tâche déclarée sans
 * aucune ligne, c'est un cron absent — et c'est ce que l'écran affiche en
 * rouge. La preuve du bon fonctionnement et la preuve de la panne viennent du
 * même endroit.
 *
 * ## Un échec de journalisation ne fait pas échouer la tâche
 *
 * Tout est sous `try/catch`. Une purge RGPD qui s'exécute correctement ne doit
 * pas être comptée en échec parce que son journal était indisponible.
 */
final class JournaliserLePlanificateur
{
    public function handleScheduledTaskFinished(ScheduledTaskFinished $evenement): void
    {
        $this->enregistrer(
            tache: $this->nom($evenement->task->getSummaryForDisplay()),
            statut: ScheduledTaskRun::SUCCES,
            duree: (int) round($evenement->runtime * 1000),
            sortie: $this->sortie($evenement->task->output),
        );
    }

    public function handleScheduledTaskFailed(ScheduledTaskFailed $evenement): void
    {
        $this->enregistrer(
            tache: $this->nom($evenement->task->getSummaryForDisplay()),
            statut: ScheduledTaskRun::ECHEC,
            duree: null,
            // Le message de l'exception, pas sa trace : ce qui sert au
            // diagnostic tient dans la première ligne, et une trace complète
            // en base grossit sans rien apprendre de plus.
            sortie: Str::limit($evenement->exception->getMessage(), 2000),
        );
    }

    private function enregistrer(string $tache, string $statut, ?int $duree, ?string $sortie): void
    {
        try {
            ScheduledTaskRun::query()->create([
                'task' => $tache,
                'status' => $statut,
                'duration_ms' => $duree,
                'output' => $sortie,
                'ran_at' => now(),
            ]);
        } catch (Throwable $souci) {
            Log::warning('Journal du planificateur indisponible : '.$souci->getMessage());
        }
    }

    private function nom(string $resume): string
    {
        return Str::limit($resume, 250, '');
    }

    /**
     * La sortie de la tâche, si elle a été redirigée vers un fichier lisible.
     *
     * `output` vaut `/dev/null` par défaut — Laravel ne capture rien tant qu'on
     * ne le lui demande pas. On ne lit donc que ce qui existe réellement, et on
     * rend `null` sinon : une chaîne « /dev/null » en base serait du bruit.
     */
    private function sortie(?string $chemin): ?string
    {
        if ($chemin === null || $chemin === '/dev/null' || ! is_file($chemin)) {
            return null;
        }

        $contenu = @file_get_contents($chemin);

        return is_string($contenu) && trim($contenu) !== ''
            ? Str::limit(trim($contenu), 2000)
            : null;
    }
}
