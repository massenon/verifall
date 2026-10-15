<?php

declare(strict_types=1);

use App\Core\Economy\Services\EconomyService;
use App\Core\Gamification\Services\AttributionDesBadges;
use App\Core\Identity\Services\PreferencesService;
use App\Core\Lms\Services\ProgressionAcademie;
use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Enums\MotifMouvementUnite;
use App\Enums\PreferenceNotification;
use App\Enums\StatutInscription;
use App\Enums\TypeLecon;
use App\Enums\UserRole;
use App\Mail\CourrielDeGabarit;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Database\Seeders\GabaritsDEmailSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Les courriels câblés
|--------------------------------------------------------------------------
|
| Quatre envois viennent d'être branchés sur des déclencheurs qui existaient
| déjà : cours terminé, badge débloqué, unités créditées, contre-expertise
| tranchée.
|
| Ce que ces tests tiennent n'est pas « le courriel part » — c'est **quand il
| ne part PAS** :
|
|   · un envoi éteint au catalogue ne part pas ;
|   · une préférence individuelle coupée le retient, même si l'administration
|     a tout allumé ;
|   · une félicitation ne se rejoue pas à chaque relecture de la dernière leçon ;
|   · le bonus de bienvenue ne double pas le courriel de bienvenue.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(GabaritsDEmailSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);

    Mail::fake();
});

/** Un cours publié avec une seule leçon, et l'inscription qui va avec. */
function coursAvecInscription(User $citoyen, int $prime = 250): array
{
    $cours = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::random(6),
        'title' => 'Détecter un deepfake',
        'description' => 'Un cours de test suffisamment décrit pour passer la validation.',
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '1 h',
        'cost_units' => 0,
        'xp_reward' => $prime,
        'is_published' => true,
    ]);

    $lecon = Lesson::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'position' => 1,
        'title' => 'La seule leçon',
        'type' => TypeLecon::Texte->value,
        'minutes' => 5,
        'content' => '<p>Contenu.</p>',
    ]);

    Enrollment::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $citoyen->id,
        'course_id' => $cours->id,
        'status' => StatutInscription::EnCours->value,
        'progress' => 0,
        'paid_units' => 0,
    ]);

    return [$cours, $lecon];
}

// ══════════════════════════════════════════════════════════ cours terminé

it('félicite quand un cours atteint 100 %', function () {
    [$cours, $lecon] = coursAvecInscription($this->citoyen);

    LessonCompletion::create([
        'user_id' => $this->citoyen->id,
        'lesson_id' => $lecon->id,
        'seconds' => 600,
        'completed_at' => now(),
    ]);

    app(ProgressionAcademie::class)->rafraichir($this->citoyen, $cours);

    Mail::assertQueued(
        CourrielDeGabarit::class,
        fn (CourrielDeGabarit $m): bool => $m->cle === 'cours_termine',
    );
});

it('ne rejoue PAS la félicitation à chaque relecture', function () {
    /*
     | LE test qui compte. `rafraichir()` s'exécute à chaque leçon lue, chaque
     | quiz corrigé. Sans témoin, rouvrir la dernière leçon d'un cours fini
     | renverrait la félicitation à chaque passage.
     |
     | Le témoin est `xp_awarded_at`, relevé AVANT la transaction : il n'est posé
     | qu'une fois, là où `completed_at` reste vrai indéfiniment.
     */
    [$cours, $lecon] = coursAvecInscription($this->citoyen);

    LessonCompletion::create([
        'user_id' => $this->citoyen->id,
        'lesson_id' => $lecon->id,
        'seconds' => 600,
        'completed_at' => now(),
    ]);

    $progression = app(ProgressionAcademie::class);

    for ($i = 0; $i < 5; $i++) {
        $progression->rafraichir($this->citoyen->fresh(), $cours);
    }

    Mail::assertQueued(CourrielDeGabarit::class, 1);
});

it('respecte la préférence « rappels d’apprentissage »', function () {
    // Un interrupteur global n'est pas un consentement individuel.
    app(PreferencesService::class)->definir(
        $this->citoyen,
        PreferenceNotification::RappelsApprentissage,
        false,
    );

    [$cours, $lecon] = coursAvecInscription($this->citoyen);

    LessonCompletion::create([
        'user_id' => $this->citoyen->id,
        'lesson_id' => $lecon->id,
        'seconds' => 600,
        'completed_at' => now(),
    ]);

    app(ProgressionAcademie::class)->rafraichir($this->citoyen->fresh(), $cours);

    Mail::assertNotQueued(CourrielDeGabarit::class);
});

// ═══════════════════════════════════════════════════════ unités créditées

it('prévient d’un crédit d’unités', function () {
    app(EconomyService::class)->crediter(
        $this->citoyen,
        50,
        MotifMouvementUnite::DotationMensuelle,
    );

    Mail::assertQueued(
        CourrielDeGabarit::class,
        fn (CourrielDeGabarit $m): bool => $m->cle === 'unites_creditees'
            && $m->variables['unites'] === 50,
    );
});

it('ne prévient PAS d’un débit', function () {
    /*
     | Un débit, c'est une vérification qu'on vient de lancer soi-même, écran
     | ouvert. Le lui écrire ferait un courriel par usage du produit.
     */
    app(EconomyService::class)->crediter($this->citoyen, 50, MotifMouvementUnite::Recharge);
    Mail::fake();

    app(EconomyService::class)->debiter($this->citoyen, 1, MotifMouvementUnite::Verification);

    Mail::assertNotQueued(CourrielDeGabarit::class);
});

it('ne double PAS le courriel de bienvenue', function () {
    // Le bonus d'inscription est déjà annoncé par le message de bienvenue, qui
    // part au même instant. Deux messages en une seconde pour dire la même
    // chose est la meilleure façon d'apprendre à quelqu'un à nous filtrer.
    app(EconomyService::class)->crediter($this->citoyen, 5, MotifMouvementUnite::Bienvenue);

    Mail::assertNotQueued(CourrielDeGabarit::class);
});

// ══════════════════════════════════════════════════════════ badge éteint

it('n’envoie PAS le courriel de badge, livré éteint', function () {
    /*
     | Le câblage existe pour que l'administration puisse en décider ; il n'est
     | pas le comportement par défaut. Un courriel par badge est le premier pas
     | vers le filtre automatique, qui emportera ensuite ce qui compte.
     */
    expect(CatalogueDesCourriels::evenement('badge_debloque')['parDefautActif'])->toBeFalse();

    app(AttributionDesBadges::class)->recalculer($this->citoyen);

    Mail::assertNotQueued(
        CourrielDeGabarit::class,
        fn (CourrielDeGabarit $m): bool => $m->cle === 'badge_debloque',
    );
});

// ════════════════════════════════════════════════════════════ le catalogue

it('ne laisse AUCUN envoi déclaré sans câblage', function () {
    /*
     | Cette assertion en remplace deux, qui énuméraient à la main les clés
     | câblées et les autres. Elles ont dû être corrigées à CHAQUE lot — trois
     | fois — parce qu'une liste recopiée dans un test est une seconde source de
     | vérité, et qu'elle diverge dès qu'on touche à la première.
     |
     | Depuis que les vingt-quatre sont câblés, l'énumération était en outre
     | devenue tautologique : elle répétait `CatalogueDesCourriels::cles()`.
     |
     | La forme actuelle est PLUS forte et ne se périme pas. Elle dit la règle
     | plutôt que son résultat : un vingt-cinquième événement déclaré `porte` à
     | faux fera échouer ce test, ce qui oblige à le câbler — ou à écrire noir
     | sur blanc pourquoi on ne le fait pas. C'est exactement le piège que ce
     | chantier a corrigé six fois : du texte qu'on soigne et que rien n'expédie.
     */
    $sansCablage = array_keys(array_filter(
        CatalogueDesCourriels::EVENEMENTS,
        static fn (array $e): bool => ! $e['porte'],
    ));

    expect($sansCablage)->toBe([]);
});
