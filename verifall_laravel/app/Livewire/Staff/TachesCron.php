<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Models\ScheduledTaskRun;
use App\Support\Trait\ListeDeLaConsole;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Tâches Cron » — le planificateur tourne-t-il vraiment ?
 *
 * ## Ce que la commande de diagnostic ne pouvait pas dire
 *
 * `verifall:diagnostic` compte les tâches DÉCLARÉES et rappelle qu'aucune ne
 * s'exécute sans une entrée cron appelant `schedule:run` chaque minute. Son
 * propre commentaire l'admet : « rien n'atteste qu'un cron l'appelle ».
 *
 * La lacune est sérieuse. Six tâches en dépendent : la dotation mensuelle
 * d'unités, la purge RGPD, la réconciliation des paiements, la fermeture des
 * opportunités expirées, les scores de pontage, l'infolettre. **Un cron absent
 * ne casse rien — il ne fait rien, et personne ne le sait.**
 *
 * ## L'écran croise le DÉCLARÉ et le RÉEL
 *
 * La colonne de gauche vient du planificateur lui-même : ce que le code
 * annonce. La colonne de droite vient de `scheduled_task_runs` : ce qui s'est
 * réellement produit.
 *
 * Une tâche déclarée sans aucune exécution enregistrée est affichée en rouge.
 * C'est le diagnostic que personne ne pouvait poser — et il ne demande aucune
 * commande à lancer, aucun serveur où se connecter.
 *
 * ## Lecture seule, et pas de bouton « exécuter »
 *
 * `maintenance => ['backup', 'cron']`, un seul verbe par sujet. Déclencher une
 * tâche depuis le web serait une mauvaise idée : la purge RGPD et la dotation
 * mensuelle ne sont pas idempotentes, et un double-clic doublerait la dotation
 * de tous les comptes. Ces commandes se lancent en console, où l'on sait ce
 * qu'on fait.
 */
#[Layout('components.layouts.staff', ['title' => "Tâches planifiées · Vérif'All"])]
final class TachesCron extends Component
{
    use ListeDeLaConsole;

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('maintenance.cron') ?? false, 403);
    }

    public function render(): View
    {
        $taches = $this->taches($this->planificateur());

        return view('livewire.staff.taches-cron', [
            'taches' => $taches,
            'page' => $this->paginerLaListe($taches, fn (array $t): string => ($t['description'] ?? '').' '.$t['nom'].' '.($t['expression'] ?? '')),
            'indicateurs' => $this->indicateurs($taches),
            'dernieresExecutions' => $this->dernieresExecutions(),
        ]);
    }

    /**
     * Le planificateur, avec ses tâches RÉELLEMENT chargées.
     *
     * ## Le défaut que cette méthode corrige, et comment il a été trouvé
     *
     * `routes/console.php` déclare six tâches. Injecter `Schedule` dans
     * `render()` en rendait **zéro** : les routes console ne sont chargées que
     * par le noyau console, jamais par une requête web. L'écran affichait donc
     * « 0 tâche déclarée · Aucune tâche déclarée » — c'est-à-dire l'inverse de
     * la vérité, sur l'écran même dont le travail est de dire la vérité sur le
     * planificateur.
     *
     * **Aucun test ne l'a vu.** Pest amorce le noyau console : dans la suite,
     * `Schedule` est peuplé et l'assertion passait. Il a fallu ouvrir la page
     * dans un navigateur. C'est la quatrième fois de ce chantier qu'un défaut
     * se montre en REGARDANT et non en exécutant — et le premier que l'ouverture
     * réelle, due depuis le lot 1, permet enfin d'attraper.
     *
     * `bootstrap()` est idempotent : le noyau garde une trace de son propre
     * amorçage et ne rejoue rien.
     */
    private function planificateur(): Schedule
    {
        app(Kernel::class)->bootstrap();

        return app(Schedule::class);
    }

    // ————————————————————————————————————————————————— lectures

    /**
     * Le DÉCLARÉ, croisé avec le RÉEL.
     *
     * @return list<array{nom: string, expression: string, description: string, derniere: ?ScheduledTaskRun, echecs: int}>
     */
    private function taches(Schedule $planificateur): array
    {
        $lignes = [];

        /** @var Event $evenement */
        foreach ($planificateur->events() as $evenement) {
            $nom = $evenement->getSummaryForDisplay();

            $lignes[] = [
                'nom' => $nom,
                'expression' => $evenement->expression,
                'description' => (string) ($evenement->description ?? ''),
                'derniere' => $this->derniereDe($nom),
                /*
                 | Les échecs des SEPT DERNIERS JOURS. Un cumul depuis l'origine
                 | mêlerait une panne réparée il y a six mois à une panne en
                 | cours — et la ligne rouge cesserait de vouloir dire
                 | « regardez maintenant ».
                 */
                'echecs' => ScheduledTaskRun::query()
                    ->where('task', $nom)
                    ->where('status', ScheduledTaskRun::ECHEC)
                    ->where('ran_at', '>=', now()->subWeek())
                    ->count(),
            ];
        }

        return $lignes;
    }

    /**
     * La dernière exécution de chaque tâche, en UNE requête.
     *
     * Une requête par tâche serait tolérable à six tâches et deviendrait un
     * problème à trente — et c'est le genre de chose qu'on n'observe jamais sur
     * un écran d'administration qu'on ouvre deux fois par mois.
     *
     * ## Mémorisé sur l'INSTANCE, jamais en `static`
     *
     * La première version employait un `static $cache` dans cette méthode. Un
     * `static` de méthode vit aussi longtemps que le PROCESSUS PHP : il aurait
     * survécu d'une requête Livewire à la suivante sous un serveur persistant,
     * et servi indéfiniment l'état du premier rendu — un écran de surveillance
     * qui se fige, ce qui est exactement l'inverse de son travail.
     *
     * Le défaut a été attrapé par un test : deux cas du même fichier
     * partageaient la mémoire, et le second voyait les données du premier.
     *
     * @var array<string, ScheduledTaskRun>|null
     */
    private ?array $memoire = null;

    /**
     * @return array<string, ScheduledTaskRun>
     */
    private function dernieresExecutions(): array
    {
        if ($this->memoire !== null) {
            return $this->memoire;
        }

        return $this->memoire = ScheduledTaskRun::query()
            ->orderByDesc('ran_at')
            ->get()
            ->unique('task')
            ->keyBy('task')
            ->all();
    }

    private function derniereDe(string $nom): ?ScheduledTaskRun
    {
        return $this->dernieresExecutions()[$nom] ?? null;
    }

    /**
     * @param  list<array{nom: string, expression: string, description: string, derniere: ?ScheduledTaskRun, echecs: int}>  $taches
     * @return array{declarees: int, jamais_executees: int, en_echec: int, derniere_activite: ?Carbon}
     */
    private function indicateurs(array $taches): array
    {
        $jamais = 0;
        $enEchec = 0;

        foreach ($taches as $tache) {
            if ($tache['derniere'] === null) {
                $jamais++;
            }

            if ($tache['echecs'] > 0) {
                $enEchec++;
            }
        }

        return [
            'declarees' => count($taches),
            /*
             | LE CHIFFRE QUI COMPTE. Si toutes les tâches sont « jamais
             | exécutées », il n'y a pas six pannes : il n'y a pas de cron. Le
             | libellé de l'écran le dit explicitement plutôt que de laisser
             | compter.
             */
            'jamais_executees' => $jamais,
            'en_echec' => $enEchec,
            'derniere_activite' => ScheduledTaskRun::query()->max('ran_at') !== null
                ? Carbon::parse((string) ScheduledTaskRun::query()->max('ran_at'))
                : null,
        ];
    }
}
