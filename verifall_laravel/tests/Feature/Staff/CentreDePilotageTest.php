<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotStatistiquesDuTableauDeBord;
use App\Core\Application\Services\CentreDePilotage;
use App\Core\Application\Statistiques\PeriodeDAnalyse;
use App\Enums\PeriodeTableauDeBord;
use App\Enums\UserRole;
use App\Livewire\Staff\TableauDeBord;
use App\Livewire\Staff\TableauDeBord\SectionAnalytique;
use App\Models\BlogPost;
use App\Models\ContentVisit;
use App\Models\HubArticle;
use App\Models\User;
use App\Models\Verification;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le centre de pilotage — refonte du tableau de bord (14 septembre 2026)
|--------------------------------------------------------------------------
|
| Quatre propriétés sont gardées ici, parce qu'aucune ne se voit en regardant
| une page qui répond 200 :
|
|   1. l'accès : la porte est ouverte à tout le personnel, les AGRÉGATS non ;
|   2. la période : un chiffre « sur 7 jours » compte bien 7 jours ;
|   3. l'honnêteté : un CTR n'apparaît que là où des clics sont mesurés ;
|   4. l'échec partiel : un bloc qui lève ne fait pas tomber la page.
*/

beforeEach(function (): void {
    Cache::flush();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['otp_verified_at' => now()]);
    $this->admin->assignRole(UserRole::Admin->value);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** Une session du personnel prête à l'emploi. */
function pilotageConnecte(User $membre)
{
    return test()->actingAs($membre, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp]);
}

/** Une vérification soumise à une date donnée. */
function verificationDatee(CarbonImmutable|Carbon $quand, string $statut = 'completed'): Verification
{
    $verification = Verification::query()->create([
        'uuid' => (string) Str::uuid(),
        'type' => 'text',
        'status' => $statut,
        'claim' => 'Affirmation '.Str::random(8),
        'content_fingerprint' => hash('sha256', Str::random(16)),
        'cost_units' => 0,
    ]);

    $verification->forceFill(['created_at' => $quand, 'completed_at' => $statut === 'completed' ? $quand->copy()->addMinutes(3) : null])->saveQuietly();

    return $verification;
}

function unMentor(): User
{
    $mentor = User::factory()->create(['otp_verified_at' => now()]);
    $mentor->assignRole(UserRole::Mentor->value);

    return $mentor;
}

describe("l'accès", function (): void {
    it("rend le centre de pilotage à l'administrateur", function (): void {
        pilotageConnecte($this->admin)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Centre de pilotage')
            ->assertSee('Alertes et santé de la plateforme')
            ->assertSee('Performance du fact-checking')
            ->assertSee('Blog & Actualités — analytics', escape: false)
            ->assertSee('data-tour="a-traiter"', escape: false);
    });

    it('renvoie vers la connexion du personnel toute session qui n’est pas staff', function (): void {
        /*
         | La route n'a pas de garde `can:` — c'est la PORTE du back-office,
         | assumée dans `MenuDuBackOffice::SANS_GARDE_ASSUMEE`. Un 403 y
         | connecterait quelqu'un sur une page vide. Le refus se fait donc au
         | guard : un citoyen, même connecté, est renvoyé.
         */
        $citoyen = User::factory()->create();
        $citoyen->assignRole(UserRole::Citoyen->value);

        test()->actingAs($citoyen, 'web')->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.login'));
    });

    it("ne montre pas au mentor les agrégats globaux qu'il n'a pas le droit de lire", function (): void {
        $mentor = unMentor();

        pilotageConnecte($mentor)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('Blog & Actualités — analytics', escape: false)
            ->assertDontSee('tdb-s-lms', escape: false);

        Livewire::actingAs($mentor, 'staff')
            ->test(TableauDeBord::class)
            ->assertViewHas('sectionsVisibles', fn (array $s): bool => ! isset($s['lms']) && ! isset($s['engagement']) && ! isset($s['editorial']));
    });

    it('revérifie le droit dans la section elle-même, pas seulement chez le parent', function (): void {
        // Une requête Livewire se forge : la section ne fait pas confiance à la page.
        Livewire::withoutLazyLoading()
            ->actingAs(unMentor(), 'staff')
            ->test(SectionAnalytique::class, ['section' => 'lms'])
            ->assertDontSee('Apprenants actifs')
            ->assertDontSee('Inscriptions et complétions');
    });
});

describe('la période', function (): void {
    it('compte exactement la fenêtre choisie, et la précédente de même durée', function (): void {
        CarbonImmutable::setTestNow('2026-09-14 12:00:00');
        $this->travelTo('2026-09-14 12:00:00');

        verificationDatee(now()->subDays(2));
        verificationDatee(now()->subDays(10));
        verificationDatee(now()->subDays(40));

        $depot = app(DepotStatistiquesDuTableauDeBord::class);

        $sept = $depot->verifications(PeriodeDAnalyse::depuis('7d'));
        $trente = $depot->verifications(PeriodeDAnalyse::depuis('30d'));

        expect($sept['soumises'])->toBe(1)
            ->and($sept['soumises_precedente'])->toBe(1)       // le J-10 tombe dans les 7 jours d'avant
            ->and($trente['soumises'])->toBe(2)
            ->and($trente['soumises_precedente'])->toBe(1)     // le J-40
            ->and($sept['serie'])->toHaveCount(7)
            ->and(array_sum($sept['serie']))->toBe(1)
            ->and($depot->verifications(PeriodeDAnalyse::depuis('90d'))['soumises'])->toBe(3);
    });

    it("ventile « aujourd'hui » par heure", function (): void {
        CarbonImmutable::setTestNow('2026-09-14 15:30:00');
        $this->travelTo('2026-09-14 15:30:00');

        verificationDatee(now()->setTime(9, 10));
        verificationDatee(now()->setTime(9, 45));

        $periode = PeriodeDAnalyse::depuis(PeriodeTableauDeBord::Aujourdhui->value);
        $serie = app(DepotStatistiquesDuTableauDeBord::class)->verifications($periode)['serie'];

        expect($periode->granularite())->toBe('heure')
            ->and($serie)->toHaveCount(16)
            ->and($serie[9])->toBe(2);
    });

    it('retombe sur 30 jours quand la période personnalisée est invalide', function (string $du, string $au) {
        $periode = PeriodeDAnalyse::depuis('custom', $du, $au);

        expect($periode->choix)->toBe(PeriodeTableauDeBord::TrenteJours);
    })->with([
        'inversée' => ['2026-09-10', '2026-09-01'],
        'illisible' => ['hier', '2026-09-01'],
        'date impossible' => ['2026-02-31', '2026-03-05'],
    ]);

    it('accepte une période personnalisée et la borne à la journée pleine', function (): void {
        CarbonImmutable::setTestNow('2026-09-14 12:00:00');

        $periode = PeriodeDAnalyse::depuis('custom', '2026-09-01', '2026-09-05');

        expect($periode->choix)->toBe(PeriodeTableauDeBord::Personnalisee)
            ->and($periode->jours())->toBe(5)
            ->and($periode->precedente()->debut->toDateString())->toBe('2026-08-27');
    });

    it("tient la période dans l'adresse, et une valeur inconnue ne casse rien", function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->withQueryParams(['periode' => 'n-importe-quoi'])
            ->test(TableauDeBord::class)
            ->assertOk()
            ->assertViewHas('periodeCalculee', fn (PeriodeDAnalyse $p): bool => $p->choix === PeriodeTableauDeBord::TrenteJours)
            ->set('periode', '7d')
            ->assertViewHas('periodeCalculee', fn (PeriodeDAnalyse $p): bool => $p->choix === PeriodeTableauDeBord::SeptJours);
    });
});

describe("l'honnêteté des mesures", function (): void {
    it("ne calcule JAMAIS de CTR pour le blog, qui n'a aucun clic mesuré", function (): void {
        BlogPost::query()->create([
            'uuid' => (string) Str::uuid(), 'slug' => 'un-billet', 'title' => 'Un billet',
            'excerpt' => 'Chapô.', 'body' => '<p>Corps.</p>', 'is_published' => true, 'published_at' => now(),
        ])->forceFill(['views' => 120])->saveQuietly();

        $article = HubArticle::query()->create([
            'uuid' => (string) Str::uuid(), 'slug' => 'une-actu', 'title' => 'Une actualité',
            'claim' => 'Rumeur', 'excerpt' => 'Démenti.', 'body' => '<p>Démenti.</p>', 'category' => 'Société',
            'verdict' => 'false', 'is_published' => true, 'published_at' => now(),
            'source_url' => 'https://www.gouv.bj/actualite/essai',
        ]);
        $article->forceFill(['views' => 200, 'source_clicks' => 10])->saveQuietly();

        $section = app(CentreDePilotage::class)->section('editorial', $this->admin, PeriodeDAnalyse::depuis('30d'));

        expect($section['cartes']['blog']['ctr_cumul'])->toBeNull()
            ->and($section['cartes']['blog']['donnees']['clics_cumul'])->toBeNull()
            ->and($section['cartes']['actualites']['ctr_cumul'])->toBe(5.0)
            ->and($section['cartes']['blog']['donnees']['partages'])->toBeNull();
    });

    it('compte les visites humaines de la période, pas les robots', function (): void {
        $article = HubArticle::query()->create([
            'uuid' => (string) Str::uuid(), 'slug' => 'actu-visitee', 'title' => 'Actu visitée',
            'claim' => 'Rumeur', 'excerpt' => 'Démenti.', 'body' => '<p>x</p>', 'category' => 'Société',
            'verdict' => 'false', 'is_published' => true, 'published_at' => now(),
        ]);

        foreach ([[now()->subDay(), false, 'a'], [now()->subDay(), false, 'b'], [now()->subDay(), true, 'c'], [now()->subDays(60), false, 'd']] as [$quand, $robot, $qui]) {
            ContentVisit::query()->create([
                'visitable_type' => $article->getMorphClass(), 'visitable_id' => $article->id,
                'visitor_hash' => hash('sha256', $qui), 'is_bot' => $robot, 'visited_at' => $quand,
            ]);
        }

        $edito = app(DepotStatistiquesDuTableauDeBord::class)->editorial(PeriodeDAnalyse::depuis('30d'));
        $top = app(DepotStatistiquesDuTableauDeBord::class)->contenusPerformants(PeriodeDAnalyse::depuis('30d'));

        expect($edito['actualites']['vues'])->toBe(2)
            ->and($edito['actualites']['visiteurs'])->toBe(2)
            ->and($top['classement'])->toBe('vues')
            ->and($top['lignes'][0]['slug'])->toBe('actu-visitee');
    });

    it("n'invente pas de variation quand la période précédente est vide", function (): void {
        $pilotage = app(CentreDePilotage::class);

        expect($pilotage->variation(12, 0))->toBeNull()
            ->and($pilotage->variation(12, 10))->toBe(20.0)
            ->and($pilotage->taux(3, 0))->toBeNull();
    });
});

describe("l'échec partiel", function (): void {
    beforeEach(function (): void {
        // Un dépôt qui lève à chaque lecture : la panne d'une base de statistiques.
        app()->bind(DepotStatistiquesDuTableauDeBord::class, fn () => new class implements DepotStatistiquesDuTableauDeBord
        {
            public function utilisateurs(PeriodeDAnalyse $periode): array
            {
                throw new RuntimeException('panne');
            }

            public function verifications(PeriodeDAnalyse $periode): array
            {
                throw new RuntimeException('panne');
            }

            public function verificationsEnAttente(): int
            {
                throw new RuntimeException('panne');
            }

            public function visites(PeriodeDAnalyse $periode): array
            {
                throw new RuntimeException('panne');
            }

            public function editorial(PeriodeDAnalyse $periode): array
            {
                throw new RuntimeException('panne');
            }

            public function contenusPerformants(PeriodeDAnalyse $periode, int $limite = 8): array
            {
                throw new RuntimeException('panne');
            }

            public function engagement(PeriodeDAnalyse $periode): array
            {
                throw new RuntimeException('panne');
            }

            public function lms(PeriodeDAnalyse $periode): array
            {
                throw new RuntimeException('panne');
            }

            public function evenementsRecents(array $sources, int $parSource = 5): array
            {
                throw new RuntimeException('panne');
            }

            public function oublier(): void {}
        });
    });

    it("rend l'état d'erreur du bloc au lieu de lever", function (): void {
        Livewire::withoutLazyLoading()
            ->actingAs($this->admin, 'staff')
            ->test(SectionAnalytique::class, ['section' => 'fact-checking'])
            ->assertOk()
            ->assertSee("n'a pas pu être chargé", escape: false)
            ->assertDontSee('panne');
    });

    it('garde la page debout, files et santé comprises, quand toutes les statistiques tombent', function (): void {
        Livewire::withoutLazyLoading();

        pilotageConnecte($this->admin)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('À traiter maintenant')
            ->assertSee('Santé du système')
            ->assertSee("n'a pas pu être chargé", escape: false);
    });
});
