<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| `verifall:diagnostic` — la commande qui rend visible ce qui échoue en silence
|--------------------------------------------------------------------------
| Ce qui est éprouvé ici n'est pas l'affichage, c'est le JUGEMENT : la commande
| doit distinguer une file qui avance d'une file abandonnée, et n'accuser un
| worker absent que lorsqu'elle en a la preuve indirecte.
|
| Le cas nominal des tests est trompeur, et c'est la raison d'être des
| `config()` ci-dessous : `phpunit.xml` fixe `QUEUE_CONNECTION=sync`, où aucun
| job ne peut rester en attente. Sans forcer le pilote `database`, on
| n'éprouverait que la branche qui ne sert jamais en développement.
|
| Le troisième test est le plus important des trois. Un diagnostic qui crie au
| loup dès qu'un job est en file serait désactivé au bout d'une semaine : il
| doit se taire quand la file avance normalement.
*/

use App\Enums\EtatMoteur;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Un job en table, disponible depuis `$ageEnSecondes`. */
function jobEnAttente(int $ageEnSecondes): void
{
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => time() - $ageEnSecondes,
        'created_at' => time() - $ageEnSecondes,
    ]);
}

it('réussit quand la file est vide', function (): void {
    config(['queue.default' => 'database']);

    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('Rien en attente.')
        ->assertSuccessful();
});

it('signale un worker absent quand un job traîne', function (): void {
    config(['queue.default' => 'database']);

    // Cinq minutes : bien au-delà du seuil d'une minute, donc sans ambiguïté.
    jobEnAttente(300);

    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('le worker ne tourne probablement pas')
        ->assertFailed();
});

it('se tait quand le job vient tout juste d\'arriver', function (): void {
    config(['queue.default' => 'database']);

    /*
     | Deux secondes : le job est bien en attente, mais un worker en bonne
     | santé n'a pas encore eu le temps de le prendre. Accuser ici serait
     | accuser la normalité — et une alerte qui se déclenche sans motif finit
     | par n'être plus lue.
     */
    jobEnAttente(2);

    $this->artisan('verifall:diagnostic')
        ->doesntExpectOutputToContain('le worker ne tourne probablement pas')
        ->assertSuccessful();
});

it('ne cherche pas de worker quand la file est synchrone', function (): void {
    config(['queue.default' => 'sync']);

    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('aucun worker requis')
        ->assertSuccessful();
});

it('compte les tâches planifiées', function (): void {
    /*
     | Six tâches sont déclarées dans `routes/console.php`. Le nombre exact
     | n'est pas figé ici — il changera — mais le planificateur ne doit jamais
     | être vide : le jour où il l'est, la dotation mensuelle, la purge RGPD et
     | la réconciliation des paiements ont cessé sans que rien ne le dise.
     */
    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('Tâches déclarées')
        ->assertSuccessful();

    expect(app(Schedule::class)->events())->not->toBeEmpty();
});

it('n\'écrit rien — elle est utilisable en production', function (): void {
    config(['queue.default' => 'database']);

    jobEnAttente(300);

    $this->artisan('verifall:diagnostic')->assertFailed();

    // Le job accusé est toujours là : la commande diagnostique, elle ne purge pas.
    expect(DB::table('jobs')->count())->toBe(1);
});

// ═══════════════════════════════════════════════ la mise en ligne

it('REFUSE APP_DEBUG=true en production', function (): void {
    /*
     | LE contrôle qui protège les clés d'API, et il vaut d'être expliqué.
     |
     | Avec `APP_DEBUG=true`, la page d'erreur de Laravel affiche la pile
     | d'appels ET l'environnement : chaque clé d'API, le mot de passe de la
     | base, le secret de la passerelle de paiement. Il suffit d'UNE requête qui
     | rend 500 — et il y en aura une.
     |
     | Le geste qui rend ce test indispensable : renouveler les clés puis
     | déployer avec le débogage actif divulgue les NOUVELLES le jour même. La
     | rotation ne sert alors à rien, et l'on croit le problème réglé.
     */
    config(['app.env' => 'production', 'app.debug' => true, 'app.url' => 'https://verifall.com']);
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('publie vos clés d’API')
        ->assertFailed();
});

it('accepte APP_DEBUG=true hors production, en le signalant', function (): void {
    // Sur un poste de développement, c'est le réglage normal. Le rappeler sans
    // faire échouer : une commande qui crie au loup en local finit ignorée.
    config(['app.env' => 'local', 'app.debug' => true, 'app.url' => 'http://localhost']);

    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('à passer à false avant la mise en ligne');
});

it('signale une APP_URL de production sous un APP_ENV local', function (): void {
    /*
     | Le cas RÉEL qui a motivé cette section : `APP_URL=https://verifall.com`
     | avec `APP_ENV=local`. La configuration désigne la production, le drapeau
     | dit le contraire. L'un des deux est faux, et rien ne le disait.
     */
    config(['app.env' => 'local', 'app.debug' => false, 'app.url' => 'https://verifall.com']);

    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('l’un des deux est faux');
});

it('REFUSE une APP_URL de production qui n’est pas en HTTPS', function (): void {
    // Un lien de réinitialisation de mot de passe voyagerait en clair.
    config(['app.env' => 'production', 'app.debug' => false, 'app.url' => 'http://verifall.com']);
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('voyagerait en clair')
        ->assertFailed();
});

/*
|--------------------------------------------------------------------------
| Les moteurs d'analyse dans le diagnostic
|--------------------------------------------------------------------------
|
| Ils y sont depuis le 10 septembre 2026, et l'histoire vaut d'être connue :
| une clé Google morte éteignait QUATRE moteurs depuis des semaines sans que
| rien ne le dise. Le diagnostic ne regardait pas les moteurs ; leur état vivait
| dans une seconde commande, que personne ne lançait.
|
| Deux commandes dont l'une s'oublie valent une commande.
|
| Ce qui est éprouvé ici, ce n'est pas l'affichage : c'est que l'état des
| moteurs pèse sur le CODE DE SORTIE. Un diagnostic qui afficherait la panne en
| sortant vert ne servirait à rien dans un contrôle avant livraison.
|
*/

/** Force une mesure de santé des moteurs en cache, sans toucher au réseau. */
function mesureDesMoteurs(array $moteurs, ?string $quand = null): void
{
    Cache::put('sante_des_moteurs', [
        'mesure_le' => ($quand ?? now()->toIso8601String()),
        'simule' => false,
        'moteurs' => $moteurs,
    ], now()->addDay());
}

function moteur(string $cle, EtatMoteur $etat, ?string $precision = null): array
{
    return ['moteur' => $cle, 'libelle' => $cle, 'etat' => $etat->value, 'precision' => $precision];
}

it('fait échouer le diagnostic quand une clé de moteur est refusée', function (): void {
    mesureDesMoteurs([
        moteur('gemini', EtatMoteur::Operationnel),
        moteur('deepseek', EtatMoteur::Operationnel),
        moteur('google_factcheck', EtatMoteur::Injoignable, 'clé refusée (INVALID_ARGUMENT)'),
    ]);

    $this->artisan('verifall:diagnostic')
        // Le motif, pas seulement l'état : régénérer une clé et ouvrir une
        // sortie réseau ne sont pas le même geste.
        ->expectsOutputToContain('clé refusée')
        ->assertFailed();
});

it('ne dit rien des moteurs tant qu’ils n’ont jamais été sondés', function (): void {
    Cache::forget('sante_des_moteurs');

    // Une pastille verte sans mesure ne veut rien dire — mieux vaut renvoyer à
    // la commande qui sait que d'inventer un état.
    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('Jamais sondés');
});

it('avertit quand la mesure est trop vieille pour être crue', function (): void {
    mesureDesMoteurs([
        moteur('gemini', EtatMoteur::Operationnel),
        moteur('deepseek', EtatMoteur::Operationnel),
    ], now()->subHours(6)->toIso8601String());

    // Elle décrit peut-être un état révolu : on l'affiche AVEC son âge plutôt
    // que de la présenter comme un constat du jour.
    $this->artisan('verifall:diagnostic')
        ->expectsOutputToContain('Mesure vieille');
});

it('sort au VERT quand les moteurs vont bien — le contrôle du test précédent', function (): void {
    /*
     | Sans ce test, le précédent serait creux : il assure `assertFailed()`, et
     | passerait tout aussi bien si l'échec venait d'un tout autre point rouge
     | de l'environnement de test. C'est ici qu'on vérifie que c'est BIEN la
     | section des moteurs qui fait la différence — mêmes conditions, un seul
     | changement : la clé n'est plus refusée.
     */
    mesureDesMoteurs([
        moteur('gemini', EtatMoteur::Operationnel),
        moteur('deepseek', EtatMoteur::Operationnel),
        moteur('google_factcheck', EtatMoteur::Operationnel),
    ]);

    $this->artisan('verifall:diagnostic')
        ->doesntExpectOutputToContain('clé refusée')
        ->assertSuccessful();
});
