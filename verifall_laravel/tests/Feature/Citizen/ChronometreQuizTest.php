<?php

declare(strict_types=1);

use App\Livewire\Citizen\PasserQuiz;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 | LE CHRONOMÈTRE DOIT CONTRAINDRE, SINON C'EST UN DÉCOR.
 |
 | Deux défauts se cumulaient, et ensemble ils annulaient la notion même de
 | temps imparti :
 |
 |  1. `mount()` posait le départ à CHAQUE montage. Appuyer sur F5 à cinq
 |     secondes de la fin remettait le compteur à zéro — côté client comme côté
 |     serveur — avec les réponses toujours en mémoire du composant.
 |
 |  2. `soumettre()` transmettait la durée pour l'ENREGISTRER, sans jamais la
 |     comparer à la limite. On pouvait rendre une copie une heure plus tard,
 |     à plein crédit.
 |
 | Trouvés par une revue de code. Aucun test ne les couvrait : la suite passait
 | des quiz instantanément, où la question du temps ne se pose pas.
 */

function quizChronometre(int $minutes = 10): array
{
    $apprenant = User::factory()->create(['otp_verified_at' => now()]);

    $cours = Course::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::lower(Str::random(6)),
        'title' => 'Un cours',
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

    $quiz = Quiz::query()->create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'title' => 'Le quiz',
        'position' => 1,
        'minutes' => $minutes,
        'pass_score' => 50,
        'xp_reward' => 50,
    ]);

    $question = Question::query()->create([
        'uuid' => (string) Str::uuid(),
        'quiz_id' => $quiz->id,
        'position' => 1,
        'prompt' => 'Une question ?',
    ]);

    return [$apprenant, $cours, $quiz, $question];
}

describe('le départ du chronomètre', function (): void {
    it('ne se réinitialise PAS quand on remonte l’écran', function (): void {
        /*
         | LE défaut principal. Chaque montage écrasait le départ : F5 rendait
         | le temps infini, sans rien changer aux réponses déjà saisies.
         */
        [$apprenant, $cours, $quiz] = quizChronometre();

        Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid]);

        $premierDepart = session()->get('quiz.debut.'.$quiz->uuid);
        expect($premierDepart)->not->toBeNull();

        // Cinq minutes passent, puis on recharge la page.
        $this->travel(5)->minutes();

        Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid]);

        expect(session()->get('quiz.debut.'.$quiz->uuid))->toBe($premierDepart);
    });
});

describe('la soumission', function (): void {
    it('accepte une copie rendue dans les temps', function (): void {
        [$apprenant, $cours, $quiz, $question] = quizChronometre(10);

        $composant = Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid]);

        $this->travel(3)->minutes();

        $composant->call('soumettre');

        expect(QuizAttempt::where('user_id', $apprenant->id)->count())->toBe(1);
    });

    it('REFUSE une copie rendue après le temps imparti', function (): void {
        /*
         | Le cœur du correctif. Sans lui, cette copie était corrigée à plein
         | crédit — les réponses étant restées côté client, il suffisait
         | d'attendre pour les chercher ailleurs.
         */
        [$apprenant, $cours, $quiz] = quizChronometre(10);

        $composant = Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid]);

        // Une heure plus tard, sur un quiz de dix minutes.
        $this->travel(60)->minutes();

        $composant->call('soumettre');

        expect(QuizAttempt::where('user_id', $apprenant->id)->count())->toBe(0);
    });

    it('accorde une marge au trajet réseau', function (): void {
        /*
         | Une soumission voyage. Sur une connexion 3G, ce trajet se compte en
         | secondes — sans marge, on punirait la connexion de quelqu'un plutôt
         | que son retard.
         */
        [$apprenant, $cours, $quiz] = quizChronometre(1);

        $composant = Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid]);

        // 1 min 20 s sur un quiz d'une minute : dans la marge de 30 s.
        $this->travel(80)->seconds();

        $composant->call('soumettre');

        expect(QuizAttempt::where('user_id', $apprenant->id)->count())->toBe(1);
    });

    it('libère le chronomètre après la remise', function (): void {
        /*
         | Sans cette purge, le correctif du « départ unique » aurait fermé la
         | porte dans l'autre sens : une seconde tentative aurait hérité du
         | chronomètre de la première et serait née expirée.
         */
        [$apprenant, $cours, $quiz] = quizChronometre();

        Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid])
            ->call('soumettre');

        expect(session()->has('quiz.debut.'.$quiz->uuid))->toBeFalse();
    });
});

describe('un quiz sans durée déclarée', function (): void {
    it("n'oppose aucune échéance", function (): void {
        // Zéro minute veut dire « pas de limite », pas « limite immédiate ».
        // Traiter l'absence comme une contrainte rendrait tout quiz sans durée
        // impossible à rendre.
        [$apprenant, $cours, $quiz] = quizChronometre(0);

        $composant = Livewire::actingAs($apprenant)
            ->test(PasserQuiz::class, ['course' => $cours->slug, 'quiz' => $quiz->uuid]);

        $this->travel(3)->hours();

        $composant->call('soumettre');

        expect(QuizAttempt::where('user_id', $apprenant->id)->count())->toBe(1);
    });
});
