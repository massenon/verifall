<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Str;

/*
 | LE CONTOURNEMENT QUE CE FICHIER EMPÊCHE DE REVENIR.
 |
 | L'API de l'Académie vérifiait la publication du cours et l'ouverture
 | séquentielle des leçons — mais jamais l'INSCRIPTION. Or `leconOuverte()`
 | rend `true` sans condition pour la première leçon.
 |
 | Le cours payant entier était donc accessible sans débiter une unité :
 |
 |   1. `GET /courses/{slug}` donne les UUID de toutes les leçons ;
 |   2. `GET .../lessons/{uuid}` rend le contenu intégral de la première ;
 |   3. `POST /me/lessons/{uuid}/complete` la valide et ouvre la suivante ;
 |   4. on répète jusqu'à la fin.
 |
 | `POST /enroll` — le seul endroit qui fait payer — devenait facultatif.
 |
 | Le chemin WEB posait pourtant la garde depuis toujours
 | (`Lecon.php` : `abort_unless($this->inscription() !== null, 403)`). Deux
 | portes vers le même contenu, deux niveaux d'exigence.
 |
 | Les 24 tests d'API existants ne l'ont pas vu : ils s'inscrivent tous
 | d'abord. Un contournement ne se voit que si on l'essaie.
 */

function coursPayantAvecContenu(): array
{
    $cours = Course::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-payant-'.Str::random(6),
        'title' => 'Un cours payant',
        'description' => 'Description.',
        'category' => 'investigation',
        'level' => 'beginner',
        'duration_label' => '30 min',
        'cost_units' => 50,
        'outcomes' => ['Un acquis'],
        'delivers_certificate' => false,
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);

    $lecon = Lesson::query()->create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'position' => 1,
        'title' => 'La toute première leçon',
        'type' => 'text',
        'minutes' => 5,
        'content' => '<p>Le contenu payant qui ne doit pas fuir.</p>',
    ]);

    return [$cours, $lecon];
}

describe('sans inscription', function (): void {
    beforeEach(function (): void {
        $this->intrus = User::factory()->create(['otp_verified_at' => now()]);
        $this->jeton = $this->intrus->createToken('téléphone')->plainTextToken;

        [$this->cours, $this->lecon] = coursPayantAvecContenu();
    });

    it('ne rend PAS le contenu de la première leçon', function (): void {
        /*
         | La première leçon est le maillon d'entrée : `leconOuverte()` la
         | déclare ouverte pour tout le monde, puisqu'aucune leçon ne la
         | précède. C'est exactement là que la garde manquait.
         */
        $reponse = $this->withToken($this->jeton)->getJson("/api/v1/courses/{$this->cours->slug}/lessons/{$this->lecon->uuid}");

        $reponse->assertForbidden();

        expect($reponse->getContent())->not->toContain('ne doit pas fuir');
    });

    it('ne laisse PAS valider une leçon, donc ne déverrouille pas la suivante', function (): void {
        $this->withToken($this->jeton)->postJson("/api/v1/me/lessons/{$this->lecon->uuid}/complete", ['secondes' => 120])
            ->assertForbidden();

        // Rien n'a été enregistré : la progression reste vierge.
        expect(LessonCompletion::where('user_id', $this->intrus->id)->count())->toBe(0);
    });

    it("ne laisse PAS passer un quiz, donc ne distribue pas d'XP", function (): void {
        /*
         | Le volet le plus rentable pour un attaquant : `corriger()` incrémente
         | l'XP à la première réussite. Sans garde, on parcourait le catalogue en
         | moissonnant l'XP de tous les quiz — niveaux et classement faussés,
         | sans jamais rien payer.
         */
        $quiz = Quiz::query()->create([
            'uuid' => (string) Str::uuid(),
            'course_id' => $this->cours->id,
            'title' => 'Un quiz',
            'position' => 1,
            'pass_score' => 70,
            'xp_reward' => 100,
        ]);

        $xpAvant = $this->intrus->fresh()->xp;

        $this->withToken($this->jeton)->postJson("/api/v1/me/quiz/{$quiz->uuid}/attempt", ['reponses' => []])
            ->assertForbidden();

        expect($this->intrus->fresh()->xp)->toBe($xpAvant);
    });
});

describe('avec inscription', function (): void {
    it('ouvre bien la première leçon — la garde ne ferme pas le cas légitime', function (): void {
        /*
         | Le pendant indispensable. Une correction qui fermerait aussi l'accès
         | des inscrits transformerait une faille en panne, ce qui n'est pas un
         | progrès.
         */
        $apprenant = User::factory()->create(['otp_verified_at' => now()]);
        $jeton = $apprenant->createToken('téléphone')->plainTextToken;

        [$cours, $lecon] = coursPayantAvecContenu();

        Enrollment::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $apprenant->id,
            'course_id' => $cours->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->withToken($jeton)->getJson("/api/v1/courses/{$cours->slug}/lessons/{$lecon->uuid}")
            ->assertOk();
    });
});
