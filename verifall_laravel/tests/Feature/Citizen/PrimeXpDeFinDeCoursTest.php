<?php

declare(strict_types=1);

use App\Core\Lms\Services\ProgressionAcademie;
use App\Enums\StatutInscription;
use App\Enums\TypeLecon;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| La prime d'XP de fin de cours
|--------------------------------------------------------------------------
|
| La colonne `courses.xp_reward` et son champ existaient depuis le lot 14 —
| et **rien ne versait la prime**. Elle est branchée sur l'entonnoir unique de
| l'avancement, `ProgressionAcademie::rafraichir()`.
|
| La propriété qui compte n'est pas « la prime est versée » : c'est **elle ne
| l'est qu'une fois**. `rafraichir()` s'exécute à chaque leçon lue, chaque quiz
| corrigé, chaque exercice remis ; une personne qui relit une leçon après avoir
| fini repasse par la branche d'achèvement.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['xp' => 0])->save();

    $this->cours = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-a-prime',
        'title' => 'Un cours qui récompense',
        'description' => 'Un cours de test suffisamment décrit pour passer la validation.',
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '1 h',
        'cost_units' => 0,
        'xp_reward' => 250,
        'delivers_certificate' => false,
        'is_published' => true,
    ]);

    $this->lecon = Lesson::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $this->cours->id,
        'position' => 1,
        'title' => 'La seule leçon',
        'type' => TypeLecon::Texte->value,
        'minutes' => 5,
        'content' => '<p>Contenu.</p>',
    ]);

    $this->inscription = Enrollment::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $this->citoyen->id,
        'course_id' => $this->cours->id,
        'status' => StatutInscription::EnCours->value,
        'progress' => 0,
        'paid_units' => 0,
    ]);
});

/** Marque la seule leçon comme lue, ce qui porte le cours à 100 %. */
function acheverLeCours(User $citoyen, Lesson $lecon): void
{
    LessonCompletion::updateOrCreate(
        ['user_id' => $citoyen->id, 'lesson_id' => $lecon->id],
        ['seconds' => 600, 'completed_at' => now()],
    );
}

it('verse la prime à 100 % d’avancement', function () {
    acheverLeCours($this->citoyen, $this->lecon);

    app(ProgressionAcademie::class)->rafraichir($this->citoyen, $this->cours);

    expect($this->citoyen->fresh()->xp)->toBe(250)
        ->and($this->inscription->fresh()->xp_awarded_at)->not->toBeNull()
        ->and($this->inscription->fresh()->progress)->toBe(100);
});

it('ne la verse PAS deux fois, même après dix rafraîchissements', function () {
    /*
     | LE TEST QUI COMPTE. `rafraichir()` s'exécute à chaque leçon lue, chaque
     | quiz corrigé, chaque exercice remis. Sans la marque, cinq relectures d'un
     | cours à 250 XP donneraient 1 250 XP — deux paliers de niveau gagnés en
     | rouvrant une page.
     */
    acheverLeCours($this->citoyen, $this->lecon);

    $progression = app(ProgressionAcademie::class);

    for ($i = 0; $i < 10; $i++) {
        $progression->rafraichir($this->citoyen->fresh(), $this->cours);
    }

    expect($this->citoyen->fresh()->xp)->toBe(250);
});

it('ne verse rien tant que le cours n’est pas terminé', function () {
    // Aucune leçon lue : l'avancement est nul, la prime attend.
    app(ProgressionAcademie::class)->rafraichir($this->citoyen, $this->cours);

    expect($this->citoyen->fresh()->xp)->toBe(0)
        ->and($this->inscription->fresh()->xp_awarded_at)->toBeNull();
});

it('pose la marque MÊME quand la prime vaut zéro', function () {
    /*
     | Elle enregistre que le moment de l'attribution est PASSÉ, pas qu'une
     | somme a été versée. C'est ce qui empêche un paiement rétroactif quand un
     | mentor fixe la récompense après coup — voir le test suivant.
     */
    $this->cours->forceFill(['xp_reward' => 0])->save();

    acheverLeCours($this->citoyen, $this->lecon);
    app(ProgressionAcademie::class)->rafraichir($this->citoyen, $this->cours->fresh());

    expect($this->citoyen->fresh()->xp)->toBe(0)
        ->and($this->inscription->fresh()->xp_awarded_at)->not->toBeNull();
});

it('ne paie pas RÉTROACTIVEMENT une prime relevée après coup', function () {
    /*
     | La prime est celle qui était annoncée quand la personne a suivi le cours.
     | C'est la seule règle qu'on puisse lui expliquer — et elle protège aussi
     | d'une inflation d'XP qu'un simple changement de fiche déclencherait sur
     | tous les anciens inscrits.
     */
    $this->cours->forceFill(['xp_reward' => 0])->save();

    acheverLeCours($this->citoyen, $this->lecon);
    $progression = app(ProgressionAcademie::class);
    $progression->rafraichir($this->citoyen, $this->cours->fresh());

    // Le mentor relève la prime après coup.
    $this->cours->forceFill(['xp_reward' => 500])->save();
    $progression->rafraichir($this->citoyen->fresh(), $this->cours->fresh());

    expect($this->citoyen->fresh()->xp)->toBe(0);
});

it('verse la prime même si le cours ne délivre PAS de certificat', function () {
    // Récompenser et diplômer sont deux gestes distincts : ils partagent un
    // déclencheur, pas une condition.
    expect($this->cours->delivers_certificate)->toBeFalse();

    acheverLeCours($this->citoyen, $this->lecon);
    app(ProgressionAcademie::class)->rafraichir($this->citoyen, $this->cours);

    expect($this->citoyen->fresh()->xp)->toBe(250);
});

it('n’ajoute pas la prime d’un autre cours au passage', function () {
    $autre = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'un-cours-voisin',
        'title' => 'Un cours voisin',
        'description' => 'Un second cours, avec sa propre récompense annoncée.',
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '1 h',
        'cost_units' => 0,
        'xp_reward' => 999,
        'is_published' => true,
    ]);

    Enrollment::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $this->citoyen->id,
        'course_id' => $autre->id,
        'status' => StatutInscription::EnCours->value,
        'progress' => 0,
        'paid_units' => 0,
    ]);

    acheverLeCours($this->citoyen, $this->lecon);
    app(ProgressionAcademie::class)->rafraichir($this->citoyen, $this->cours);

    // Seule la prime du cours achevé est versée.
    expect($this->citoyen->fresh()->xp)->toBe(250);
});

/*
|--------------------------------------------------------------------------
| Ce que les écrans en DISENT
|--------------------------------------------------------------------------
|
| La prime était enregistrée depuis le lot 14 et **aucun gabarit ne la montrait** :
| le quiz affiche la sienne, le badge la sienne, le cours restait muet. Une
| récompense qu'on ne promet pas ne motive personne, et une récompense versée
| sans un mot ne se remarque pas.
|
| Même motif pour les **prérequis** : saisis par le mentor, jamais affichés,
| là où les objectifs l'étaient. Ces trois tests-là tiennent l'affichage, que les
| tests de service ne peuvent pas voir.
|
*/

it('annonce la prime sur la fiche du cours', function () {
    $this->actingAs($this->citoyen)
        ->get(route('citizen.course', ['course' => $this->cours]))
        ->assertOk()
        // La MÊME chaîne que le test suivant nie : c'est le couple qui rend
        // l'assertion discriminante. « +250 » seul pourrait venir d'ailleurs.
        ->assertSee('+250')
        ->assertSee('XP à la fin');
});

it('n’annonce rien quand le cours ne récompense pas', function () {
    $this->cours->forceFill(['xp_reward' => 0])->save();

    $this->actingAs($this->citoyen)
        ->get(route('citizen.course', ['course' => $this->cours]))
        ->assertOk()
        ->assertDontSee('XP à la fin');
});

it('affiche les prérequis du cours', function () {
    // Ils étaient stockés et invisibles : on découvrait à la troisième leçon
    // qu'il manquait une base.
    $this->cours->forceFill([
        'prerequisites' => ['Savoir ouvrir un navigateur', 'Un compte WhatsApp'],
    ])->save();

    $this->actingAs($this->citoyen)
        ->get(route('citizen.course', ['course' => $this->cours]))
        ->assertOk()
        ->assertSee('Savoir ouvrir un navigateur')
        ->assertSee('Un compte WhatsApp');
});
