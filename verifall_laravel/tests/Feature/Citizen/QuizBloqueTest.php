<?php

declare(strict_types=1);

use App\Livewire\Citizen\Evaluations;
use App\Livewire\Citizen\PasserQuiz;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| B-05 — « Passer le quiz » ne doit plus mener à une page d'erreur nue
|--------------------------------------------------------------------------
| La RÈGLE est juste et reste en place : un quiz vérifie ce que le cours a
| enseigné, il ne s'ouvre donc qu'une fois les leçons lues. C'est la RÉPONSE
| qui était fautive — `abort(403)`, une page sans phrase, sans indication de
| ce qui manque, sans lien pour y aller. Alors même que le serveur sait
| exactement quelle leçon bloque.
|
| Ce fichier éprouve les deux moitiés du correctif :
|
|   · l'écran explique au lieu de refuser (et n'ouvre toujours pas le quiz) ;
|   · le bouton qui menait à l'impasse ne s'affiche plus.
|
| Le dernier test est le garde-fou : il prouve que la règle n'a PAS été levée
| au passage. Rendre un écran aimable en supprimant la contrainte qu'il
| explique serait la pire des corrections.
*/

/**
 * Un cours avec une leçon et un quiz, et quelqu'un d'inscrit.
 *
 * @return array{0: User, 1: Course, 2: Lesson, 3: Quiz}
 */
function coursAvecLeconEtQuiz(): array
{
    $apprenant = User::factory()->create(['otp_verified_at' => now()]);

    $cours = Course::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::lower(Str::random(6)),
        'title' => 'Reconnaître une image sortie de son contexte',
        'description' => 'Description.',
        'category' => 'investigation',
        'level' => 'beginner',
        'duration_label' => '30 min',
        'cost_units' => 0,
        'outcomes' => ['Un acquis'],
        'delivers_certificate' => false,
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);

    Enrollment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $apprenant->id,
        'course_id' => $cours->id,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    $lecon = Lesson::query()->create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'position' => 1,
        'title' => 'La recherche d\'image inversée',
        'type' => 'text',
        'minutes' => 5,
        'content' => 'Contenu.',
    ]);

    $quiz = Quiz::query()->create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'title' => 'Le quiz',
        'position' => 1,
        'minutes' => 10,
        'pass_score' => 50,
        'xp_reward' => 50,
    ]);

    Question::query()->create([
        'uuid' => (string) Str::uuid(),
        'quiz_id' => $quiz->id,
        'position' => 1,
        'prompt' => 'Une question ?',
    ]);

    return [$apprenant, $cours, $lecon, $quiz];
}

/** Marque la leçon comme lue, ce qui ouvre le quiz. */
function leconLue(User $apprenant, Lesson $lecon): void
{
    LessonCompletion::query()->create([
        'user_id' => $apprenant->id,
        'lesson_id' => $lecon->id,
        'completed_at' => now(),
    ]);
}

describe('quand une leçon reste à lire', function (): void {
    it('répond 200 et explique, au lieu de renvoyer un 403 nu', function (): void {
        [$apprenant, $cours, , $quiz] = coursAvecLeconEtQuiz();

        Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid])
            ->assertOk()
            ->assertSet('blocage', 'lecons-restantes')
            ->assertSee('Ce quiz n\'est pas encore ouvert');
    });

    it('nomme la leçon qui bloque et offre le lien pour y aller', function (): void {
        /*
         | Le cœur du correctif. `prochaineLecon()` rendait déjà cette leçon au
         | moment même où l'ancien code abandonnait : l'information existait, on
         | la jetait.
         */
        [$apprenant, $cours, $lecon, $quiz] = coursAvecLeconEtQuiz();

        Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid])
            ->assertSee($lecon->title)
            ->assertSee(route('citizen.lesson', ['course' => $cours, 'lesson' => $lecon]), escape: false);
    });

    it('n\'ouvre PAS le quiz pour autant — la règle tient', function (): void {
        /*
         | Le garde-fou. Rendre l'écran aimable en levant la contrainte qu'il
         | explique serait pire que le 403 : le quiz s'ouvrirait avant le cours.
         */
        [$apprenant, $cours, , $quiz] = coursAvecLeconEtQuiz();

        Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid])
            ->assertDontSee('Une question ?');
    });
});

describe('quand tout est lu', function (): void {
    it('ouvre le quiz normalement', function (): void {
        [$apprenant, $cours, $lecon, $quiz] = coursAvecLeconEtQuiz();

        leconLue($apprenant, $lecon);

        Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid])
            ->assertOk()
            ->assertSet('blocage', null)
            ->assertSee('Une question ?');
    });
});

describe('quand on n\'est pas inscrit au cours', function (): void {
    it('explique au lieu de refuser', function (): void {
        [, $cours, , $quiz] = coursAvecLeconEtQuiz();

        $etranger = User::factory()->create(['otp_verified_at' => now()]);

        Livewire::actingAs($etranger)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid])
            ->assertOk()
            ->assertSet('blocage', 'non-inscrit')
            ->assertDontSee('Une question ?');
    });
});

describe('le bouton qui menait à l\'impasse', function (): void {
    it('ne propose plus « Passer le quiz » tant que les leçons ne sont pas lues', function (): void {
        /*
         | L'autre moitié du défaut. `<x-evaluation.action-quiz>` ne consultait
         | que « inscrit » : il offrait donc le bouton à qui n'avait pas fini le
         | cours, et le clic tombait sur le refus. Les deux appelants de
         | `cours.blade.php` tenaient déjà ce compte ; celui-ci non.
         */
        [$apprenant, $cours, , $quiz] = coursAvecLeconEtQuiz();

        Livewire::actingAs($apprenant)
            ->test(Evaluations::class)
            ->assertDontSee(route('citizen.quiz.take', ['course' => $cours, 'quiz' => $quiz]), escape: false);
    });

    it('le propose dès que le cours est parcouru', function (): void {
        [$apprenant, $cours, $lecon, $quiz] = coursAvecLeconEtQuiz();

        leconLue($apprenant, $lecon);

        Livewire::actingAs($apprenant)
            ->test(Evaluations::class)
            ->assertSee(route('citizen.quiz.take', ['course' => $cours, 'quiz' => $quiz]), escape: false);
    });
});
