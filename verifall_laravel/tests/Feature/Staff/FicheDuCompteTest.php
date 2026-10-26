<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotFicheDuCompte;
use App\Core\Identity\Services\HistoriqueDuHub;
use App\Enums\UserRole;
use App\Livewire\Staff\FicheUtilisateur;
use App\Livewire\Staff\Utilisateurs;
use App\Models\AnalysisReport;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\ConsultationHub;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ForumCategory;
use App\Models\HistoriqueConnexion;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Thread;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedFact;
use App\Models\XpLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| La fiche d'un compte (admin/utilisateurs/{uuid}) — 14 septembre 2026
|--------------------------------------------------------------------------
|
| Dans l'ordre où un défaut coûterait le plus :
|
|   · **la porte** — la fiche et CHAQUE source d'onglet refusent qui n'a pas
|     `user.view` ; le Journal demande en plus `security.logs` ;
|   · **le cloisonnement** — chaque onglet rend les lignes de CE compte, et
|     jamais celles d'un autre : une fuite ici ne fait aucun bruit ;
|   · **ce qui sort** — pas d'`id`, pas de secret, pas d'IP ;
|   · **le mentor** — ses onglets de périmètre, et eux seuls ;
|   · **le coût** — le nombre de requêtes ne grandit pas avec la page.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create(['name' => 'Admin Principal', 'two_factor_confirmed_at' => now()]);
    $this->admin->assignRole(UserRole::Admin->value);

    $this->citoyen = User::factory()->actif()->create([
        'name' => 'Rhodes Agossou',
        'email' => 'rhodes@exemple.bj',
        'city' => 'Parakou',
        'units' => 42,
        'xp' => 310,
        'last_login_at' => now()->subDays(2),
        'phone' => '+22990000000',
        'two_factor_secret' => 'SECRET-A-NE-PAS-FUIR',
    ]);

    $this->autre = User::factory()->actif()->create(['name' => 'Autre Compte']);
});

/** GET sur la source JSON d'un onglet, en session du personnel. */
function ficheDonnees(User $membre, User $compte, string $onglet, array $parametres = []): TestResponse
{
    return test()->actingAs($membre, 'staff')
        ->getJson(route('staff.users.show.data', ['user' => $compte->uuid, 'onglet' => $onglet] + $parametres));
}

/** Un cours minimal. */
function ficheCours(string $titre, ?User $formateur = null): Course
{
    return Course::query()->forceCreate([
        'uuid' => (string) Str::uuid(),
        'slug' => Str::slug($titre).'-'.Str::lower(Str::random(5)),
        'title' => $titre,
        'description' => 'Un cours de test.',
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '1 h',
        'cost_units' => 0,
        'instructor_id' => $formateur?->id,
        'is_published' => true,
    ]);
}

/**
 * Une ligne dans CHAQUE historique pour ce compte, marquée de son nom, pour
 * vérifier qu'aucun onglet ne renvoie les lignes d'un autre.
 *
 * @return array<string, string> onglet => texte qui doit apparaître
 */
function ficheHistorique(User $compte, string $marque): array
{
    $cours = ficheCours("Cours {$marque}");

    $verification = Verification::query()->forceCreate([
        'uuid' => (string) Str::uuid(), 'user_id' => $compte->id, 'type' => 'text', 'status' => 'completed',
        'claim' => "Affirmation {$marque}", 'cost_units' => 1,
    ]);
    AnalysisReport::query()->forceCreate(['uuid' => (string) Str::uuid(), 'verification_id' => $verification->id, 'verdict' => 'false']);

    Enrollment::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $compte->id, 'course_id' => $cours->id, 'status' => 'in_progress', 'progress' => 40]);

    $quiz = Quiz::query()->forceCreate(['uuid' => (string) Str::uuid(), 'course_id' => $cours->id, 'title' => "Quiz {$marque}", 'pass_score' => 70]);
    QuizAttempt::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $compte->id, 'quiz_id' => $quiz->id, 'score' => 80, 'passed' => true]);

    $devoir = Assignment::query()->forceCreate(['uuid' => (string) Str::uuid(), 'course_id' => $cours->id, 'title' => "Devoir {$marque}", 'description' => 'Consigne.']);
    AssignmentSubmission::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $compte->id, 'assignment_id' => $devoir->id, 'status' => 'passed', 'grade' => 16]);

    $examen = Exam::query()->forceCreate(['uuid' => (string) Str::uuid(), 'slug' => 'examen-'.Str::lower(Str::random(6)), 'title' => "Examen {$marque}", 'status' => 'published']);
    ExamAttempt::query()->forceCreate([
        'uuid' => (string) Str::uuid(), 'reference' => 'EX-'.Str::upper(Str::random(6)), 'exam_id' => $examen->id,
        'user_id' => $compte->id, 'started_at' => now(), 'pass_score' => 50, 'max_points' => 20, 'status' => 'graded', 'score_percent' => 75, 'passed' => true,
    ]);

    Certificate::query()->forceCreate([
        'uuid' => (string) Str::uuid(), 'code' => 'CERT-'.$marque, 'user_id' => $compte->id, 'course_id' => $cours->id,
        'holder_name' => $compte->name, 'course_title' => "Cours {$marque}", 'issued_at' => now(),
    ]);

    UnitLog::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $compte->id, 'delta' => 5, 'balance_after' => 5, 'reason' => 'admin_adjustment', 'label' => "Unités {$marque}"]);
    XpLog::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $compte->id, 'delta' => 10, 'balance_after' => 10, 'reason' => 'admin_adjustment', 'label' => "Points {$marque}"]);

    Payment::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $compte->id, 'provider' => 'mtn', 'pack' => "Pack {$marque}", 'amount_fcfa' => 1000, 'units' => 10, 'status' => 'succeeded', 'phone_encrypted' => 'TELEPHONE-CHIFFRE']);

    $categorie = ForumCategory::query()->firstOrCreate(['slug' => 'general'], ['uuid' => (string) Str::uuid(), 'name' => 'Général']);
    Thread::query()->forceCreate(['uuid' => (string) Str::uuid(), 'slug' => 'sujet-'.Str::lower(Str::random(6)), 'user_id' => $compte->id, 'forum_category_id' => $categorie->id, 'title' => "Sujet {$marque}", 'body' => 'Corps.']);

    return [
        'verifications' => "Affirmation {$marque}",
        'cours' => "Cours {$marque}",
        'quiz' => "Quiz {$marque}",
        'devoirs' => "Devoir {$marque}",
        'examens' => "Examen {$marque}",
        'certificats' => "CERT-{$marque}",
        'unites' => "Unités {$marque}",
        'points' => "Points {$marque}",
        'paiements' => "Pack {$marque}",
        'forum' => "Sujet {$marque}",
    ];
}

// ═══════════════════════════════════════════════════════════════ la porte

it('refuse la fiche et ses onglets à qui n’a pas user.view — mentor et modérateur compris', function (): void {
    foreach ([UserRole::Mentor, UserRole::Moderateur] as $role) {
        $membre = User::factory()->actif()->create();
        $membre->assignRole($role->value);

        $this->actingAs($membre, 'staff')->get(route('staff.users.show', $this->citoyen))->assertForbidden();

        foreach (['verifications', 'cours', 'journal', 'cours-encadres'] as $onglet) {
            ficheDonnees($membre, $this->citoyen, $onglet)->assertForbidden();
        }
    }
});

it('refuse la fiche sans session du personnel', function (): void {
    $this->getJson(route('staff.users.show.data', ['user' => $this->citoyen->uuid, 'onglet' => 'verifications']))->assertUnauthorized();
    $this->get(route('staff.users.show', $this->citoyen))->assertRedirect();
});

it('porte la même garde de route sur la fiche et sur ses onglets', function (): void {
    $garde = static fn (string $nom): array => array_values(array_filter(
        Route::getRoutes()->getByName($nom)->gatherMiddleware(),
        static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'can:'),
    ));

    expect($garde('staff.users.show'))->toBe(['can:view,user'])
        ->and($garde('staff.users.show.data'))->toBe($garde('staff.users.show'));
});

it('n’adresse la fiche que par uuid, et refuse un onglet hors liste', function (): void {
    $this->actingAs($this->admin, 'staff')->get('/admin/utilisateurs/'.$this->citoyen->id)->assertNotFound();
    $this->actingAs($this->admin, 'staff')->getJson('/admin/utilisateurs/'.$this->citoyen->uuid.'/donnees/password')->assertNotFound();

    // La liste des comptes reste servie : `utilisateurs/donnees` n'est pas lu comme un uuid.
    $this->actingAs($this->admin, 'staff')->getJson(route('staff.users.data'))->assertOk();
});

it('réserve l’onglet Journal à security.logs, en plus de user.view', function (): void {
    $lecteur = User::factory()->actif()->create();
    $lecteur->givePermissionTo('user.view');

    ficheDonnees($lecteur, $this->citoyen, 'verifications')->assertOk();
    ficheDonnees($lecteur, $this->citoyen, 'journal')->assertForbidden();

    $this->actingAs($lecteur, 'staff')->get(route('staff.users.show', $this->citoyen))
        ->assertOk()
        ->assertDontSee('data-vfu-onglet="journal"', escape: false);
});

it('refuse l’export de l’historique d’un compte, même à l’administrateur', function (): void {
    ficheDonnees($this->admin, $this->citoyen, 'verifications', ['export' => 1])->assertForbidden();
});

// ═══════════════════════════════════════════════════════════ ce qui sort

it('affiche la carte d’identité sans téléphone ni secret', function (): void {
    $this->actingAs($this->admin, 'staff')
        ->get(route('staff.users.show', $this->citoyen))
        ->assertOk()
        ->assertSee('Rhodes Agossou')
        ->assertSee('rhodes@exemple.bj')
        ->assertSee('Parakou')
        ->assertSee('Dernière connexion')
        ->assertSee('Citoyen')
        ->assertSee('data-vdt="fiche-verifications"', escape: false)
        ->assertSee('wire:ignore', escape: false)
        ->assertSee(route('staff.users.show.data', ['user' => $this->citoyen->uuid, 'onglet' => 'verifications']), escape: false)
        ->assertDontSee('+22990000000')
        ->assertDontSee('SECRET-A-NE-PAS-FUIR');
});

it('rend dans chaque onglet les lignes de CE compte, et jamais celles d’un autre', function (): void {
    $attendus = ficheHistorique($this->citoyen, 'RHODES');
    $intrus = ficheHistorique($this->autre, 'INTRUS');

    foreach ($attendus as $onglet => $texte) {
        $reponse = ficheDonnees($this->admin, $this->citoyen, $onglet)->assertOk();

        $lignes = (string) json_encode($reponse->json('data'), JSON_UNESCAPED_UNICODE);

        expect($reponse->json('recordsTotal'))->toBe(1, "onglet {$onglet}")
            ->and($lignes)->toContain($texte)
            ->and($lignes)->not->toContain('INTRUS');

        expect((string) $reponse->headers->get('Cache-Control'))->toContain('no-store');
    }

    // Et l'inverse, pour qu'un filtre codé en dur ne passe pas le test.
    expect(ficheDonnees($this->admin, $this->autre, 'forum')->json('data.0.sujet'))->toBe($intrus['forum']);
});

it('ne laisse sortir ni id, ni clé étrangère, ni secret, ni téléphone chiffré', function (): void {
    ficheHistorique($this->citoyen, 'RHODES');

    // Les lectures du Hub n'existent qu'avec le consentement du compte.
    app(HistoriqueDuHub::class)->definir($this->citoyen, true, '127.0.0.1', 'test');

    foreach (array_keys(DepotFicheDuCompte::COLONNES_TRIABLES) as $onglet) {
        $contenu = (string) ficheDonnees($this->admin, $this->citoyen, $onglet)->assertOk()->getContent();

        expect($contenu)
            ->not->toContain('"id"')
            ->not->toContain('_id"')
            ->not->toContain('TELEPHONE-CHIFFRE')
            ->not->toContain('ip_hash')
            ->not->toContain('SECRET-A-NE-PAS-FUIR');
    }
});

it('rend les libellés et le verdict d’une vérification', function (): void {
    ficheHistorique($this->citoyen, 'RHODES');

    $ligne = ficheDonnees($this->admin, $this->citoyen, 'verifications')->json('data.0');

    expect(array_keys($ligne))->toEqualCanonicalizing([
        'affirmation', 'auteur', 'type', 'theme', 'statut', 'ton', 'verdict', 'cout', 'rembourse', 'assigneeLe', 'creeLe',
    ])
        ->and($ligne['ton'])->toBe('ok')
        ->and($ligne['verdict'])->not->toBeNull();
});

it('trace au Journal ce que le compte a fait ET ce qu’on lui a fait', function (): void {
    AuditLog::query()->create(['actor_id' => $this->citoyen->id, 'actor_label' => 'Rhodes Agossou', 'action' => 'login', 'module' => 'auth', 'ip_hash' => 'EMPREINTE-IP']);
    AuditLog::query()->create(['actor_id' => $this->admin->id, 'actor_label' => 'Admin Principal', 'action' => 'user.suspend', 'module' => 'moderation', 'target_type' => User::class, 'target_uuid' => $this->citoyen->uuid, 'target_label' => 'rhodes@exemple.bj']);
    // Même uuid, autre type : ce n'est PAS ce compte.
    AuditLog::query()->create(['actor_id' => $this->admin->id, 'action' => 'course.publish', 'module' => 'academie', 'target_type' => Course::class, 'target_uuid' => $this->citoyen->uuid]);
    AuditLog::query()->create(['actor_id' => $this->autre->id, 'action' => 'login', 'module' => 'auth']);

    $reponse = ficheDonnees($this->admin, $this->citoyen, 'journal')->assertOk();

    expect($reponse->json('recordsTotal'))->toBe(2)
        ->and(collect($reponse->json('data'))->pluck('role')->sort()->values()->all())->toBe(['Acteur', 'Cible'])
        ->and($reponse->getContent())->not->toContain('EMPREINTE-IP');
});

// ═════════════════════════════════════════════════════ entrée bornée

it('pagine, plafonne la longueur et ignore un tri hors liste blanche', function (): void {
    foreach (range(1, 120) as $i) {
        UnitLog::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $this->citoyen->id, 'delta' => $i, 'balance_after' => $i, 'reason' => 'admin_adjustment', 'label' => "Mouvement {$i}"]);
    }

    expect(ficheDonnees($this->admin, $this->citoyen, 'unites', ['length' => 1000000])->json('data'))->toHaveCount(100)
        ->and(ficheDonnees($this->admin, $this->citoyen, 'unites', ['start' => 110, 'length' => 25])->json('data'))->toHaveCount(10);

    ficheDonnees($this->admin, $this->citoyen, 'unites', ['order' => [['name' => 'user_id; DROP TABLE users', 'dir' => 'asc']]])
        ->assertOk()->assertJsonPath('recordsTotal', 120);

    $tri = ficheDonnees($this->admin, $this->citoyen, 'unites', ['order' => [['name' => 'mouvement', 'dir' => 'desc']], 'length' => 1]);
    expect($tri->json('data.0.mouvement'))->toBe('+120');

    $filtre = ficheDonnees($this->admin, $this->citoyen, 'unites', ['search' => ['value' => 'Mouvement 42']]);
    expect($filtre->json('recordsFiltered'))->toBe(1);
});

// ═══════════════════════════════════════════════════════════════ le mentor

it('ajoute au mentor les onglets de son périmètre, et à lui seul', function (): void {
    $mentor = User::factory()->actif()->create(['name' => 'Mentor Encadrant']);
    $mentor->assignRole(UserRole::Mentor->value);

    $cours = ficheCours('Cours du mentor', $mentor);
    Enrollment::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $this->citoyen->id, 'course_id' => $cours->id]);
    ficheCours('Cours d’un collègue', $this->admin);

    $devoir = Assignment::query()->forceCreate(['uuid' => (string) Str::uuid(), 'course_id' => $cours->id, 'title' => 'Devoir corrigé', 'description' => 'C.']);
    AssignmentSubmission::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $this->citoyen->id, 'assignment_id' => $devoir->id, 'status' => 'passed', 'grade' => 14, 'reviewed_by' => $mentor->id, 'reviewed_at' => now()]);

    $examen = Exam::query()->forceCreate(['uuid' => (string) Str::uuid(), 'slug' => 'examen-mentor', 'title' => 'Examen assigné']);
    ExamAttempt::query()->forceCreate(['uuid' => (string) Str::uuid(), 'reference' => 'EX-MENTOR', 'exam_id' => $examen->id, 'user_id' => $this->citoyen->id, 'started_at' => now(), 'pass_score' => 50, 'max_points' => 20, 'status' => 'pending', 'assigned_to' => $mentor->id]);

    Verification::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $this->citoyen->id, 'assigned_to' => $mentor->id, 'assigned_at' => now(), 'type' => 'text', 'status' => 'human_review', 'claim' => 'Affirmation assignée', 'cost_units' => 1]);

    VerifiedFact::query()->forceCreate(['uuid' => (string) Str::uuid(), 'title' => 'Fait du mentor', 'summary' => 'R.', 'verdict' => 'false', 'keywords' => 'mot', 'created_by' => $mentor->id]);

    $page = $this->actingAs($this->admin, 'staff')->get(route('staff.users.show', $mentor))->assertOk();

    foreach (['cours-encadres', 'corrections-devoirs', 'corrections-examens', 'verifications-assignees', 'faits-rediges'] as $onglet) {
        $page->assertSee('data-vfu-onglet="'.$onglet.'"', escape: false);
    }

    expect(ficheDonnees($this->admin, $mentor, 'cours-encadres')->json('data'))->toHaveCount(1)
        ->and(ficheDonnees($this->admin, $mentor, 'cours-encadres')->json('data.0.inscrits'))->toBe(1)
        ->and(ficheDonnees($this->admin, $mentor, 'corrections-devoirs')->json('data.0.apprenant'))->toBe('Rhodes Agossou')
        ->and(ficheDonnees($this->admin, $mentor, 'corrections-examens')->json('data.0.examen'))->toBe('Examen assigné')
        ->and(ficheDonnees($this->admin, $mentor, 'verifications-assignees')->json('data.0.auteur'))->toBe('Rhodes Agossou')
        ->and(ficheDonnees($this->admin, $mentor, 'faits-rediges')->json('data.0.titre'))->toBe('Fait du mentor');

    // Le citoyen n'a aucun onglet de périmètre.
    $fiche = $this->actingAs($this->admin, 'staff')->get(route('staff.users.show', $this->citoyen))->assertOk();
    foreach (['cours-encadres', 'corrections-devoirs', 'corrections-examens', 'verifications-assignees', 'faits-rediges'] as $onglet) {
        $fiche->assertDontSee('data-vfu-onglet="'.$onglet.'"', escape: false);
    }
});

it('garde les corrections d’un ancien mentor visibles après son rétrogradage', function (): void {
    $ancien = User::factory()->actif()->create();
    $cours = ficheCours('Cours orphelin');
    $devoir = Assignment::query()->forceCreate(['uuid' => (string) Str::uuid(), 'course_id' => $cours->id, 'title' => 'Devoir', 'description' => 'C.']);
    AssignmentSubmission::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $this->citoyen->id, 'assignment_id' => $devoir->id, 'reviewed_by' => $ancien->id]);

    $this->actingAs($this->admin, 'staff')->get(route('staff.users.show', $ancien))
        ->assertOk()
        ->assertSee('data-vfu-onglet="corrections-devoirs"', escape: false)
        ->assertDontSee('data-vfu-onglet="cours-encadres"', escape: false);
});

it('mène à la fiche depuis Utilisateurs et depuis Mentors', function (): void {
    $mentor = User::factory()->actif()->create(['name' => 'Mentor Lien']);
    $mentor->assignRole(UserRole::Mentor->value);

    $this->actingAs($this->admin, 'staff')->get(route('staff.users'))
        ->assertOk()
        ->assertSee('data-vdt-fiche="'.route('staff.users.show', ['user' => '00000000-0000-0000-0000-000000000000']).'"', escape: false);

    $this->actingAs($this->admin, 'staff')->get(route('staff.mentors'))
        ->assertOk()
        ->assertSee('href="'.route('staff.users.show', $mentor).'"', escape: false);
});

// ════════════════════════════════════════════════════════════════ le coût

it('ne multiplie pas les requêtes avec la taille de la page', function (): void {
    $cours = ficheCours('Cours partagé');
    $examen = Exam::query()->forceCreate(['uuid' => (string) Str::uuid(), 'slug' => 'examen-cout', 'title' => 'Examen']);
    $quiz = Quiz::query()->forceCreate(['uuid' => (string) Str::uuid(), 'course_id' => $cours->id, 'title' => 'Quiz']);

    foreach (range(1, 30) as $i) {
        $v = Verification::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $this->citoyen->id, 'type' => 'text', 'status' => 'completed', 'claim' => "A {$i}", 'cost_units' => 1]);
        AnalysisReport::query()->forceCreate(['uuid' => (string) Str::uuid(), 'verification_id' => $v->id, 'verdict' => 'true']);
        // Une remise par devoir : la paire (compte, devoir) est unique.
        $devoir = Assignment::query()->forceCreate(['uuid' => (string) Str::uuid(), 'course_id' => $cours->id, 'title' => "Devoir {$i}", 'description' => 'C.']);
        AssignmentSubmission::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $this->citoyen->id, 'assignment_id' => $devoir->id, 'reviewed_by' => $this->admin->id]);
        ExamAttempt::query()->forceCreate(['uuid' => (string) Str::uuid(), 'reference' => "EX-{$i}", 'exam_id' => $examen->id, 'user_id' => $this->citoyen->id, 'attempt_no' => $i, 'started_at' => now(), 'pass_score' => 50, 'max_points' => 20, 'assigned_to' => $this->admin->id]);
        QuizAttempt::query()->forceCreate(['uuid' => (string) Str::uuid(), 'user_id' => $this->citoyen->id, 'quiz_id' => $quiz->id, 'score' => $i]);
    }

    $compter = function (string $onglet, int $longueur): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        ficheDonnees($this->admin, $this->citoyen, $onglet, ['length' => $longueur])->assertOk();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    };

    foreach (['verifications', 'devoirs', 'examens', 'quiz'] as $onglet) {
        $compter($onglet, 5); // amorce les caches de permissions

        expect($compter($onglet, 30))->toBe($compter($onglet, 5), "onglet {$onglet}");
    }
});

// ═══════════════════════════════════════════════════════════ les actions

it('suspend et rétablit depuis la fiche par le service existant, journalisé', function (): void {
    Livewire::actingAs($this->admin, 'staff')
        ->test(FicheUtilisateur::class, ['user' => $this->citoyen])
        ->call('ouvrirLaSuspension')
        ->set('motifDeSuspension', 'court')
        ->call('suspendre')
        ->assertHasErrors('motifDeSuspension')
        ->set('motifDeSuspension', 'Publication répétée de contenus haineux.')
        ->call('suspendre')
        ->assertHasNoErrors()
        ->assertDispatched(FicheUtilisateur::EVENEMENT_TABLE_A_RECHARGER);

    expect($this->citoyen->fresh()->suspended_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.suspend')->where('target_uuid', $this->citoyen->uuid)->exists())->toBeTrue();

    Livewire::actingAs($this->admin, 'staff')
        ->test(FicheUtilisateur::class, ['user' => $this->citoyen->fresh()])
        ->call('ouvrirLeRetablissement')
        ->call('retablir')
        ->assertDispatched(FicheUtilisateur::EVENEMENT_TABLE_A_RECHARGER);

    expect($this->citoyen->fresh()->suspended_at)->toBeNull();
});

it('refuse la suspension à qui la Policy la refuse', function (): void {
    $lecteur = User::factory()->actif()->create();
    $lecteur->givePermissionTo('user.view');

    Livewire::actingAs($lecteur, 'staff')
        ->test(FicheUtilisateur::class, ['user' => $this->citoyen])
        ->assertDontSee('Suspendre')
        ->call('ouvrirLaSuspension')
        ->assertForbidden();
});

it('ouvre le tiroir d’édition existant depuis la fiche (?modifier=)', function (): void {
    Livewire::withQueryParams(['modifier' => $this->citoyen->uuid])
        ->actingAs($this->admin, 'staff')
        ->test(Utilisateurs::class)
        ->assertSet('formulaireOuvert', true)
        ->assertSet('enEdition', $this->citoyen->uuid);

    // Sans le droit de modifier : la liste, sans tiroir.
    $lecteur = User::factory()->actif()->create();
    $lecteur->givePermissionTo('user.view');

    Livewire::withQueryParams(['modifier' => $this->citoyen->uuid])
        ->actingAs($lecteur, 'staff')
        ->test(Utilisateurs::class)
        ->assertSet('formulaireOuvert', false);
});

it('montre l’historique des connexions sans l’empreinte d’IP, et seulement à qui lit le journal', function (): void {
    HistoriqueConnexion::query()->create(['user_id' => $this->citoyen->id, 'espace' => 'citoyen', 'resultat' => 'reussie', 'methode' => 'mot_de_passe', 'navigateur' => 'Firefox', 'systeme' => 'Linux', 'ip_tronquee' => '41.138.2.0', 'ip_hash' => 'EMPREINTE-SECRETE']);

    $reponse = ficheDonnees($this->admin, $this->citoyen, 'connexions')->assertOk();

    expect($reponse->json('recordsTotal'))->toBe(1)
        ->and($reponse->json('data.0.reseau'))->toBe('41.138.2.0')
        ->and($reponse->getContent())->not->toContain('EMPREINTE-SECRETE');

    $lecteur = User::factory()->actif()->create();
    $lecteur->givePermissionTo('user.view');
    ficheDonnees($lecteur, $this->citoyen, 'connexions')->assertForbidden();
});

it('ne montre les lectures du Hub qu’avec le consentement du compte', function (): void {
    ficheDonnees($this->admin, $this->citoyen, 'lectures-hub')->assertForbidden();

    app(HistoriqueDuHub::class)->definir($this->citoyen, true, '127.0.0.1', 'test');
    ConsultationHub::query()->create(['user_id' => $this->citoyen->id, 'rubrique' => 'actualite', 'cible_id' => 1, 'titre' => 'Une actualité lue', 'action' => 'lu']);

    ficheDonnees($this->admin, $this->citoyen, 'lectures-hub')->assertOk()->assertJsonPath('data.0.titre', 'Une actualité lue');

    app(HistoriqueDuHub::class)->definir($this->citoyen, false, '127.0.0.1', 'test');
    ficheDonnees($this->admin, $this->citoyen, 'lectures-hub')->assertForbidden();
});
