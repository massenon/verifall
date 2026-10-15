<?php

declare(strict_types=1);

use App\Core\Lms\Services\InscriptionAuCours;
use App\Core\Lms\Services\ProgressionAcademie;
use App\Enums\NiveauCours;
use App\Enums\TypeLecon;
use App\Enums\UserRole;
use App\Http\Api\V1\Support\Chronometre;
use App\Models\Answer;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API v1 — l'Académie
|--------------------------------------------------------------------------
| Le point à ne jamais lâcher : le corrigé d'un quiz ne franchit pas le
| réseau avant que la copie soit rendue. Un certificat obtenu avec un
| intercepteur de trafic ne vaut rien — pour personne.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 100])->save();
    $this->jeton = $this->citoyen->createToken('téléphone')->plainTextToken;

    $this->cours = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'reconnaitre-une-intox',
        'title' => 'Reconnaître une intox',
        'description' => 'Les réflexes de base.',
        'category' => 'Fondamentaux',
        'level' => NiveauCours::Debutant->value,
        'duration_label' => '2 h',
        'cost_units' => 0,
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->lecon = Lesson::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $this->cours->id,
        'position' => 1,
        'title' => 'Première leçon',
        'type' => TypeLecon::Texte->value,
        'minutes' => 5,
        'content' => '<p>Le contenu de la leçon.</p>',
    ]);

    $this->seconde = Lesson::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $this->cours->id,
        'position' => 2,
        'title' => 'Deuxième leçon',
        'type' => TypeLecon::Texte->value,
        'minutes' => 5,
        'content' => '<p>La suite.</p>',
    ]);
});

/** Un quiz d'une question, avec une bonne réponse et une mauvaise. */
function quizApiDeTest(Course $cours): Quiz
{
    $quiz = Quiz::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'position' => 1,
        'title' => 'Évaluation finale',
        'minutes' => 10,
        'pass_score' => 70,
        'xp_reward' => 50,
    ]);

    $question = Question::create([
        'uuid' => (string) Str::uuid(),
        'quiz_id' => $quiz->id,
        'position' => 1,
        'prompt' => 'Que faire devant une rumeur ?',
        'explanation' => 'On remonte toujours à la source primaire.',
    ]);

    Answer::create(['question_id' => $question->id, 'position' => 1, 'label' => 'La partager', 'is_correct' => false]);
    Answer::create(['question_id' => $question->id, 'position' => 2, 'label' => 'Chercher la source', 'is_correct' => true]);

    return $quiz;
}

describe('catalogue', function (): void {
    it('liste les cours publiés, paginés par curseur', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'reconnaitre-une-intox')
            ->assertJsonStructure(['meta' => ['page' => ['per_page', 'next_cursor', 'has_more']]]);
    });

    it('ne montre pas un cours non publié', function (): void {
        Course::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'en-preparation',
            'title' => 'En préparation',
            'description' => 'Pas encore prêt.',
            'category' => 'Fondamentaux',
            'duration_label' => '1 h',
            'level' => NiveauCours::Debutant->value,
            'cost_units' => 0,
            'is_published' => false,
        ]);

        $this->withToken($this->jeton)->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Le connaître par son slug ne suffit pas à le lire : sinon on
        // publierait par accident tout ce qui est en préparation.
        $this->withToken($this->jeton)->getJson('/api/v1/courses/en-preparation')
            ->assertStatus(404);
    });

    it('filtre par niveau', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/courses?level='.NiveauCours::Avance->value)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('refuse sans jeton', function (): void {
        $this->getJson('/api/v1/courses')->assertStatus(401);
    });
});

describe('fiche de cours', function (): void {
    it('dit quelles leçons sont ouvertes — le serveur tranche, pas le client', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $reponse = $this->withToken($this->jeton)->getJson('/api/v1/courses/reconnaitre-une-intox');

        $reponse->assertOk()
            ->assertJsonPath('data.lecons.0.ouverte', true)
            // Le déverrouillage séquentiel est une règle métier, pas une
            // décision d'affichage.
            ->assertJsonPath('data.lecons.1.ouverte', false);
    });

    it('ne livre pas le contenu des leçons dans la fiche', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $lecons = $this->withToken($this->jeton)
            ->getJson('/api/v1/courses/reconnaitre-une-intox')->json('data.lecons');

        // Sinon c'est le corps entier du cours qui part à chaque ouverture du
        // catalogue.
        expect($lecons[0])->not->toHaveKey('contenu');
    });
});

describe('inscription', function (): void {
    it('inscrit et rend la progression', function (): void {
        $this->withToken($this->jeton)->postJson('/api/v1/courses/reconnaitre-une-intox/enroll')
            ->assertStatus(201)
            ->assertJsonPath('data.cours_slug', 'reconnaitre-une-intox')
            ->assertJsonStructure(['meta' => ['solde']]);
    });

    it('ne débite pas deux fois quand la requête est rejouée', function (): void {
        $this->cours->forceFill(['cost_units' => 10])->save();

        $this->withToken($this->jeton)->postJson('/api/v1/courses/reconnaitre-une-intox/enroll')
            ->assertStatus(201);
        $this->withToken($this->jeton)->postJson('/api/v1/courses/reconnaitre-une-intox/enroll')
            ->assertStatus(201);

        // Idempotent PAR CONSTRUCTION : c'est ce qui rend l'appel rejouable
        // depuis la file d'attente hors ligne du téléphone.
        expect($this->citoyen->fresh()?->units)->toBe(90)
            ->and(Enrollment::where('user_id', $this->citoyen->id)->count())->toBe(1);
    });

    it('refuse quand le solde ne suffit pas', function (): void {
        $this->cours->forceFill(['cost_units' => 500])->save();

        $this->withToken($this->jeton)->postJson('/api/v1/courses/reconnaitre-une-intox/enroll')
            ->assertStatus(402)
            ->assertJsonPath('error.code', 'solde_insuffisant');
    });
});

describe('leçon', function (): void {
    it('ouvre la première leçon avec son contenu', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $this->withToken($this->jeton)
            ->getJson('/api/v1/courses/reconnaitre-une-intox/lessons/'.$this->lecon->uuid)
            ->assertOk()
            ->assertJsonPath('data.contenu', '<p>Le contenu de la leçon.</p>')
            ->assertJsonStructure(['meta' => ['secondes_requises']]);
    });

    it('refuse une leçon verrouillée', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $this->withToken($this->jeton)
            ->getJson('/api/v1/courses/reconnaitre-une-intox/lessons/'.$this->seconde->uuid)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'droit_manquant');
    });

    it('refuse une leçon qui n\'appartient pas au cours demandé', function (): void {
        $autreCours = Course::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'autre-cours',
            'title' => 'Autre cours',
            'description' => 'Un autre cours.',
            'category' => 'Fondamentaux',
            'duration_label' => '1 h',
            'level' => NiveauCours::Debutant->value,
            'cost_units' => 0,
            'is_published' => true,
        ]);

        $this->withToken($this->jeton)
            ->getJson('/api/v1/courses/'.$autreCours->slug.'/lessons/'.$this->lecon->uuid)
            ->assertStatus(404);
    });

    it('refuse une validation trop rapide pour être une lecture', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $this->withToken($this->jeton)
            ->getJson('/api/v1/courses/reconnaitre-une-intox/lessons/'.$this->lecon->uuid)
            ->assertOk();

        // Le client annonce une heure de lecture ; le serveur n'a mesuré que
        // l'instant qui vient de s'écouler. C'est le MINIMUM qui compte.
        $this->withToken($this->jeton)
            ->postJson('/api/v1/me/lessons/'.$this->lecon->uuid.'/complete', ['secondes' => 3600])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'action_impossible');
    });

    it('valide la leçon quand la durée mesurée suffit', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $this->withToken($this->jeton)
            ->getJson('/api/v1/courses/reconnaitre-une-intox/lessons/'.$this->lecon->uuid)
            ->assertOk();

        // On recule le départ posé par le serveur : c'est la seule façon
        // honnête de simuler le temps sans dormir dans un test.
        $this->travel(ProgressionAcademie::SECONDES_DE_LECTURE_REQUISES + 5)->seconds();

        $this->withToken($this->jeton)
            ->postJson('/api/v1/me/lessons/'.$this->lecon->uuid.'/complete', [
                'secondes' => ProgressionAcademie::SECONDES_DE_LECTURE_REQUISES + 5,
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['avancement', 'statut']]);

        expect(app(ProgressionAcademie::class)->leconAcquise($this->citoyen, $this->lecon))->toBeTrue();
    });

    it('refuse de valider une leçon jamais ouverte', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        // Aucun départ posé : la durée mesurée vaut zéro, et la règle de durée
        // minimale refuse. C'est exactement ce qu'il faut.
        $this->withToken($this->jeton)
            ->postJson('/api/v1/me/lessons/'.$this->lecon->uuid.'/complete', ['secondes' => 999])
            ->assertStatus(422);
    });
});

describe('quiz', function (): void {
    it('rend les questions SANS les bonnes réponses ni les explications', function (): void {
        quizApiDeTest($this->cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $reponse = $this->withToken($this->jeton)
            ->getJson('/api/v1/courses/reconnaitre-une-intox/quiz');

        $reponse->assertOk()->assertJsonStructure(['meta' => ['commence_le']]);

        $question = $reponse->json('data.questions.0');

        /*
         | LE POINT CAPITAL DE TOUT CE FICHIER. Livrer `is_correct` rendrait
         | n'importe quel quiz réussissable à 100 % avec un intercepteur de
         | trafic, et le certificat qui en découle ne vaudrait plus rien.
         */
        expect($question)->not->toHaveKey('explication');

        foreach ($question['reponses'] as $reponsePossible) {
            expect($reponsePossible)->not->toHaveKeys(['is_correct', 'correcte']);
        }
    });

    it('corrige côté serveur et ne rend l\'explication qu\'après la copie', function (): void {
        $quiz = quizApiDeTest($this->cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $question = $quiz->questions()->firstOrFail();
        $bonne = $question->answers()->where('is_correct', true)->firstOrFail();

        Chronometre::demarrer('quiz', $this->citoyen, (string) $quiz->uuid);

        $this->withToken($this->jeton)->postJson('/api/v1/me/quiz/'.$quiz->uuid.'/attempt', [
            'reponses' => [$question->uuid => $bonne->id],
        ])->assertStatus(201)
            ->assertJsonPath('data.reussi', true)
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.corrige.0.bonne_reponse', $bonne->id)
            ->assertJsonPath('data.corrige.0.explication', 'On remonte toujours à la source primaire.');
    });

    it('compte comme fausse une question laissée sans réponse', function (): void {
        $quiz = quizApiDeTest($this->cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        // Compter le score sur les seules questions répondues permettrait
        // d'obtenir 100 % en répondant à une seule.
        $this->withToken($this->jeton)->postJson('/api/v1/me/quiz/'.$quiz->uuid.'/attempt', [
            'reponses' => [],
        ])->assertStatus(201)
            ->assertJsonPath('data.score', 0)
            ->assertJsonPath('data.reussi', false);
    });

    it('répond INTROUVABLE quand le cours n\'a pas d\'évaluation', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/courses/reconnaitre-une-intox/quiz')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'introuvable');
    });
});

describe('progression', function (): void {
    it('liste mes inscriptions', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $this->withToken($this->jeton)->getJson('/api/v1/me/enrollments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cours_slug', 'reconnaitre-une-intox');
    });

    it('rend NUL — et non une erreur — quand il n\'y a rien à reprendre', function (): void {
        // Ce n'est pas une erreur, c'est un état vide : l'écran d'accueil doit
        // pouvoir l'afficher comme tel.
        $this->withToken($this->jeton)->getJson('/api/v1/me/courses/resume')
            ->assertOk()
            ->assertJsonPath('data', null);
    });

    it('propose la prochaine leçon à reprendre', function (): void {
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $this->cours);

        $this->withToken($this->jeton)->getJson('/api/v1/me/courses/resume')
            ->assertOk()
            ->assertJsonPath('data.cours.slug', 'reconnaitre-une-intox')
            ->assertJsonPath('data.prochaine_lecon.uuid', $this->lecon->uuid);
    });

    it('refuse la progression d\'un cours où je ne suis pas inscrit', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/me/courses/reconnaitre-une-intox/progress')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'introuvable');
    });
});

describe('certificats', function (): void {
    it('ne montre que MES certificats', function (): void {
        $autre = User::factory()->actif()->create();

        $sien = Certificate::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'VFACERT7',
            'user_id' => $autre->id,
            'course_id' => $this->cours->id,
            'holder_name' => 'Quelqu\'un d\'autre',
            'course_title' => $this->cours->title,
            'issued_at' => now(),
        ]);

        $this->withToken($this->jeton)->getJson('/api/v1/me/certificates')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // La vérification publique d'un certificat existe déjà, sur le site.
        // Cette route ne doit pas devenir un second oracle où l'on énumère
        // des codes.
        $this->withToken($this->jeton)->getJson('/api/v1/me/certificates/'.$sien->code)
            ->assertStatus(404);
    });
});
