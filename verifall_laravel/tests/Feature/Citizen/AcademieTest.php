<?php

declare(strict_types=1);

use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\Lms\Exceptions\CoursIndisponible;
use App\Core\Lms\Services\DelivranceCertificat;
use App\Core\Lms\Services\EvaluationQuiz;
use App\Core\Lms\Services\InscriptionAuCours;
use App\Core\Lms\Services\ProgressionAcademie;
use App\Enums\MotifMouvementUnite;
use App\Enums\NiveauCours;
use App\Enums\StatutInscription;
use App\Enums\UserRole;
use App\Livewire\Citizen\Catalogue;
use App\Livewire\Citizen\Cours as FicheCours;
use App\Livewire\Public\VerifierCertificat;
use App\Models\Answer;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\UnitLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Académie — inscription, progression, quiz, certificat
|--------------------------------------------------------------------------
| Deux fautes seraient impardonnables ici : débiter deux fois pour un cours,
| et délivrer un certificat à quelqu'un qui n'a pas fait le travail. Le second
| est le pire : un diplôme qui ne prouve rien décrédibilise tous les autres.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 34, 'xp' => 0])->save();
});

/** Un cours publié, avec le nombre de leçons demandé. */
function coursDeTest(int $lecons = 2, int $cout = 0, bool $publie = true): Course
{
    $cours = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::random(8),
        'title' => 'Vérifier une image',
        'description' => 'Recherche inversée, métadonnées, géolocalisation.',
        'category' => 'OSINT',
        'level' => NiveauCours::Debutant->value,
        'duration_label' => '30 min',
        'cost_units' => $cout,
        'outcomes' => ['Faire une recherche inversée'],
        'is_published' => $publie,
        'published_at' => now(),
    ]);

    for ($i = 1; $i <= $lecons; $i++) {
        Lesson::create([
            'uuid' => (string) Str::uuid(),
            'course_id' => $cours->id,
            'position' => $i,
            'title' => 'Leçon '.$i,
            'minutes' => 5,
            'content' => '<p>Contenu.</p>',
        ]);
    }

    return $cours->fresh(['lessons', 'quizzes', 'assignments']);
}

/** Un quiz à deux questions, dont on connaît les bonnes réponses. */
function quizDeTest(Course $cours, int $seuil = 70): Quiz
{
    $quiz = Quiz::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'position' => 1,
        'title' => 'Quiz final',
        'minutes' => 5,
        'pass_score' => $seuil,
        'xp_reward' => 100,
    ]);

    foreach ([1, 2] as $rang) {
        $question = Question::create([
            'uuid' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'position' => $rang,
            'prompt' => 'Question '.$rang,
            'explanation' => 'Parce que.',
        ]);

        Answer::create(['question_id' => $question->id, 'position' => 1, 'label' => 'Bonne', 'is_correct' => true]);
        Answer::create(['question_id' => $question->id, 'position' => 2, 'label' => 'Mauvaise', 'is_correct' => false]);
    }

    return $quiz->fresh(['questions.answers']);
}

/** Lit toutes les leçons d'un cours, en respectant l'ordre. */
function lireToutesLesLecons(User $utilisateur, Course $cours): void
{
    $progression = app(ProgressionAcademie::class);

    foreach ($cours->loadMissing('lessons')->lessons as $lecon) {
        $progression->marquerLeconLue($utilisateur, $lecon, ProgressionAcademie::SECONDES_DE_LECTURE_REQUISES);
    }
}

describe('inscription', function (): void {
    it('inscrit sans rien débiter sur un cours gratuit', function (): void {
        $cours = coursDeTest();

        $inscription = app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        expect($inscription->status)->toBe(StatutInscription::Inscrit)
            ->and($inscription->paid_units)->toBe(0)
            ->and($this->citoyen->fresh()->units)->toBe(34)
            ->and($cours->fresh()->students_count)->toBe(1);
    });

    it('débite le prix affiché sur un cours premium', function (): void {
        $cours = coursDeTest(cout: 10);

        $inscription = app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        expect($inscription->paid_units)->toBe(10)
            ->and($this->citoyen->fresh()->units)->toBe(24)
            ->and(UnitLog::where('reason', MotifMouvementUnite::CoursPremium->value)->exists())->toBeTrue();
    });

    it('ne débite pas deux fois quand on clique deux fois', function (): void {
        $cours = coursDeTest(cout: 10);
        $service = app(InscriptionAuCours::class);

        $premiere = $service->inscrire($this->citoyen, $cours);
        $seconde = $service->inscrire($this->citoyen, $cours);

        // Du point de vue de la personne, le résultat voulu est atteint : ce
        // n'est pas une erreur, c'est un double-clic.
        expect($seconde->id)->toBe($premiere->id)
            ->and($this->citoyen->fresh()->units)->toBe(24)
            ->and(Enrollment::where('user_id', $this->citoyen->id)->count())->toBe(1)
            ->and($cours->fresh()->students_count)->toBe(1);
    });

    it('refuse quand le solde ne suffit pas', function (): void {
        $cours = coursDeTest(cout: 99);

        expect(fn () => app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours))
            ->toThrow(SoldeInsuffisant::class);

        expect(Enrollment::count())->toBe(0)
            ->and($this->citoyen->fresh()->units)->toBe(34);
    });

    it('refuse un cours non publié', function (): void {
        $cours = coursDeTest(publie: false);

        expect(fn () => app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours))
            ->toThrow(CoursIndisponible::class);
    });

    it('applique la gratuité du mois plutôt que le prix affiché', function (): void {
        $cours = coursDeTest(cout: 10);
        $cours->forceFill(['free_until' => now()->addWeek()])->save();

        $inscription = app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours->fresh());

        // Le tarif est relu AU MOMENT du débit : le cours a pu devenir offert
        // depuis l'affichage de la page.
        expect($inscription->paid_units)->toBe(0)
            ->and($this->citoyen->fresh()->units)->toBe(34);
    });
});

describe('progression', function (): void {
    it('ouvre les leçons dans l\'ordre, pas avant', function (): void {
        $cours = coursDeTest(lecons: 3);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        $progression = app(ProgressionAcademie::class);
        [$une, $deux, $trois] = $cours->loadMissing('lessons')->lessons->all();

        expect($progression->leconOuverte($this->citoyen, $une))->toBeTrue()
            ->and($progression->leconOuverte($this->citoyen, $deux))->toBeFalse()
            ->and($progression->leconOuverte($this->citoyen, $trois))->toBeFalse();

        $progression->marquerLeconLue($this->citoyen, $une, 45);

        expect($progression->leconOuverte($this->citoyen, $deux))->toBeTrue()
            // Sauter à la dernière pour cocher le cours viderait le certificat
            // de son sens.
            ->and($progression->leconOuverte($this->citoyen, $trois))->toBeFalse();
    });

    it('refuse une leçon lue trop vite', function (): void {
        $cours = coursDeTest(lecons: 1);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        $progression = app(ProgressionAcademie::class);
        $lecon = $cours->loadMissing('lessons')->lessons->first();

        // Sans temps de lecture minimal, terminer un cours se réduirait à
        // cliquer six fois sur « Suivant ».
        expect($progression->marquerLeconLue($this->citoyen, $lecon, 5))->toBeFalse()
            ->and($progression->leconAcquise($this->citoyen, $lecon))->toBeFalse();

        expect($progression->marquerLeconLue($this->citoyen, $lecon, 45))->toBeTrue();
    });

    it('compte les étapes, pas les seules leçons', function (): void {
        $cours = coursDeTest(lecons: 3);
        quizDeTest($cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        $progression = app(ProgressionAcademie::class);
        $progression->marquerLeconLue($this->citoyen, $cours->loadMissing('lessons')->lessons->first(), 45);

        // Une leçon sur quatre étapes — trois leçons plus un quiz.
        expect($progression->avancement($this->citoyen, $cours->fresh()))->toBe(25);
    });

    it('ne dépasse jamais 100 % quand on relit une leçon', function (): void {
        $cours = coursDeTest(lecons: 2);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        $progression = app(ProgressionAcademie::class);
        $lecon = $cours->loadMissing('lessons')->lessons->first();

        $progression->marquerLeconLue($this->citoyen, $lecon, 45);
        $progression->marquerLeconLue($this->citoyen, $lecon, 90);

        expect($progression->avancement($this->citoyen, $cours))->toBe(50);
    });

    it('fige la date d\'achèvement à la première complétion', function (): void {
        $cours = coursDeTest(lecons: 1);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        lireToutesLesLecons($this->citoyen, $cours);
        $premiere = Enrollment::first()->completed_at;

        $this->travel(2)->days();
        app(ProgressionAcademie::class)->rafraichir($this->citoyen, $cours);

        // La réécrire à chaque événement ferait d'un cours suivi sur trois mois
        // un cours terminé le jour même.
        expect(Enrollment::first()->completed_at->timestamp)->toBe($premiere->timestamp);
    });
});

describe('quiz', function (): void {
    it('note sur toutes les questions, pas sur celles répondues', function (): void {
        $cours = coursDeTest(lecons: 1);
        $quiz = quizDeTest($cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        $premiere = $quiz->loadMissing('questions.answers')->questions->first();
        $bonne = $premiere->answers->firstWhere('is_correct', true);

        // Une seule réponse donnée, juste : 50 %, pas 100 %.
        $tentative = app(EvaluationQuiz::class)->corriger(
            $this->citoyen, $quiz, [$premiere->id => $bonne->id],
        );

        expect($tentative->score)->toBe(50)
            ->and($tentative->passed)->toBeFalse();
    });

    it('accorde l\'XP une seule fois, même en repassant le quiz', function (): void {
        $cours = coursDeTest(lecons: 1);
        $quiz = quizDeTest($cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        $toutesJustes = $quiz->loadMissing('questions.answers')->questions
            ->mapWithKeys(fn (Question $q): array => [$q->id => $q->answers->firstWhere('is_correct', true)->id])
            ->all();

        $evaluation = app(EvaluationQuiz::class);
        $evaluation->corriger($this->citoyen, $quiz, $toutesJustes);
        $apresLaPremiere = $this->citoyen->fresh()->xp;

        $evaluation->corriger($this->citoyen, $quiz, $toutesJustes);

        // Sans ce garde-fou, repasser dix fois un quiz déjà validé rapporte dix
        // fois les points, et le classement ne mesure plus que l'obstination.
        expect($apresLaPremiere)->toBe(100)
            ->and($this->citoyen->fresh()->xp)->toBe(100);
    });

    it('ignore une réponse qui appartient à une autre question', function (): void {
        $cours = coursDeTest(lecons: 1);
        $quiz = quizDeTest($cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        [$premiere, $seconde] = $quiz->loadMissing('questions.answers')->questions->all();

        // L'identifiant vient d'un formulaire : rien n'empêche d'y coller celui
        // de la bonne réponse d'une autre question.
        $tentative = app(EvaluationQuiz::class)->corriger($this->citoyen, $quiz, [
            $premiere->id => $seconde->answers->firstWhere('is_correct', true)->id,
            $seconde->id => $seconde->answers->firstWhere('is_correct', true)->id,
        ]);

        expect($tentative->score)->toBe(50);
    });

    it('compte quelle mauvaise réponse attire', function (): void {
        $cours = coursDeTest(lecons: 1);
        $quiz = quizDeTest($cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        $question = $quiz->loadMissing('questions.answers')->questions->first();
        $mauvaise = $question->answers->firstWhere('is_correct', false);

        app(EvaluationQuiz::class)->corriger($this->citoyen, $quiz, [$question->id => $mauvaise->id]);

        // C'est la seule raison de préférer une table à un tableau json : savoir
        // où la leçon n'accroche pas.
        expect($mauvaise->fresh()->picked_count)->toBe(1);
    });
});

describe('certificat', function (): void {
    it('est délivré dès que le cours est achevé', function (): void {
        $cours = coursDeTest(lecons: 2);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        lireToutesLesLecons($this->citoyen, $cours);

        $certificat = Certificate::first();

        expect($certificat)->not->toBeNull()
            ->and($certificat->code)->toStartWith('VERIF-')
            // Recopiés à l'émission : un effacement RGPD ne doit pas rendre le
            // document nominatif à nouveau, ni le laisser mentir.
            ->and($certificat->holder_name)->toBe($this->citoyen->name)
            ->and($certificat->course_title)->toBe($cours->title);
    });

    it('n\'est pas délivré tant qu\'un quiz reste à réussir', function (): void {
        $cours = coursDeTest(lecons: 1);
        quizDeTest($cours);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        lireToutesLesLecons($this->citoyen, $cours->fresh());

        expect(Certificate::count())->toBe(0)
            ->and(Enrollment::first()->status)->not->toBe(StatutInscription::Termine);
    });

    it('n\'est jamais délivré deux fois', function (): void {
        $cours = coursDeTest(lecons: 1);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        lireToutesLesLecons($this->citoyen, $cours);
        app(ProgressionAcademie::class)->rafraichir($this->citoyen, $cours);
        app(DelivranceCertificat::class)->delivrer($this->citoyen, $cours);

        expect(Certificate::count())->toBe(1);
    });

    it('porte un code sans caractères confondables', function (): void {
        $cours = coursDeTest(lecons: 1);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);
        lireToutesLesLecons($this->citoyen, $cours);

        $bloc = str_replace('VERIF-', '', Certificate::first()->code);

        // Le code se dicte au téléphone et se recopie d'un CV : ni O ni 0,
        // ni I ni 1, ni S ni 5.
        expect($bloc)->not->toMatch('/[O0I1S5]/');
    });

    it('se vérifie publiquement, sans compte', function (): void {
        $cours = coursDeTest(lecons: 1);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);
        lireToutesLesLecons($this->citoyen, $cours);

        $code = Certificate::first()->code;

        Livewire::test(VerifierCertificat::class)
            ->set('code', $code)
            ->call('verifier')
            ->assertSee('Certificat authentique')
            ->assertSee($this->citoyen->name);
    });

    it('dit « inconnu » et non « faux » sur un code mal recopié', function (): void {
        Livewire::test(VerifierCertificat::class)
            ->set('code', 'VERIF-ZZZZ-ZZZZ')
            ->call('verifier')
            // « Introuvable », jamais « faux » — et c'est le mot du prototype.
            ->assertSee('Certificat introuvable')
            ->assertDontSee('Certificat authentique');
    });

    it('annonce un certificat révoqué au lieu de le cacher', function (): void {
        $cours = coursDeTest(lecons: 1);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);
        lireToutesLesLecons($this->citoyen, $cours);

        $certificat = Certificate::first();
        $certificat->forceFill(['revoked_at' => now(), 'revoked_reason' => 'Fraude avérée'])->save();

        Livewire::test(VerifierCertificat::class)
            ->set('code', $certificat->code)
            ->call('verifier')
            ->assertSee('Certificat révoqué')
            ->assertDontSee('Certificat authentique');
    });
});

describe('les écrans', function (): void {
    it('montre les quatre états de carte du catalogue', function (): void {
        $pasCommence = coursDeTest(lecons: 1);
        $enCours = coursDeTest(lecons: 2);

        app(InscriptionAuCours::class)->inscrire($this->citoyen, $enCours);
        app(ProgressionAcademie::class)->marquerLeconLue($this->citoyen, $enCours->loadMissing('lessons')->lessons->first(), 45);

        Livewire::actingAs($this->citoyen)
            ->test(Catalogue::class)
            ->assertOk()
            ->assertSee($pasCommence->title)
            ->assertSee('Commencer')
            ->assertSee('Continuer le cours');
    });

    it('ne laisse pas donner un avis sans être inscrit', function (): void {
        $cours = coursDeTest(lecons: 1);

        Livewire::actingAs($this->citoyen)
            ->test(FicheCours::class, ['course' => $cours->slug])
            ->set('note', 5)
            ->call('publierAvis');

        // Un avis sur un cours qu'on n'a pas suivi ne dit rien du cours, et la
        // note affichée en catalogue est ce sur quoi les gens se décident.
        expect($cours->fresh()->reviews_count)->toBe(0);
    });

    it('recalcule la note du cours à la publication d\'un avis', function (): void {
        $cours = coursDeTest(lecons: 1);
        app(InscriptionAuCours::class)->inscrire($this->citoyen, $cours);

        Livewire::actingAs($this->citoyen)
            ->test(FicheCours::class, ['course' => $cours->slug])
            ->set('note', 4)
            ->set('commentaire', 'Clair et direct.')
            ->call('publierAvis')
            ->assertHasNoErrors();

        expect($cours->fresh()->rating)->toBe(4.0)
            ->and($cours->fresh()->reviews_count)->toBe(1);
    });
});
