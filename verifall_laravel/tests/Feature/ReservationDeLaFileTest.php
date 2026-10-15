<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| La réservation de la file couvre-t-elle le job le plus long ?
|--------------------------------------------------------------------------
| `retry_after` n'est pas un délai avant nouvelle tentative : c'est la durée
| pendant laquelle la file garde un job RÉSERVÉ. Passé ce temps, elle le juge
| abandonné et le rend à un autre worker — sans vérifier que le premier a fini.
|
| Un job dont le `$timeout` dépasse cette réservation sera donc un jour exécuté
| DEUX FOIS EN MÊME TEMPS. Ce n'est pas une hypothèse : avec la valeur livrée
| par Laravel (90 s) contre les 180 s d'`AnalyserVerification`, la production a
| rendu un « Duplicate entry for key analysis_reports_verification_id_unique ».
| Deux passages avaient interrogé les moteurs, deux passages ont écrit le
| rapport, le second s'est écrasé sur la contrainte — après avoir payé.
|
| Rien ne rappelle cet invariant au moment où l'on ajoute un job long. Ce
| fichier s'en charge.
*/

/**
 * Le plus grand `$timeout` déclaré, avec le nom du job qui le porte.
 *
 * @return array{0: string, 1: int}
 */
function jobLePlusLong(): array
{
    $trouve = ['aucun', 0];

    foreach (glob(app_path('Jobs').'/*.php') ?: [] as $fichier) {
        $classe = 'App\\Jobs\\'.basename($fichier, '.php');

        if (! class_exists($classe) || ! property_exists($classe, 'timeout')) {
            continue;
        }

        $timeout = (int) (new ReflectionClass($classe))->getDefaultProperties()['timeout'];

        if ($timeout > $trouve[1]) {
            $trouve = [class_basename($classe), $timeout];
        }
    }

    return $trouve;
}

it('réserve les jobs plus longtemps que le plus long de tous', function (): void {
    [$nom, $timeout] = jobLePlusLong();

    $reservation = (int) config('queue.connections.database.retry_after');

    expect($timeout)->toBeGreaterThan(0, 'Aucun job ne déclare de $timeout : le test ne prouve rien.')
        ->and($reservation)->toBeGreaterThan(
            $timeout,
            "retry_after ({$reservation} s) ne couvre pas {$nom} ({$timeout} s) : "
            .'ce job peut être exécuté deux fois en parallèle.',
        );
});

it('laisse une marge, et pas seulement une seconde', function (): void {
    [, $timeout] = jobLePlusLong();

    /*
     | Une marge d'une seconde tiendrait sur le papier et pas en production :
     | entre le moment où la file rend le job et celui où le worker s'en
     | aperçoit, il s'écoule du temps. Cinq minutes est le pas retenu dans
     | `config/queue.php`.
     */
    expect((int) config('queue.connections.database.retry_after') - $timeout)
        ->toBeGreaterThanOrEqual(300);
});

describe('le diagnostic le dit', function (): void {
    it('passe au vert quand la réservation couvre le job le plus long', function (): void {
        // La suite tourne en `sync` : sans ce réglage, la section se contente
        // de dire qu'elle ne sait pas lire cette file.
        config()->set('queue.default', 'database');

        $this->artisan('verifall:diagnostic')
            ->expectsOutputToContain('La réservation couvre le job le plus long.');
    });

    it('vire au rouge sur la valeur livrée par Laravel', function (): void {
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.retry_after', 90);

        $this->artisan('verifall:diagnostic')
            ->expectsOutputToContain('peut tourner deux fois en parallèle')
            ->assertFailed();
    });
});
