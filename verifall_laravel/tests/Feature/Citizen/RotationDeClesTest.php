<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\CleRefusee;
use App\Core\FactChecking\Services\QuotaEpuise;
use App\Core\FactChecking\Services\RotationDeCles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Rotation des clés d'API
|--------------------------------------------------------------------------
| Le quota gratuit de Gemini s'épuise en quelques heures : avec une seule clé,
| la plateforme s'arrête net au milieu de la journée.
*/

it('utilise la première clé disponible', function (): void {
    $utilisee = null;

    $resultat = (new RotationDeCles('test'))->essayer(['cle-a', 'cle-b'], function (string $cle) use (&$utilisee) {
        $utilisee = $cle;

        return 'ok';
    });

    expect($resultat)->toBe('ok')->and($utilisee)->toBe('cle-a');
});

it('passe à la clé suivante quand le quota est épuisé', function (): void {
    $essayees = [];

    $resultat = (new RotationDeCles('test'))->essayer(['cle-a', 'cle-b'], function (string $cle) use (&$essayees) {
        $essayees[] = $cle;

        if ($cle === 'cle-a') {
            throw new QuotaEpuise;
        }

        return 'ok';
    });

    expect($resultat)->toBe('ok')->and($essayees)->toBe(['cle-a', 'cle-b']);
});

it('met la clé épuisée au repos et ne la rappelle plus', function (): void {
    $rotation = new RotationDeCles('test');

    $rotation->essayer(['cle-a', 'cle-b'], function (string $cle) {
        if ($cle === 'cle-a') {
            throw new QuotaEpuise;
        }

        return 'ok';
    });

    $essayees = [];
    $rotation->essayer(['cle-a', 'cle-b'], function (string $cle) use (&$essayees) {
        $essayees[] = $cle;

        return 'ok';
    });

    // La clé au repos est sautée : la réessayer en boucle épuiserait le temps
    // de chaque analyse pour un refus connu d'avance.
    expect($essayees)->toBe(['cle-b']);
});

it('isole le disjoncteur par clé, pas par service', function (): void {
    $rotation = new RotationDeCles('test');

    $rotation->essayer(['cle-a', 'cle-b', 'cle-c'], function (string $cle) {
        if ($cle === 'cle-a') {
            throw new QuotaEpuise;
        }

        return 'ok';
    });

    // Une clé épuisée ne doit pas condamner les autres.
    expect($rotation->toutesAuRepos(['cle-a', 'cle-b', 'cle-c']))->toBeFalse();
});

it('signale quand plus aucune clé n\'est disponible', function (): void {
    $rotation = new RotationDeCles('test');

    $rotation->essayer(['cle-a', 'cle-b'], function (): never {
        throw new QuotaEpuise;
    });

    expect($rotation->toutesAuRepos(['cle-a', 'cle-b']))->toBeTrue();
});

it('rend null plutôt que de lever quand aucune clé n\'est configurée', function (): void {
    // Un moteur sans clé ne doit pas faire tomber l'analyse : il s'abstient.
    expect((new RotationDeCles('test'))->essayer([], fn (): string => 'jamais'))->toBeNull();
});

it('n\'écrit jamais une clé dans le journal', function (): void {
    $rotation = new RotationDeCles('test');

    $rotation->essayer(['cle-tres-secrete-a-ne-pas-journaliser'], function (): never {
        throw new QuotaEpuise;
    });

    $journal = file_exists(storage_path('logs/laravel.log'))
        ? file_get_contents(storage_path('logs/laravel.log'))
        : '';

    // Un journal se relit, se copie et s'exporte : seul le RANG y figure.
    expect($journal)->not->toContain('cle-tres-secrete-a-ne-pas-journaliser');
});

/*
|--------------------------------------------------------------------------
| Une clé MORTE n'est pas une clé saturée
|--------------------------------------------------------------------------
|
| Les deux étaient traitées ensemble : `refuserSiQuota()` levait `QuotaEpuise`
| sur 401, 403 ET 429. Elles n'ont pourtant rien à voir.
|
| Un quota (429) se recharge — cinq minutes de repos, puis on reprend. Une clé
| refusée (401/403) ne se recharge JAMAIS : elle a été révoquée, mal recopiée,
| ou n'a pas les droits demandés. La reposer cinq minutes revenait à la
| réessayer indéfiniment, toutes les cinq minutes, en la comptant comme saine
| dans la rotation — et rien ne le disait, le journal étant le même.
|
| La documentation de Gemini le pose en règle : « Do not retry on client errors
| (like 400 or 403) as they indicate issues like invalid API keys. »
|
*/

it('met une clé REFUSÉE au repos bien plus longtemps qu’un quota', function (): void {
    Cache::flush();

    (new RotationDeCles('refus'))->essayer(['morte'], function (): never {
        throw new CleRefusee;
    }, reposMinutes: 5);

    $reposCourt = (new RotationDeCles('quota'))->essayer(['saturee'], function (): never {
        throw new QuotaEpuise;
    }, reposMinutes: 5);

    expect($reposCourt)->toBeNull();

    // Juste après, les deux sont au repos.
    expect((new RotationDeCles('refus'))->toutesAuRepos(['morte']))->toBeTrue()
        ->and((new RotationDeCles('quota'))->toutesAuRepos(['saturee']))->toBeTrue();

    /*
     | Une demi-heure plus tard, tout se joue : le quota est rechargé et la clé
     | reprend son tour, la clé refusée dort toujours.
     |
     | C'est CE décalage qui compte, pas le fait d'être au repos. Sans lui, la
     | clé morte revient toutes les cinq minutes ralentir chaque analyse, en
     | étant comptée comme saine sur la page « Santé du système ».
     */
    $this->travel(30)->minutes();

    expect((new RotationDeCles('quota'))->toutesAuRepos(['saturee']))->toBeFalse()
        ->and((new RotationDeCles('refus'))->toutesAuRepos(['morte']))->toBeTrue();

    $this->travelBack();
});

it('journalise une clé refusée en ERREUR, et un quota en avertissement', function (): void {
    Cache::flush();
    Log::spy();

    (new RotationDeCles('refus'))->essayer(['morte'], function (): never {
        throw new CleRefusee;
    });

    (new RotationDeCles('quota'))->essayer(['saturee'], function (): never {
        throw new QuotaEpuise;
    });

    /*
     | Deux niveaux, parce que les deux situations n'appellent pas le même
     | geste : un quota se subit, une clé refusée se REMPLACE. Les journaliser
     | pareil laissait la seconde se noyer dans les avertissements de la
     | première — et personne ne remplaçait rien.
     */
    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'REFUSÉE'))
        ->once();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'mise au repos'))
        ->once();
});

it('ne fait JAMAIS figurer la clé elle-même dans le journal', function (): void {
    Cache::flush();
    Log::spy();

    (new RotationDeCles('secret'))->essayer(['AIzaSyUNSECRETQUINEDOITPASSORTIR'], function (): never {
        throw new CleRefusee;
    });

    // Un journal se relit, se copie, s'exporte et s'envoie au support. Seul le
    // RANG y a sa place.
    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $contexte): bool => ! str_contains(
            json_encode($contexte, JSON_THROW_ON_ERROR),
            'AIzaSyUNSECRET',
        ))
        ->once();
});
