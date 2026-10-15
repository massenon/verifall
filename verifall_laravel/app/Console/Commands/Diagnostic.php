<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\FactChecking\Services\SanteDesMoteurs;
use App\Enums\EtatMoteur;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * « Pourquoi rien ne s'est passé ? » — l'état des rouages qui échouent en silence.
 *
 * POURQUOI ELLE EXISTE. Trois pièces de cette application travaillent hors du
 * cycle d'une requête : la file d'attente, le planificateur, le transport de
 * courriel. Quand l'une s'arrête, **rien ne casse à l'écran**. Aucune exception,
 * aucun journal d'erreur, aucun test rouge — la page répond 200 et l'action
 * demandée n'a simplement jamais eu lieu.
 *
 * Le cas qui a motivé cette commande est l'inscription. `InscriptionCitoyen`
 * met le code de vérification à la file (`Mail::queue`) ; sans
 * `php artisan queue:work`, le message reste en table `jobs`, l'écran affiche
 * l'étape « saisissez le code reçu », et la personne attend un courriel qui ne
 * partira jamais. Le formulaire n'a pourtant aucun défaut.
 *
 * CE QU'ELLE FAIT, ET CE QU'ELLE NE FAIT PAS. Elle **lit** : drivers configurés,
 * tables présentes, jobs en attente et leur âge, jobs échoués, tâches planifiées.
 * Elle n'écrit rien, ne vide rien, n'envoie rien — on doit pouvoir la lancer sur
 * une production sans y réfléchir.
 *
 * Elle ne peut pas non plus *voir* un worker : rien, dans la base, n'enregistre
 * qu'un processus écoute. Elle en juge par la conséquence — un job disponible
 * depuis plus d'une minute et toujours pas pris. C'est un indice, et la commande
 * le dit comme tel plutôt que d'affirmer ce qu'elle ne sait pas.
 *
 * Le code de sortie est FAILURE dès qu'un point rouge est trouvé : la commande
 * est ainsi utilisable comme contrôle avant livraison, et pas seulement à l'œil.
 */
final class Diagnostic extends Command
{
    protected $signature = 'verifall:diagnostic';

    protected $description = 'État de la file, du courriel, du cache, du planificateur et des moteurs — les rouages qui échouent en silence.';

    /**
     * Au-delà, un job disponible et non pris désigne un worker absent.
     *
     * Une minute, et non quelques secondes : un worker occupé par une tâche
     * longue laisse légitimement la file attendre. Le seuil doit accuser
     * l'absence, pas la lenteur.
     */
    private const SECONDES_AVANT_SOUPCON = 60;

    /** Les points rouges rencontrés — ils décident du code de sortie. */
    private array $problemes = [];

    public function handle(Schedule $planificateur): int
    {
        $this->newLine();
        $this->line("  <options=bold>Vérif'All — diagnostic des rouages</>");
        $this->line('  <fg=gray>Environnement : '.app()->environment().' · lecture seule</>');

        $this->fileDAttente();
        $this->reservationDeLaFile();
        $this->courriel();
        $this->stockages();
        $this->planificateur($planificateur);
        $this->moteurs();
        $this->miseEnLigne();

        return $this->bilan();
    }

    /**
     * LES MOTEURS D'ANALYSE — le cœur du produit, et le plus lointain.
     *
     * ## Pourquoi cette section lit un CACHE au lieu de sonder
     *
     * `SanteDesMoteurs` sépare `etat()` — la dernière mesure, gardée une heure —
     * de `mesurer()`, qui interroge réellement les douze fournisseurs. Cette
     * séparation existe pour une raison écrite dans le service : « une sonde
     * relancée à chaque affichage épuiserait le quota du moteur qu'elle
     * surveille avant midi ». Un diagnostic qui provoquerait la panne qu'il
     * cherche n'aurait aucun sens, et celui-ci s'annonce en lecture seule.
     *
     * On lit donc la mesure, et on affiche SON ÂGE avec elle : une pastille
     * verte sans date ne veut rien dire. Quand elle est périmée ou absente, on
     * ne devine pas — on renvoie à la commande qui sait.
     *
     * ## Pourquoi elle est ici plutôt que dans sa propre commande
     *
     * Elle y était, et il fallait la connaître. Le 10 septembre 2026, une clé
     * Google morte éteignait quatre moteurs depuis des semaines sans que rien
     * ne le dise : le diagnostic ne regardait pas les moteurs, et personne ne
     * lançait la seconde commande. Deux commandes dont l'une s'oublie valent
     * une commande.
     */
    private function moteurs(): void
    {
        $this->titre('Moteurs d’analyse');

        $sante = app(SanteDesMoteurs::class);
        $etat = $sante->etat();
        $plancher = (int) config('factcheck.decision.moteurs_minimum', 2);

        if ($etat['mesure_le'] === null) {
            $this->info_('Jamais sondés : cette section ne dira rien tant que la mesure n’a pas été faite une fois.');
            $this->conseil('php artisan verifall:sonder-les-moteurs');

            return;
        }

        $this->ligne('Mesuré', $etat['mesure_le']->diffForHumans());
        $this->ligne('Utilisables / plancher', $sante->nombreQuiComptent().' / '.$plancher);

        if ($etat['simule']) {
            $this->avertir('Moteurs SIMULÉS (FAKE_ADAPTERS) : aucun fournisseur n’est réellement interrogé.');
        }

        /*
         | Une clé refusée n'est pas un moteur injoignable, et la distinction
         | commande le geste : régénérer une clé, ou ouvrir une sortie réseau.
         | C'est `AdaptateurHttp::cleRefusee()` qui la fait, depuis que Google
         | nous a appris à rendre 400 là où les autres rendent 401.
         */
        foreach ($etat['moteurs'] as $moteur) {
            if ($moteur['etat'] !== EtatMoteur::Injoignable) {
                continue;
            }

            $motif = $moteur['precision'] ?? 'le fournisseur refuse la clé ou ne répond pas';

            $this->rouge(sprintf('%s : %s.', $moteur['libelle'], $motif));
        }

        if ($sante->nombreQuiComptent() < $plancher) {
            $this->rouge(sprintf(
                '%d moteur(s) utilisable(s) pour un plancher de %d : les analyses ne concluront pas.',
                $sante->nombreQuiComptent(),
                $plancher,
            ));

            return;
        }

        if ($sante->estPerimee()) {
            $this->avertir(sprintf(
                'Mesure vieille de plus de %d minutes : elle décrit peut-être un état révolu.',
                SanteDesMoteurs::FRAICHEUR_MINUTES,
            ));
            $this->conseil('php artisan verifall:sonder-les-moteurs');

            return;
        }

        $this->vert('Les moteurs répondent, et le plancher est tenu.');
    }

    /**
     * LA FILE D'ATTENTE — le point de panne le plus fréquent, et le plus muet.
     */
    private function fileDAttente(): void
    {
        $this->titre("File d'attente");

        $driver = (string) config('queue.default');
        $this->ligne('Pilote', $driver);

        if ($driver === 'sync') {
            /*
             | `sync` exécute le job dans la requête. Rien ne peut donc rester
             | en attente — mais l'inscription attend alors l'envoi SMTP avant
             | de rendre la main, ce qui est un autre problème.
             */
            $this->avertir('Les jobs s\'exécutent dans la requête : aucun worker requis, mais l\'utilisateur attend le SMTP.');

            return;
        }

        if ($driver !== 'database') {
            $this->info_('Pilote non « database » : cette commande ne sait pas inspecter cette file.');

            return;
        }

        try {
            if (! Schema::hasTable('jobs')) {
                $this->rouge('La table `jobs` est absente. Lancez `php artisan queue:table` puis `migrate`.');

                return;
            }

            $enAttente = (int) DB::table('jobs')->count();
            $echoues = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

            $this->ligne('Jobs en attente', (string) $enAttente);
            $this->ligne('Jobs échoués', (string) $echoues);

            if ($echoues > 0) {
                $this->avertir($echoues.' job(s) en échec. `php artisan queue:failed` les liste, `queue:retry all` les rejoue.');
            }

            if ($enAttente === 0) {
                $this->vert('Rien en attente.');

                return;
            }

            /*
             | `available_at` et non `created_at` : un job différé n'est pas en
             | retard tant que son échéance n'est pas passée. Compter depuis la
             | création accuserait un worker en bonne santé.
             */
            $plusAncien = (int) DB::table('jobs')->min('available_at');
            $attente = time() - $plusAncien;

            $this->ligne('Plus ancien disponible depuis', $this->duree($attente));

            if ($attente > self::SECONDES_AVANT_SOUPCON) {
                $this->rouge(
                    'Des jobs attendent depuis plus de '.$this->duree(self::SECONDES_AVANT_SOUPCON)
                    .' : le worker ne tourne probablement pas.'
                );
                $this->conseil('php artisan queue:work');
            } else {
                $this->vert('La file avance — un worker semble bien à l\'écoute.');
            }
        } catch (Throwable $e) {
            $this->rouge('Base injoignable : '.$e->getMessage());
        }
    }

    /**
     * LA RÉSERVATION — le défaut qui fait tourner un job DEUX FOIS.
     *
     * `retry_after` n'est pas un délai avant nouvelle tentative après échec :
     * c'est la durée pendant laquelle la file garde un job réservé. Passé ce
     * temps, elle le juge abandonné et le rend à un autre worker — sans
     * vérifier que le premier a fini. Si un job peut durer plus longtemps que
     * la réservation, il sera un jour exécuté deux fois EN MÊME TEMPS.
     *
     * Ça n'a rien d'hypothétique : c'est ce qui a produit en production un
     * « Duplicate entry for key analysis_reports_verification_id_unique ». Deux
     * passages avaient interrogé les moteurs, deux passages ont écrit le
     * rapport, le second s'est écrasé sur la contrainte — après avoir payé.
     *
     * Rien à l'écran ne signale ce défaut tant qu'aucun job ne dépasse le
     * seuil. D'où sa place ici : une commande qui ne trouve que les pannes déjà
     * survenues arrive toujours trop tard.
     */
    private function reservationDeLaFile(): void
    {
        $this->titre('Réservation des jobs');

        $connexion = (string) config('queue.default');

        if ($connexion !== 'database') {
            $this->info_('Pilote non « database » : la réservation ne se lit pas ici.');

            return;
        }

        $reservation = (int) config('queue.connections.database.retry_after');
        $plusLong = $this->jobLePlusLong();

        $this->ligne('Réservation (retry_after)', $reservation.' s');

        if ($plusLong === null) {
            $this->info_('Aucun job ne déclare de $timeout : rien à comparer.');

            return;
        }

        [$nom, $timeout] = $plusLong;

        $this->ligne('Job le plus long', $nom.' — '.$timeout.' s');

        if ($timeout >= $reservation) {
            $this->rouge(
                'La réservation ('.$reservation.' s) ne couvre pas '.$nom
                .' ('.$timeout.' s) : ce job peut tourner deux fois en parallèle.'
            );
            $this->conseil('DB_QUEUE_RETRY_AFTER='.($timeout + 300).' dans .env');

            return;
        }

        $this->vert('La réservation couvre le job le plus long.');
    }

    /**
     * Le job déclarant le plus grand `$timeout`, s'il en existe un.
     *
     * Lecture par réflexion plutôt que par une liste tenue à la main : une
     * liste oubliée le jour où l'on ajoute un job long redonnerait exactement
     * le silence que cette commande existe pour rompre.
     *
     * @return array{0: string, 1: int}|null
     */
    private function jobLePlusLong(): ?array
    {
        $trouve = null;

        foreach (glob(app_path('Jobs').'/*.php') ?: [] as $fichier) {
            $classe = 'App\\Jobs\\'.basename($fichier, '.php');

            if (! class_exists($classe) || ! property_exists($classe, 'timeout')) {
                continue;
            }

            try {
                $timeout = (int) (new \ReflectionClass($classe))
                    ->getDefaultProperties()['timeout'];
            } catch (Throwable) {
                continue;
            }

            if ($trouve === null || $timeout > $trouve[1]) {
                $trouve = [class_basename($classe), $timeout];
            }
        }

        return $trouve;
    }

    /**
     * LE COURRIEL. On ne teste pas la connexion : ouvrir un socket SMTP peut
     * bloquer une minute sur un hôte injoignable, et une commande de diagnostic
     * qui se fige est pire que pas de diagnostic. On rapporte la configuration
     * et on nomme la combinaison qui piège en local.
     */
    private function courriel(): void
    {
        $this->titre('Courriel');

        $mailer = (string) config('mail.default');
        $this->ligne('Transport', $mailer);
        $this->ligne('Expéditeur', (string) config('mail.from.address'));

        if ($mailer === 'smtp') {
            $hote = (string) config('mail.mailers.smtp.host');
            $this->ligne('Hôte SMTP', $hote.':'.config('mail.mailers.smtp.port'));

            if (app()->environment('local')) {
                $this->avertir(
                    'SMTP distant en environnement local : si `'.$hote.'` est injoignable depuis ce poste, '
                    .'tout courriel échouera dans le worker, hors de votre vue.'
                );
                $this->conseil('MAIL_MAILER=log  →  les messages arrivent dans storage/logs/laravel.log');
            }
        }

        if ($mailer === 'log') {
            $this->vert('Les messages sont écrits dans le journal — rien ne part réellement.');
        }
    }

    /** CACHE ET SESSION — deux pilotes `database` qui exigent leur table. */
    private function stockages(): void
    {
        $this->titre('Cache et session');

        foreach ([
            'Cache' => [(string) config('cache.default'), 'cache', 'php artisan cache:table'],
            'Session' => [(string) config('session.driver'), 'sessions', 'php artisan session:table'],
        ] as $nom => [$driver, $table, $remede]) {
            $this->ligne($nom, $driver);

            if ($driver !== 'database') {
                continue;
            }

            try {
                if (! Schema::hasTable($table)) {
                    $this->rouge("Pilote « database » mais la table `{$table}` est absente.");
                    $this->conseil($remede);
                }
            } catch (Throwable $e) {
                $this->rouge('Base injoignable : '.$e->getMessage());

                return;
            }
        }
    }

    /**
     * LE PLANIFICATEUR. Comme pour le worker, rien n'atteste qu'un cron
     * l'appelle : on compte les tâches déclarées et on rappelle la condition.
     */
    private function planificateur(Schedule $planificateur): void
    {
        $this->titre('Planificateur');

        $taches = count($planificateur->events());
        $this->ligne('Tâches déclarées', (string) $taches);

        if ($taches === 0) {
            return;
        }

        $this->info_(
            'Aucune ne s\'exécute sans une entrée cron appelant `php artisan schedule:run` '
            .'chaque minute (voir docs/CRONS.md).'
        );
    }

    /**
     * LA MISE EN LIGNE — ce qui doit être vrai le jour où verifall.com ouvre.
     *
     * ## Pourquoi `APP_DEBUG` vient en premier, et pourquoi c'est ROUGE
     *
     * Avec `APP_DEBUG=true`, la page d'erreur de Laravel affiche la pile
     * d'appels **et l'environnement** : chaque clé d'API, le mot de passe de la
     * base, le secret de la passerelle de paiement. Il suffit d'une seule
     * requête qui rend 500 — et il y en aura une — pour que tout soit lisible
     * par le premier visiteur venu.
     *
     * Le geste qui rend ce contrôle indispensable : **renouveler les clés puis
     * déployer avec `APP_DEBUG=true` divulgue les nouvelles le jour même.** La
     * rotation ne sert alors à rien, et l'on croit le problème réglé.
     *
     * ## Pourquoi les autres points
     *
     * `APP_ENV` ne protège rien à lui seul, mais il commande beaucoup de choses
     * en aval — dont le comportement par défaut de plusieurs paquets. Une
     * production qui se croit en `local` est une production qui bavarde.
     *
     * `APP_URL` sert à fabriquer les URL signées et les liens des courriels. En
     * `http://`, un lien de réinitialisation de mot de passe voyage en clair.
     */
    private function miseEnLigne(): void
    {
        $this->titre('Mise en ligne');

        $environnement = (string) config('app.env');
        $enProduction = app()->environment('production');
        $debug = (bool) config('app.debug');
        $url = (string) config('app.url');

        $this->ligne('APP_ENV', $environnement);
        $this->ligne('APP_URL', $url !== '' ? $url : '(vide)');

        if ($debug && $enProduction) {
            $this->rouge(
                'APP_DEBUG=true EN PRODUCTION : la page d’erreur publie vos clés d’API.'
            );
            $this->line('      <fg=gray>→ APP_DEBUG=false, puis `php artisan config:clear`</>');
        } elseif ($debug) {
            $this->avertir(
                'APP_DEBUG=true — acceptable hors production, à passer à false avant la mise en ligne.'
            );
        } else {
            $this->vert('APP_DEBUG=false.');
        }

        if (! $enProduction && $url !== '' && ! str_contains($url, 'localhost') && ! str_contains($url, '127.0.0.1')) {
            /*
             | Le cas qui a motivé ce contrôle : `APP_URL=https://verifall.com`
             | avec `APP_ENV=local`. La configuration désigne la production, le
             | drapeau dit le contraire. L'un des deux est faux, et rien ne le
             | disait.
             */
            $this->avertir(sprintf(
                'APP_URL désigne « %s » mais APP_ENV vaut « %s » : l’un des deux est faux.',
                $url,
                $environnement,
            ));
        }

        if ($url === '') {
            $this->rouge('APP_URL est vide : les liens des courriels et les URL signées seront faux.');
        } elseif ($enProduction && ! str_starts_with($url, 'https://')) {
            $this->rouge('APP_URL n’est pas en HTTPS : un lien de réinitialisation voyagerait en clair.');
        }
    }

    private function bilan(): int
    {
        $this->newLine();

        if ($this->problemes === []) {
            $this->line('  <fg=green;options=bold>Aucun point bloquant.</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->line('  <fg=red;options=bold>'.count($this->problemes).' point(s) à traiter :</>');

        foreach ($this->problemes as $probleme) {
            $this->line('    <fg=red>·</> '.$probleme);
        }

        $this->newLine();

        return self::FAILURE;
    }

    // ------------------------------------------------------------ affichage

    private function titre(string $texte): void
    {
        $this->newLine();
        $this->line('  <options=bold>'.$texte.'</>');
    }

    /**
     * Une ligne « clé … valeur », alignée.
     *
     * Le remplissage se calcule sur `mb_strlen` et non avec `%-32s` : `sprintf`
     * compte les OCTETS, si bien que « Jobs échoués » — deux caractères
     * accentués, donc deux octets de plus — se retrouvait décalé de deux
     * colonnes vers la gauche. Sur un tableau de bord dont tout l'intérêt est
     * de se parcourir d'un coup d'œil, la colonne doit tomber droit.
     *
     * `mb_str_pad` ferait cela en un appel, mais il n'existe qu'à partir de
     * PHP 8.3 et `composer.json` déclare `^8.2`.
     */
    private function ligne(string $cle, string $valeur): void
    {
        $remplissage = max(1, 32 - mb_strlen($cle));

        $this->line('    <fg=gray>'.$cle.str_repeat(' ', $remplissage).'</> '.$valeur);
    }

    private function vert(string $texte): void
    {
        $this->line('    <fg=green>✔</> '.$texte);
    }

    private function avertir(string $texte): void
    {
        $this->line('    <fg=yellow>!</> '.$texte);
    }

    private function rouge(string $texte): void
    {
        $this->line('    <fg=red>✘</> '.$texte);
        $this->problemes[] = $texte;
    }

    private function info_(string $texte): void
    {
        $this->line('    <fg=gray>i</> <fg=gray>'.$texte.'</>');
    }

    private function conseil(string $commande): void
    {
        $this->line('      <fg=cyan>→</> <fg=cyan>'.$commande.'</>');
    }

    private function duree(int $secondes): string
    {
        if ($secondes < 60) {
            return $secondes.' s';
        }

        if ($secondes < 3600) {
            return intdiv($secondes, 60).' min';
        }

        if ($secondes < 86400) {
            return intdiv($secondes, 3600).' h';
        }

        return intdiv($secondes, 86400).' j';
    }
}
