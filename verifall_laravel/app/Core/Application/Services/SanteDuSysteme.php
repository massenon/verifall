<?php

declare(strict_types=1);

namespace App\Core\Application\Services;

use App\Core\FactChecking\Services\SanteDesMoteurs;
use App\Models\MailLog;
use App\Models\ScheduledTaskRun;
use FilesystemIterator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * L'état d'exploitation de la plateforme, mesuré — jamais illustré.
 *
 * ## Ce que cette carte n'affiche PAS, et pourquoi
 *
 * La maquette demandait « Charge CPU 46 % », « Espace disque 89 % · 300,6 Go
 * libres sur 2 697 Go » et « Reverb (WebSockets) : Déconnecté ». Les trois ont
 * été écartés le 1er septembre 2026, après examen :
 *
 *   · **Reverb n'existe pas dans ce projet.** Absent de `composer.json`, aucun
 *     événement `ShouldBroadcast`, pas de `routes/channels.php`,
 *     `BROADCAST_CONNECTION=log`. Le temps réel se fait au `wire:poll`. Une
 *     ligne annonçant « Déconnecté » aurait rapporté l'état d'un composant qui
 *     n'a jamais été installé.
 *   · **La charge CPU ne se mesure pas en hébergement mutualisé.**
 *     `sys_getloadavg()` rend la charge de TOUT le nœud — des centaines de
 *     comptes — et l'hébergeur la désactive souvent. Le chiffre serait exact et
 *     sans rapport avec l'application.
 *   · **Le disque non plus.** `disk_free_space()` rend la partition du serveur,
 *     pas le quota du compte cPanel. « 300,6 Go libres » décrirait l'espace du
 *     voisin.
 *
 * Ce qui les remplace est ce qui casse RÉELLEMENT en mutualisé, où aucun démon
 * ne tourne : le planificateur, la file d'attente, le courriel. Chaque ligne est
 * une mesure, et chacune sait dire si elle est en défaut.
 */
final class SanteDuSysteme
{
    /**
     * Un job qui attend plus longtemps que cela signale un worker mort, pas une
     * charge. En mutualisé, la file est vidée par une entrée cron chaque
     * minute : dix minutes d'attente sont déjà une anomalie.
     */
    private const ATTENTE_ANORMALE_MINUTES = 10;

    /** Deux heures sans passage du planificateur : l'entrée cron manque. */
    private const PLANIFICATEUR_MUET_MINUTES = 120;

    public function __construct(private readonly SanteDesMoteurs $moteurs) {}

    /**
     * @return array{
     *     moteurs: array<string, mixed>,
     *     infrastructure: list<array{cle: string, libelle: string, valeur: string, detail: string|null, alerte: bool}>,
     * }
     */
    public function tout(): array
    {
        return [
            'moteurs' => $this->moteurs->etat(),
            'infrastructure' => [
                $this->planificateur(),
                $this->fileDAttente(),
                $this->courriel(),
                $this->stockage(),
            ],
        ];
    }

    /**
     * Le planificateur tourne-t-il ?
     *
     * C'EST LA LIGNE LA PLUS IMPORTANTE DE LA CARTE en mutualisé. cPanel n'a pas
     * de démon : sans une entrée cron appelant `schedule:run` chaque minute, les
     * sept tâches déclarées s'arrêtent toutes, en silence, sans lever la moindre
     * erreur — purge RGPD, dotation mensuelle, réconciliation des paiements,
     * sonde des moteurs.
     *
     * @return array{cle: string, libelle: string, valeur: string, detail: string|null, alerte: bool}
     */
    private function planificateur(): array
    {
        $dernier = ScheduledTaskRun::query()->latest('ran_at')->first();

        if ($dernier === null) {
            return [
                'cle' => 'planificateur',
                'libelle' => 'Planificateur',
                'valeur' => 'Jamais exécuté',
                'detail' => "Aucune tâche n'a jamais tourné : l'entrée cron « schedule:run » manque.",
                'alerte' => true,
            ];
        }

        $muet = $dernier->ran_at->lt(now()->subMinutes(self::PLANIFICATEUR_MUET_MINUTES));

        return [
            'cle' => 'planificateur',
            'libelle' => 'Planificateur',
            'valeur' => $dernier->ran_at->diffForHumans(),
            'detail' => $muet
                ? "Plus de deux heures sans passage : vérifiez l'entrée cron."
                : $dernier->task,
            'alerte' => $muet,
        ];
    }

    /**
     * La file avance-t-elle ?
     *
     * Le compteur seul ne dit rien : 216 jobs en attente est excellent si la
     * file se vide, catastrophique si le plus ancien date de trois heures. C'est
     * L'ÂGE du plus ancien qui distingue une charge d'un worker mort.
     *
     * @return array{cle: string, libelle: string, valeur: string, detail: string|null, alerte: bool}
     */
    private function fileDAttente(): array
    {
        $enAttente = (int) DB::table('jobs')->count();
        $echoues = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        $plusAncien = DB::table('jobs')->min('available_at');

        $attente = $plusAncien === null
            ? null
            : (int) Carbon::createFromTimestamp((int) $plusAncien)->diffInMinutes(now());

        $bloquee = $attente !== null && $attente > self::ATTENTE_ANORMALE_MINUTES;

        return [
            'cle' => 'file',
            'libelle' => "File d'attente",
            'valeur' => $enAttente === 0 ? 'Vide' : $enAttente.' en attente',
            'detail' => match (true) {
                $bloquee => sprintf(
                    'Le plus ancien attend depuis %d min : le worker ne tourne pas.',
                    $attente,
                ),
                $echoues > 0 => $echoues.' job(s) en échec à examiner.',
                default => null,
            },
            'alerte' => $bloquee || $echoues > 0,
        ];
    }

    /**
     * Les courriels partent-ils ?
     *
     * Sur 24 h, parce que c'est la fenêtre où un échec est encore rattrapable :
     * un code de vérification non reçu ce matin bloque une inscription
     * aujourd'hui.
     *
     * @return array{cle: string, libelle: string, valeur: string, detail: string|null, alerte: bool}
     */
    private function courriel(): array
    {
        $depuis = now()->subDay();

        $total = MailLog::query()->where('created_at', '>=', $depuis)->count();
        $echoues = MailLog::query()->where('created_at', '>=', $depuis)->enEchec()->count();

        if ($total === 0) {
            return [
                'cle' => 'courriel',
                'libelle' => 'Courriel (24 h)',
                'valeur' => 'Aucun envoi',
                'detail' => config('mail.default') === 'log'
                    ? 'MAIL_MAILER=log : rien ne part réellement.'
                    : null,
                'alerte' => false,
            ];
        }

        return [
            'cle' => 'courriel',
            'libelle' => 'Courriel (24 h)',
            'valeur' => sprintf('%d envoyé%s', $total, $total > 1 ? 's' : ''),
            'detail' => $echoues > 0 ? sprintf('%d échec(s) sur %d.', $echoues, $total) : null,
            'alerte' => $echoues > 0,
        ];
    }

    /**
     * Ce que l'application occupe — pas ce que la partition contient.
     *
     * `storage/` est le seul volume dont l'application est responsable et sur
     * lequel elle peut agir : justificatifs KYC, exports RGPD, cartes de démenti,
     * journaux. C'est aussi le seul qui grossit sans qu'on regarde.
     *
     * @return array{cle: string, libelle: string, valeur: string, detail: string|null, alerte: bool}
     */
    private function stockage(): array
    {
        return [
            'cle' => 'stockage',
            'libelle' => 'Stockage applicatif',
            'valeur' => $this->lisible($this->poidsDuDossier(storage_path())),
            'detail' => 'Dossier storage/ — le seul volume que l’application contrôle.',
            'alerte' => false,
        ];
    }

    private function poidsDuDossier(string $chemin): int
    {
        if (! is_dir($chemin)) {
            return 0;
        }

        $total = 0;

        /** @var iterable<SplFileInfo> $fichiers */
        $fichiers = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($chemin, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($fichiers as $fichier) {
            if ($fichier->isFile()) {
                $total += $fichier->getSize();
            }
        }

        return $total;
    }

    private function lisible(int $octets): string
    {
        $unites = ['o', 'Ko', 'Mo', 'Go'];
        $rang = 0;

        while ($octets >= 1024 && $rang < count($unites) - 1) {
            $octets = (int) round($octets / 1024);
            $rang++;
        }

        return number_format($octets, 0, ',', ' ').' '.$unites[$rang];
    }
}
