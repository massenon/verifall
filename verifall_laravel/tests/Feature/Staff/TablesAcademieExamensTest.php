<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Core\Application\Contracts\DepotExamens;
use App\Core\Application\Contracts\DepotInscriptionsExamen;
use App\Enums\UserRole;
use App\Livewire\Staff\BanqueDeQuestions;
use App\Livewire\Staff\CorrectionExamens;
use App\Livewire\Staff\EnrolementAuxExamens;
use App\Livewire\Staff\Examens;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\QuestionBankItem;
use App\Models\QuestionBankOption;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Académie / Examens — quatre tables DataTables côté serveur (14 sept. 2026)
|--------------------------------------------------------------------------
|
| Banque de questions, Liste des examens, Correction, Enrôlement aux examens.
| Ces quatre écrans sont ouverts au MENTOR. Le point qui compte le plus est
| donc le PÉRIMÈTRE : la source JSON ne doit pas rendre à un mentor une seule
| ligne que la liste Livewire ne lui montrait pas.
|
| Constat, vérifié dans le code avant la migration : aucune de ces listes ne
| bornait le mentor. `QuestionBankItemPolicy` et `ExamPolicy` écartent
| explicitement la borne « ses ressources » (fonds commun, jury), la file de
| correction montrait les copies de tous les correcteurs, et l'enrôlement
| lisait n'importe quelle session. Les tests de périmètre comparent donc la
| réponse JSON, pour deux mentors ET un administrateur, aux méthodes de liste
| que les composants appelaient (`lister()`, `fileDeCorrection()`) — toujours
| présentes dans les dépôts. Ce qui distingue le mentor, ce sont les ACTIONS.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create(['name' => 'Admin Principal']);
    $this->admin->assignRole(UserRole::Admin->value);

    $this->mentorA = User::factory()->actif()->create(['name' => 'Mentor Awa']);
    $this->mentorA->assignRole(UserRole::Mentor->value);

    $this->mentorB = User::factory()->actif()->create(['name' => 'Mentor Bio']);
    $this->mentorB->assignRole(UserRole::Mentor->value);
});

// ═══════════════════════════════════════════════════════════════ fabriques

function acCours(?User $auteur = null, string $titre = 'Cours'): Course
{
    return Course::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::lower(Str::random(8)),
        'title' => $titre,
        'description' => str_repeat('Contenu. ', 8),
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '2 h',
        'cost_units' => 0,
        'instructor_id' => $auteur?->id,
        'is_published' => true,
    ]);
}

/** @param array<string, mixed> $attributs */
function acQuestion(array $attributs = []): QuestionBankItem
{
    return QuestionBankItem::query()->forceCreate($attributs + [
        'uuid' => (string) Str::uuid(),
        'type' => 'qcm',
        'prompt' => 'Question '.Str::random(6),
        'points' => 10,
        'status' => 'draft',
    ]);
}

/** @param array<string, mixed> $attributs */
function acExamen(array $attributs = []): Exam
{
    return Exam::query()->forceCreate($attributs + [
        'uuid' => (string) Str::uuid(),
        'slug' => 'examen-'.Str::lower(Str::random(8)),
        'title' => 'Épreuve '.Str::random(6),
        'pass_score' => 70,
        'duration_minutes' => 60,
        'max_attempts' => 2,
        'status' => 'published',
    ]);
}

/** @param array<string, mixed> $attributs */
function acSession(Exam $examen, array $attributs = []): ExamSession
{
    return ExamSession::query()->forceCreate($attributs + [
        'uuid' => (string) Str::uuid(),
        'exam_id' => $examen->id,
        'starts_at' => now()->addWeek()->addMinutes(random_int(1, 50000)),
        'seats' => 30,
        'status' => 'open',
    ]);
}

/** @param array<string, mixed> $attributs */
function acCopie(Exam $examen, array $attributs = []): ExamAttempt
{
    return ExamAttempt::query()->forceCreate($attributs + [
        'uuid' => (string) Str::uuid(),
        'reference' => 'EX-'.Str::upper(Str::random(8)),
        'exam_id' => $examen->id,
        'user_id' => User::factory()->create()->id,
        'attempt_no' => 1,
        'status' => 'pending',
        'started_at' => now()->subDays(2),
        'submitted_at' => now()->subDay(),
        'pass_score' => 70,
        'max_points' => 20,
    ]);
}

/** @param array<string, mixed> $attributs */
function acInscription(ExamSession $session, array $attributs = []): ExamRegistration
{
    return ExamRegistration::query()->forceCreate($attributs + [
        'uuid' => (string) Str::uuid(),
        'exam_session_id' => $session->id,
        'user_id' => User::factory()->create()->id,
        'status' => 'registered',
    ]);
}

/** @param array<string, mixed> $parametres */
function acDonnees(string $route, User $membre, array $parametres = []): TestResponse
{
    return test()->actingAs($membre, 'staff')->getJson(route($route, $parametres));
}

/**
 * @param  array<string, mixed>  $parametres
 * @return list<string>
 */
function acUuids(string $route, User $membre, array $parametres = []): array
{
    return collect(acDonnees($route, $membre, $parametres + ['length' => 100])->assertOk()->json('data'))
        ->pluck('uuid')->sort()->values()->all();
}

dataset('sources de l’académie', [
    'banque' => ['staff.question-bank.data', 'staff.question-bank'],
    'examens' => ['staff.exams.data', 'staff.exams'],
    'correction' => ['staff.exam-grading.data', 'staff.exam-grading'],
    'enrôlement' => ['staff.exam-enrolment.data', 'staff.exam-enrolment'],
]);

// ═══════════════════════════════════════════════════════════════ la porte

it('porte exactement la même garde de route que son écran', function (string $donnees, string $ecran): void {
    $garde = static fn (string $nom): array => array_values(array_filter(
        Route::getRoutes()->getByName($nom)->gatherMiddleware(),
        static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'can:'),
    ));

    expect($garde($donnees))->not->toBe([])
        ->and($garde($donnees))->toBe($garde($ecran));
})->with('sources de l’académie');

it('refuse la source à qui n’a pas la permission de l’écran, et sans session', function (string $donnees): void {
    $this->getJson(route($donnees))->assertUnauthorized();

    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);

    acDonnees($donnees, $moderateur)->assertForbidden();
})->with('sources de l’académie');

it('pagine côté SQL et plafonne la longueur à 100', function (): void {
    foreach (range(1, 120) as $i) {
        acQuestion();
    }

    $page = acDonnees('staff.question-bank.data', $this->mentorA, ['start' => 110, 'length' => 25])->assertOk();

    expect($page->json('data'))->toHaveCount(10)
        ->and($page->json('recordsTotal'))->toBe(120)
        ->and(acDonnees('staff.question-bank.data', $this->mentorA, ['length' => 1000000])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_MAXIMUM)
        ->and(acDonnees('staff.question-bank.data', $this->mentorA, ['length' => -1])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_PAR_DEFAUT);
});

it('ne propose aucun export : aucune permission ne le couvre', function (string $donnees): void {
    acDonnees($donnees, $this->admin, ['export' => 1])->assertForbidden();
    acDonnees($donnees, $this->mentorA, ['export' => 1])->assertForbidden();
})->with('sources de l’académie');

it('plafonne la longueur et ignore un tri hors liste blanche', function (string $donnees): void {
    acDonnees($donnees, $this->admin, ['length' => 1000000])->assertOk();

    foreach (['id', 'password', 'id; DROP TABLE users', 'prompt'] as $colonne) {
        acDonnees($donnees, $this->admin, [
            'columns' => [['data' => $colonne]],
            'order' => [['column' => 0, 'dir' => 'asc'], ['name' => $colonne, 'dir' => 'desc; --']],
        ])->assertOk();
    }

    acDonnees($donnees, $this->admin, ['draw' => '<script>1</script>'])->assertOk()->assertJsonPath('draw', 0);
})->with('sources de l’académie');

// ═══════════════════════════════════════ le périmètre du mentor, écran par écran

it('BANQUE — deux mentors et l’admin reçoivent exactement la liste Livewire d’avant', function (): void {
    $coursA = acCours($this->mentorA, 'Cours de Awa');
    $coursB = acCours($this->mentorB, 'Cours de Bio');

    acQuestion(['course_id' => $coursA->id, 'created_by' => $this->mentorA->id]);
    acQuestion(['course_id' => $coursB->id, 'created_by' => $this->mentorB->id]);
    acQuestion(['created_by' => $this->admin->id, 'status' => 'published']);

    $avant = collect(app(DepotBanqueQuestions::class)->lister(parPage: 1000)->items())
        ->pluck('uuid')->sort()->values()->all();

    expect($avant)->toHaveCount(3)
        ->and(acUuids('staff.question-bank.data', $this->mentorA))->toBe($avant)
        ->and(acUuids('staff.question-bank.data', $this->mentorB))->toBe($avant)
        ->and(acUuids('staff.question-bank.data', $this->admin))->toBe($avant);
});

it('BANQUE — le mentor n’a ni publier ni supprimer ; l’admin les a', function (): void {
    $question = acQuestion();

    $mentor = collect(acDonnees('staff.question-bank.data', $this->mentorA)->json('data'))->keyBy('uuid');
    $admin = collect(acDonnees('staff.question-bank.data', $this->admin)->json('data'))->keyBy('uuid');

    expect($mentor[$question->uuid]['actions'])->toBe(['publier' => false, 'retirer' => false, 'modifier' => true, 'supprimer' => false])
        ->and($admin[$question->uuid]['actions'])->toBe(['publier' => true, 'retirer' => false, 'modifier' => true, 'supprimer' => true]);
});

it('EXAMENS — deux mentors et l’admin reçoivent exactement la liste Livewire d’avant', function (): void {
    acExamen(['created_by' => $this->mentorA->id, 'status' => 'draft']);
    acExamen(['created_by' => $this->mentorB->id]);
    acExamen(['created_by' => $this->admin->id, 'status' => 'archived']);

    $avant = collect(app(DepotExamens::class)->lister(parPage: 1000)->items())
        ->pluck('uuid')->sort()->values()->all();

    expect($avant)->toHaveCount(3)
        ->and(acUuids('staff.exams.data', $this->mentorA))->toBe($avant)
        ->and(acUuids('staff.exams.data', $this->mentorB))->toBe($avant)
        ->and(acUuids('staff.exams.data', $this->admin))->toBe($avant);

    $brouillon = Exam::query()->where('status', 'draft')->firstOrFail();
    $ligneMentor = collect(acDonnees('staff.exams.data', $this->mentorA)->json('data'))->firstWhere('uuid', $brouillon->uuid);
    $ligneAdmin = collect(acDonnees('staff.exams.data', $this->admin)->json('data'))->firstWhere('uuid', $brouillon->uuid);

    expect($ligneMentor['actions'])->toBe(['sessions' => true, 'publier' => false, 'modifier' => true, 'archiver' => false])
        ->and($ligneAdmin['actions'])->toBe(['sessions' => true, 'publier' => true, 'modifier' => true, 'archiver' => true]);
});

it('CORRECTION — deux mentors et l’admin reçoivent exactement la file Livewire d’avant', function (): void {
    $examen = acExamen();

    acCopie($examen, ['assigned_to' => $this->mentorA->id, 'status' => 'grading']);
    acCopie($examen, ['assigned_to' => $this->mentorB->id, 'status' => 'grading']);
    acCopie($examen);
    acCopie($examen, ['status' => 'graded', 'score_percent' => 80, 'passed' => true]);
    // Hors file, avant comme après : en composition et abandonnée.
    acCopie($examen, ['status' => 'in_progress', 'submitted_at' => null]);
    acCopie($examen, ['status' => 'abandoned']);

    $avant = collect(app(DepotCopiesExamen::class)->fileDeCorrection(parPage: 1000)->items())
        ->pluck('uuid')->sort()->values()->all();

    expect($avant)->toHaveCount(4)
        ->and(acUuids('staff.exam-grading.data', $this->mentorA))->toBe($avant)
        ->and(acUuids('staff.exam-grading.data', $this->mentorB))->toBe($avant)
        ->and(acUuids('staff.exam-grading.data', $this->admin))->toBe($avant);

    // « Les miennes » reste un FILTRE, résolu sur la personne qui demande.
    $siennes = static fn (User $m): array => ExamAttempt::query()->where('assigned_to', $m->id)->pluck('uuid')->all();

    expect(acUuids('staff.exam-grading.data', $this->mentorA, ['filtres' => ['correcteur' => 'moi']]))->toBe($siennes($this->mentorA))
        ->and(acUuids('staff.exam-grading.data', $this->mentorB, ['filtres' => ['correcteur' => 'moi']]))->toBe($siennes($this->mentorB))
        ->and(acUuids('staff.exam-grading.data', $this->admin, ['filtres' => ['correcteur' => $this->mentorB->uuid]]))->toBe($siennes($this->mentorB));
});

it('ENRÔLEMENT — deux mentors et l’admin lisent les mêmes inscrits par session qu’avant', function (): void {
    $examen = acExamen();
    $s1 = acSession($examen);
    $s2 = acSession($examen, ['starts_at' => now()->subMonth(), 'status' => 'closed']);

    acInscription($s1);
    acInscription($s1, ['status' => 'cancelled']);
    acInscription($s2);

    $depot = app(DepotInscriptionsExamen::class);

    foreach ([$s1, $s2] as $session) {
        $avant = collect($depot->lister(session: $session, parPage: 1000)->items())->pluck('uuid')->sort()->values()->all();

        foreach ([$this->mentorA, $this->mentorB, $this->admin] as $membre) {
            expect(acUuids('staff.exam-enrolment.data', $membre, ['filtres' => ['session' => $session->uuid]]))->toBe($avant);
        }
    }

    // Sans session choisie : l'union de toutes les sessions, que la liste
    // d'avant atteignait déjà une session à la fois.
    $tout = collect($depot->lister(parPage: 1000)->items())->pluck('uuid')->sort()->values()->all();
    expect(acUuids('staff.exam-enrolment.data', $this->mentorA))->toBe($tout);

    // Le mentor n'a aucune permission d'écriture sur l'enrôlement : aucun bouton.
    foreach (acDonnees('staff.exam-enrolment.data', $this->mentorA)->json('data') as $ligne) {
        expect(array_filter($ligne['actions']))->toBe([]);
    }

    $ligneAdmin = collect(acDonnees('staff.exam-enrolment.data', $this->admin)->json('data'))
        ->firstWhere('statut', 'registered');
    expect($ligneAdmin['actions'])->toBe(['convoquer' => true, 'emarger' => true, 'arbitrer' => false, 'annuler' => true]);
});

// ═══════════════════════════════════════════════════════════ ce qui sort

it('BANQUE — ni id, ni proposition, ni bonne réponse, ni explication', function (): void {
    $question = acQuestion(['prompt' => 'Premier réflexe ?', 'explanation' => 'EXPLICATION-A-NE-PAS-FUIR']);
    QuestionBankOption::query()->forceCreate([
        'question_bank_item_id' => $question->id, 'position' => 1,
        'label' => 'BONNE-REPONSE-A-NE-PAS-FUIR', 'is_correct' => true,
    ]);

    $reponse = acDonnees('staff.question-bank.data', $this->mentorA, ['draw' => 2])->assertOk()->assertJsonPath('draw', 2);

    expect((string) $reponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_keys($reponse->json('data.0')))->toEqualCanonicalizing([
            'uuid', 'enonce', 'sansExplication', 'type', 'typeLibelle', 'cours', 'points', 'reponses',
            'tauxReussite', 'statut', 'statutLibelle', 'creeLe', 'creeLeTexte', 'actions',
        ])
        ->and($reponse->json('data.0.sansExplication'))->toBeFalse()
        ->and($reponse->getContent())
        ->not->toContain('"id"')
        ->not->toContain('BONNE-REPONSE')
        ->not->toContain('EXPLICATION-A-NE-PAS-FUIR')
        ->not->toContain('is_correct')
        ->not->toContain('course_id');
});

it('CORRECTION — métadonnées seules : aucune réponse, aucun commentaire, aucune appréciation', function (): void {
    $copie = acCopie(acExamen(), ['feedback' => 'APPRECIATION-A-NE-PAS-FUIR']);

    ExamAnswer::query()->forceCreate([
        'exam_attempt_id' => $copie->id, 'position' => 1, 'prompt' => 'ENONCE-DE-COPIE',
        'type' => 'short_answer', 'points_max' => 10,
        'given_text' => 'REPONSE-DU-CANDIDAT', 'grader_comment' => 'COMMENTAIRE-DU-CORRECTEUR',
    ]);

    $reponse = acDonnees('staff.exam-grading.data', $this->mentorA)->assertOk();

    expect(array_keys($reponse->json('data.0')))->toEqualCanonicalizing([
        'uuid', 'reference', 'statut', 'statutLibelle', 'candidat', 'epreuve', 'soumiseLe', 'soumiseLeTexte',
        'attenteHeures', 'urgente', 'note', 'admis', 'correcteur', 'actions',
    ])
        ->and($reponse->getContent())
        ->not->toContain('"id"')
        ->not->toContain('REPONSE-DU-CANDIDAT')
        ->not->toContain('COMMENTAIRE-DU-CORRECTEUR')
        ->not->toContain('APPRECIATION-A-NE-PAS-FUIR')
        ->not->toContain('ENONCE-DE-COPIE')
        ->not->toContain('user_id')
        ->not->toContain('assigned_to');
});

it('EXAMENS et ENRÔLEMENT — des champs fermés, sans id ni clé étrangère', function (): void {
    $examen = acExamen(['description' => 'DESCRIPTION-INTERNE', 'instructions' => 'CONSIGNES-INTERNES']);
    $session = acSession($examen);
    $inscrit = User::factory()->create(['phone' => '+22990000000']);
    acInscription($session, ['user_id' => $inscrit->id]);

    $examens = acDonnees('staff.exams.data', $this->admin)->assertOk();
    expect(array_keys($examens->json('data.0')))->toEqualCanonicalizing([
        'uuid', 'titre', 'duree', 'tentatives', 'cours', 'seuil', 'sessions', 'candidats', 'tauxReussite',
        'statut', 'statutLibelle', 'creeLe', 'creeLeTexte', 'publieLeTexte', 'actions',
    ])
        ->and($examens->json('data.0.sessions'))->toBe(1)
        ->and($examens->getContent())->not->toContain('"id"')->not->toContain('INTERNE');

    $inscriptions = acDonnees('staff.exam-enrolment.data', $this->admin)->assertOk();
    expect(array_keys($inscriptions->json('data.0')))->toEqualCanonicalizing([
        'uuid', 'candidat', 'email', 'epreuve', 'session', 'sessionTexte', 'lieu', 'convocation', 'convocationTexte',
        'codeConvocation', 'presence', 'statut', 'statutLibelle', 'motifReport', 'inscritLe', 'inscritLeTexte', 'actions',
    ])
        ->and($inscriptions->getContent())
        ->not->toContain('"id"')
        ->not->toContain('+22990000000')
        ->not->toContain('password')
        ->not->toContain('exam_session_id');
});

it('ENRÔLEMENT — le code de convocation ne part qu’à qui peut émarger', function (): void {
    // Arbitrage du 14 septembre 2026 : le Mentor lit les inscriptions
    // (`enrolment.view`) mais n'émarge pas ; le code n'a pas à circuler chez lui.
    $session = acSession(acExamen());
    acInscription($session, ['convocation_code' => 'CODE-SECRET-42', 'convocation_sent_at' => now()]);

    $admin = acDonnees('staff.exam-enrolment.data', $this->admin)->assertOk();
    $mentor = acDonnees('staff.exam-enrolment.data', $this->mentorA)->assertOk();

    expect($admin->json('data.0.codeConvocation'))->toBe('CODE-SECRET-42')
        ->and($mentor->json('data.0.codeConvocation'))->toBeNull()
        ->and($mentor->getContent())->not->toContain('CODE-SECRET-42');
});

// ══════════════════════════════════════════════════ le jeu entier, et les filtres

it('BANQUE — recherche dans tout le fonds, sans tenir compte de la casse, et filtre', function (): void {
    $cours = acCours(null, 'Vérifier une image');

    foreach (range(1, 30) as $i) {
        acQuestion(['created_at' => now()->subMinutes($i)]);
    }

    $cible = acQuestion([
        'prompt' => 'Qu’est-ce que la recherche inversée ?', 'type' => 'short_answer',
        'status' => 'to_review', 'course_id' => $cours->id, 'created_at' => now()->subYear(),
    ]);

    // Casse ASCII seulement : SQLite (la base des tests) ne replie pas « É » ;
    // MySQL le fait par sa collation, comme pour le pilote.
    $trouve = acDonnees('staff.question-bank.data', $this->mentorA, ['search' => ['value' => 'RECHERCHE INVERS'], 'length' => 10]);
    $trouve->assertJsonPath('recordsTotal', 31)->assertJsonPath('recordsFiltered', 1);
    expect($trouve->json('data.0.uuid'))->toBe($cible->uuid);

    expect(acUuids('staff.question-bank.data', $this->admin, ['filtres' => ['type' => 'short_answer']]))->toBe([$cible->uuid])
        ->and(acUuids('staff.question-bank.data', $this->admin, ['filtres' => ['statut' => 'to_review']]))->toBe([$cible->uuid])
        ->and(acUuids('staff.question-bank.data', $this->admin, ['filtres' => ['cours' => $cours->uuid]]))->toBe([$cible->uuid])
        // Valeur inconnue : ignorée, pas une 500.
        ->and(acUuids('staff.question-bank.data', $this->admin, ['filtres' => ['cours' => (string) Str::uuid(), 'type' => 'difficile']]))->toHaveCount(31);

    // SearchBuilder, à travers la liste blanche.
    $criteres = ['logic' => 'AND', 'criteria' => [['origData' => 'enonce', 'condition' => 'contains', 'value' => ['recherche inv']]]];
    expect(acDonnees('staff.question-bank.data', $this->admin, ['searchBuilder' => $criteres])->json('recordsFiltered'))->toBe(1);

    $intrus = ['logic' => 'AND', 'criteria' => [['origData' => 'explanation', 'condition' => '!null', 'value' => []]]];
    expect(acDonnees('staff.question-bank.data', $this->admin, ['searchBuilder' => $intrus])->json('recordsFiltered'))->toBe(31);
});

it('EXAMENS — recherche sur le titre et le cours, filtres cours, statut et période', function (): void {
    $cours = acCours(null, 'Rumeurs et élections');
    $cible = acExamen(['title' => 'Certification', 'course_id' => $cours->id, 'status' => 'draft', 'created_at' => '2026-03-10 10:00:00']);
    acExamen(['title' => 'Autre épreuve', 'created_at' => '2026-05-10 10:00:00']);

    expect(acUuids('staff.exams.data', $this->mentorA, ['search' => ['value' => 'RUMEURS']]))->toBe([$cible->uuid])
        ->and(acUuids('staff.exams.data', $this->mentorA, ['filtres' => ['cours' => $cours->uuid]]))->toBe([$cible->uuid])
        ->and(acUuids('staff.exams.data', $this->mentorA, ['filtres' => ['statut' => 'draft']]))->toBe([$cible->uuid])
        ->and(acUuids('staff.exams.data', $this->mentorA, ['filtres' => ['cree_du' => '2026-03-01', 'cree_au' => '2026-03-31']]))->toBe([$cible->uuid])
        ->and(acUuids('staff.exams.data', $this->mentorA, ['filtres' => ['cree_du' => '2026-02-31']]))->toHaveCount(2);

    $tri = acDonnees('staff.exams.data', $this->admin, ['order' => [['name' => 'titre', 'dir' => 'asc']]]);
    expect($tri->json('data.0.titre'))->toBe('Autre épreuve');
});

it('CORRECTION — filtres examen, statut et période de remise ; la plus ancienne trie en tête', function (): void {
    $e1 = acExamen(['title' => 'Épreuve un']);
    $e2 = acExamen(['title' => 'Épreuve deux']);

    $vieille = acCopie($e1, ['submitted_at' => '2026-03-10 09:00:00', 'reference' => 'EX-VIEILLE']);
    $recente = acCopie($e2, ['submitted_at' => '2026-05-10 09:00:00', 'status' => 'graded', 'score_percent' => 55, 'passed' => false]);

    expect(acUuids('staff.exam-grading.data', $this->mentorB, ['filtres' => ['examen' => $e1->uuid]]))->toBe([$vieille->uuid])
        ->and(acUuids('staff.exam-grading.data', $this->mentorB, ['filtres' => ['statut' => 'graded']]))->toBe([$recente->uuid])
        ->and(acUuids('staff.exam-grading.data', $this->mentorB, ['filtres' => ['soumise_du' => '2026-05-01']]))->toBe([$recente->uuid])
        ->and(acUuids('staff.exam-grading.data', $this->mentorB, ['search' => ['value' => 'ex-vieille']]))->toBe([$vieille->uuid]);

    $page = acDonnees('staff.exam-grading.data', $this->admin, ['order' => [['name' => 'soumiseLe', 'dir' => 'asc']]]);
    expect($page->json('data.0.uuid'))->toBe($vieille->uuid)
        ->and($page->json('data.1.note'))->toBe(55)
        ->and($page->json('data.0.note'))->toBeNull();
});

it('ENRÔLEMENT — filtres examen, statut, période de session, et recherche du candidat', function (): void {
    $e1 = acExamen();
    $e2 = acExamen();
    $s1 = acSession($e1, ['starts_at' => '2026-10-12 09:00:00']);
    $s2 = acSession($e2, ['starts_at' => '2026-12-01 09:00:00']);

    $cible = acInscription($s1, ['user_id' => User::factory()->create(['name' => 'Rhodes Agossou'])->id, 'status' => 'deferral_requested']);
    acInscription($s2);

    expect(acUuids('staff.exam-enrolment.data', $this->mentorA, ['filtres' => ['examen' => $e1->uuid]]))->toBe([$cible->uuid])
        ->and(acUuids('staff.exam-enrolment.data', $this->mentorA, ['filtres' => ['statut' => 'deferral_requested']]))->toBe([$cible->uuid])
        ->and(acUuids('staff.exam-enrolment.data', $this->mentorA, ['filtres' => ['session_du' => '2026-10-01', 'session_au' => '2026-10-31']]))->toBe([$cible->uuid])
        ->and(acUuids('staff.exam-enrolment.data', $this->mentorA, ['search' => ['value' => 'RHODES']]))->toBe([$cible->uuid]);
});

// ════════════════════════════════════════════════════════════════ le coût

it('ne multiplie pas les requêtes avec la taille de la page', function (string $donnees): void {
    $cours = acCours();

    foreach (range(1, 40) as $i) {
        $examen = acExamen(['course_id' => $cours->id]);
        $session = acSession($examen);
        acQuestion(['course_id' => $cours->id]);
        acCopie($examen, ['assigned_to' => $i % 2 === 0 ? $this->mentorA->id : null]);
        acInscription($session);
    }

    $compter = function (int $longueur) use ($donnees): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        acDonnees($donnees, $this->mentorA, ['length' => $longueur])->assertOk();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    };

    $compter(5); // amorce les caches de permissions

    expect($compter(40))->toBe($compter(5));
})->with('sources de l’académie');

// ══════════════════════════════════════════════════════════ le pont Livewire

it('BANQUE — demande à la table de se recharger après une écriture', function (): void {
    $question = acQuestion(['status' => 'published']);

    Livewire::actingAs($this->admin, 'staff')
        ->test(BanqueDeQuestions::class)
        ->call('mettreEnRevision', $question->uuid)
        ->assertDispatched(BanqueDeQuestions::EVENEMENT_TABLE_A_RECHARGER);

    Livewire::actingAs($this->mentorA, 'staff')
        ->test(BanqueDeQuestions::class)
        ->call('ouvrirCreation')
        ->set('enonce', 'Une question écrite par un mentor')
        ->set('propositions', [['label' => 'Oui', 'correcte' => true], ['label' => 'Non', 'correcte' => false]])
        ->call('enregistrer')
        ->assertHasNoErrors()
        ->assertDispatched(BanqueDeQuestions::EVENEMENT_TABLE_A_RECHARGER);
});

it('EXAMENS — demande à la table de se recharger après une écriture', function (): void {
    $examen = acExamen(['status' => 'published']);

    Livewire::actingAs($this->admin, 'staff')
        ->test(Examens::class)
        ->call('archiver', $examen->uuid)
        ->assertDispatched(Examens::EVENEMENT_TABLE_A_RECHARGER);
});

it('CORRECTION — demande à la table de se recharger quand un mentor prend une copie', function (): void {
    $copie = acCopie(acExamen());

    Livewire::actingAs($this->mentorA, 'staff')
        ->test(CorrectionExamens::class)
        ->call('prendreLaCopie', $copie->uuid)
        ->assertDispatched(CorrectionExamens::EVENEMENT_TABLE_A_RECHARGER);

    expect($copie->refresh()->assigned_to)->toBe($this->mentorA->id);
    $this->assertDatabaseHas('audit_logs', ['action' => 'exam.assign']);
});

it('ENRÔLEMENT — demande à la table de se recharger après un émargement', function (): void {
    $inscription = acInscription(acSession(acExamen()));

    Livewire::actingAs($this->admin, 'staff')
        ->test(EnrolementAuxExamens::class)
        ->call('emarger', $inscription->uuid)
        ->assertDispatched(EnrolementAuxExamens::EVENEMENT_TABLE_A_RECHARGER);

    $this->assertDatabaseHas('audit_logs', ['action' => 'enrolment.attend']);
});

it('sert chaque écran au mentor, avec la zone DataTables DANS la racine et sans export', function (string $donnees, string $ecran): void {
    $html = (string) $this->actingAs($this->mentorA, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp])
        ->get(route($ecran))
        ->assertOk()
        ->assertSee(route($donnees), escape: false)
        ->assertSee('wire:ignore', escape: false)
        ->assertSee('assets/vendor/datatables/js/dataTables-2.3.8.min.js', escape: false)
        ->assertDontSee('data-vdt-export', escape: false)
        ->getContent();

    expect($html)->toContain('data-vdt-table');
})->with('sources de l’académie');

it('garde les propriétés #[Url] existantes branchées sur les champs de la table', function (): void {
    $this->actingAs($this->admin, 'staff')->withSession(['staff.last_activity' => now()->timestamp]);

    $this->get(route('staff.question-bank', ['q' => 'image', 'type' => 'qcm']))->assertOk()
        ->assertSee('data-vdt-livewire="recherche"', escape: false)
        ->assertSee('data-vdt-livewire="filtreType"', escape: false)
        ->assertSee('data-vdt-livewire="filtreCours"', escape: false)
        ->assertSee('value="image"', escape: false);

    $this->get(route('staff.exam-grading', ['correcteur' => 'moi']))->assertOk()
        ->assertSee('data-vdt-livewire="filtreCorrecteur"', escape: false)
        ->assertSee('<option value="moi" selected', escape: false);

    $session = acSession(acExamen(), ['starts_at' => now()->subMonth(), 'status' => 'closed']);

    // Une session passée ouverte par l'adresse reste dans le sélecteur.
    $this->get(route('staff.exam-enrolment', ['session' => $session->uuid]))->assertOk()
        ->assertSee('data-vdt-livewire="sessionUuid"', escape: false)
        ->assertSee('value="'.$session->uuid.'"', escape: false);
});
