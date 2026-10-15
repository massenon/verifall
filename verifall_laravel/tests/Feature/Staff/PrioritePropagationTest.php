<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotSignalementsContenu;
use App\Core\Community\Services\VitesseDePropagation;
use App\Models\ContentReport;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Ce qui se répand vite passe devant
|--------------------------------------------------------------------------
| La file de modération se vidait dans l'ordre d'arrivée. C'est équitable et
| c'est mauvais : une rumeur signalée par quinze personnes en deux heures
| attendait derrière un hors-sujet signalé la veille par une seule. Entre les
| deux, c'est la première qui circule pendant qu'on traite la seconde.
|
| Le seul signal HONNÊTE dont la base dispose est le nombre d'alertes sur un
| même contenu et leur étalement. Les compteurs de vues et de réponses sont
| des instantanés : ils disent combien, jamais à quelle allure.
*/

beforeEach(function (): void {
    $this->signaleur = User::factory()->create(['otp_verified_at' => now()]);
});

function contenuSignale(int $alertes, int $ilYAHeures = 1): Thread
{
    /*
     | `forceCreate` : le fixture pose `created_at` à la main pour simuler une
     | flambée, et ces colonnes ne sont pas dans `$fillable` — ce qui est juste,
     | puisqu'une requête ne doit jamais choisir sa propre date.
     */
    $sujet = Thread::query()->forceCreate([
        'uuid' => (string) Str::uuid(),
        'slug' => 'sujet-'.Str::random(8),
        'user_id' => test()->signaleur->id,
        'title' => 'Un sujet du forum',
        'body' => '<p>Contenu.</p>',
        'last_activity_at' => now(),
    ]);

    for ($i = 0; $i < $alertes; $i++) {
        ContentReport::query()->forceCreate([
            'uuid' => (string) Str::uuid(),
            // Des signaleurs DIFFÉRENTS : c'est ce qui distingue une rumeur qui
            // circule d'une personne qui insiste.
            'user_id' => User::factory()->create(['otp_verified_at' => now()])->id,
            'reportable_type' => Thread::class,
            'reportable_id' => $sujet->id,
            'reason' => 'desinformation',
            'status' => 'pending',
            'created_at' => now()->subHours($ilYAHeures)->addMinutes($i * 5),
            'updated_at' => now()->subHours($ilYAHeures)->addMinutes($i * 5),
        ]);
    }

    return $sujet;
}

describe('la mesure', function (): void {
    it('ne donne AUCUNE vitesse à une alerte isolée', function (): void {
        /*
         | `null`, et surtout pas zéro. Zéro dirait « ce contenu ne circule
         | pas », ce que personne ne sait ; `null` dit « on ne peut rien en
         | conclure », ce qui est exact.
         */
        expect(app(VitesseDePropagation::class)->parHeure(1, now()->subHour()))->toBeNull()
            ->and(app(VitesseDePropagation::class)->parHeure(3, null))->toBeNull();
    });

    it('calcule une cadence quand plusieurs alertes existent', function (): void {
        $cadence = app(VitesseDePropagation::class)->parHeure(6, now()->subHours(2));

        expect($cadence)->toBeFloat()->toBeGreaterThan(2.0)->toBeLessThan(4.0);
    });

    it("ne s'emballe PAS sur deux alertes quasi simultanées", function (): void {
        /*
         | Sans plancher, deux alertes à quelques secondes d'intervalle
         | donneraient une vitesse quasi infinie, et un seul signaleur qui
         | clique deux fois dominerait la file pour toujours.
         */
        $cadence = app(VitesseDePropagation::class)->parHeure(2, now());

        expect($cadence)->toBeLessThanOrEqual(12.0);
    });

    it('dit ce qu’elle a COMPTÉ, jamais une conclusion', function (): void {
        // C'est au modérateur de conclure ; l'écran lui donne de quoi le faire.
        $resume = app(VitesseDePropagation::class)->resume(5, now()->subHours(2));

        expect($resume)->toContain('5 alertes')
            ->and($resume)->toContain('/h')
            ->and(app(VitesseDePropagation::class)->resume(1, now()))->toBe('Une seule alerte');
    });
});

describe('la file', function (): void {
    it('met la FLAMBÉE devant, même si elle est plus récente', function (): void {
        /*
         | LE test de ce lot. Avant, le hors-sujet de la veille passait devant
         | la rumeur du matin, et c'est la rumeur qui circulait pendant ce
         | temps.
         */
        $ancienIsole = contenuSignale(alertes: 1, ilYAHeures: 20);
        $recenteFlambee = contenuSignale(alertes: 6, ilYAHeures: 2);

        $file = app(DepotSignalementsContenu::class)->paginer(null, null, 20);

        expect($file->first()->reportable_id)->toBe($recenteFlambee->id)
            ->and($file->last()->reportable_id)->toBe($ancienIsole->id);
    });

    it('classe les flambées entre elles par NOMBRE d’alertes', function (): void {
        contenuSignale(alertes: 3, ilYAHeures: 2);
        $laPlusSignalee = contenuSignale(alertes: 8, ilYAHeures: 2);

        $file = app(DepotSignalementsContenu::class)->paginer(null, null, 20);

        expect($file->first()->reportable_id)->toBe($laPlusSignalee->id);
    });

    it('garde l’ordre par ANCIENNETÉ hors flambée', function (): void {
        // Sans propagation mesurable, la règle d'avant s'applique : une file de
        // modération se vide par le bas.
        $leePlusVieux = contenuSignale(alertes: 1, ilYAHeures: 30);
        contenuSignale(alertes: 1, ilYAHeures: 3);

        $file = app(DepotSignalementsContenu::class)->paginer(null, null, 20);

        expect($file->first()->reportable_id)->toBe($leePlusVieux->id);
    });

    it('ne prend PAS une accumulation ancienne pour une flambée', function (): void {
        /*
         | Quinze signalements étalés sur trois mois décrivent un contenu
         | durablement gênant, pas une rumeur qui monte. Les deux méritent
         | d'être traités, pas dans le même ordre d'urgence.
         */
        $vieilleAccumulation = contenuSignale(
            alertes: 9,
            ilYAHeures: VitesseDePropagation::FENETRE_HEURES + 24,
        );
        $recenteFlambee = contenuSignale(alertes: 2, ilYAHeures: 1);

        $file = app(DepotSignalementsContenu::class)->paginer(null, null, 20);

        expect($file->first()->reportable_id)->toBe($recenteFlambee->id)
            ->and($vieilleAccumulation->id)->not->toBe($file->first()->reportable_id);
    });
});

describe("l'écran", function (): void {
    it('affiche la mesure, sans requête par ligne', function (): void {
        /*
         | Une seule requête agrégée pour toute la page. La demander ligne par
         | ligne coûterait une requête par signalement affiché, et
         | `preventLazyLoading` ne l'attraperait pas : ce n'est pas une relation.
         */
        contenuSignale(alertes: 4, ilYAHeures: 2);

        $flambees = app(VitesseDePropagation::class)->pourLesSignalements();

        expect($flambees)->toHaveCount(1)
            ->and(array_values($flambees)[0]['alertes'])->toBe(4);
    });

    it("n'annonce comme flambée QUE ce qui l'est", function (): void {
        contenuSignale(alertes: 1, ilYAHeures: 2);

        expect(app(VitesseDePropagation::class)->pourLesSignalements())->toBe([]);
    });
});
