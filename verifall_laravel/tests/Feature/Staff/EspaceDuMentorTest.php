<?php

declare(strict_types=1);

use App\Core\Lms\Services\EspaceDuMentor;
use App\Enums\StatutInscription;
use App\Enums\TypeLecon;
use App\Enums\UserRole;
use App\Livewire\Staff\Cours as EcranCours;
use App\Livewire\Staff\MesContenus;
use App\Livewire\Staff\MesEtudiants;
use App\Livewire\Staff\PerformanceDuMentor;
use App\Livewire\Staff\ProgressionDesApprenants;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| L'espace mentor
|--------------------------------------------------------------------------
|
| Quatre écrans qui manquaient. Le mentor empruntait jusqu'ici les écrans
| d'administration : il voyait TOUS les cours, y compris ceux de ses collègues,
| et n'avait aucun moyen de savoir qui suivait les siens.
|
| **La propriété qui compte est le cloisonnement**, et elle est testée sur
| chacun des quatre : une fuite ici ne fait aucun bruit — l'écran s'affiche, les
| chiffres sont plausibles, et personne ne remarque qu'ils ne sont pas les bons.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mentor = User::factory()->actif()->create(['name' => 'Mentor A']);
    $this->mentor->assignRole(UserRole::Mentor->value);

    $this->collegue = User::factory()->actif()->create(['name' => 'Mentor B']);
    $this->collegue->assignRole(UserRole::Mentor->value);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

/** Un cours publié, attribué à un formateur, avec une leçon. */
function coursDuFormateur(?User $formateur, string $titre): Course
{
    $cours = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => Str::slug($titre).'-'.Str::lower(Str::random(4)),
        'title' => $titre,
        'description' => 'Un cours de test suffisamment décrit pour passer la validation.',
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '1 h',
        'cost_units' => 0,
        'instructor_id' => $formateur?->id,
        'is_published' => true,
    ]);

    Lesson::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'position' => 1,
        'title' => 'Une leçon de '.$titre,
        'type' => TypeLecon::Texte->value,
        'minutes' => 12,
        'content' => '<p>Contenu.</p>',
    ]);

    return $cours;
}

/*
 | Nom PRÉFIXÉ, et ce n'est pas de la coquetterie : les fonctions d'aide de Pest
 | vivent dans l'espace de noms global, partagé par toute la suite. Un
 | `inscritA()` existait déjà dans `RediffusionTest` — le fichier passait seul et
 | faisait tomber la suite entière d'une `FatalException`.
 */
function inscritAuCoursDuMentor(Course $cours, string $nom, int $avancement = 10): Enrollment
{
    $citoyen = User::factory()->actif()->create(['name' => $nom]);
    $citoyen->assignRole(UserRole::Citoyen->value);

    return Enrollment::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $citoyen->id,
        'course_id' => $cours->id,
        'status' => $avancement >= 100
            ? StatutInscription::Termine->value
            : StatutInscription::EnCours->value,
        'progress' => $avancement,
        'paid_units' => 0,
        'last_activity_at' => now(),
        'completed_at' => $avancement >= 100 ? now() : null,
    ]);
}

// ══════════════════════════════════════════════════════ le cloisonnement

it('ne montre à un mentor QUE les inscrits de ses cours', function () {
    /*
     | LA propriété. Une fuite ici ne fait aucun bruit : l'écran s'affiche, les
     | chiffres sont plausibles, et personne ne remarque qu'ils appartiennent à
     | quelqu'un d'autre.
     */
    inscritAuCoursDuMentor(coursDuFormateur($this->mentor, 'Mon cours'), 'Mon inscrite');
    inscritAuCoursDuMentor(coursDuFormateur($this->collegue, 'Le cours du collègue'), 'Son inscrite');

    Livewire::actingAs($this->mentor, 'staff')
        ->test(MesEtudiants::class)
        ->assertSee('Mon inscrite')
        ->assertDontSee('Son inscrite');
});

it('montre une liste VIDE à un mentor sans cours, pas celle de tout le monde', function () {
    /*
     | Le piège serait de se dire « il n'a aucun cours, donc montrons tout ».
     | Le discriminant doit être le DROIT, jamais la présence de données.
     */
    inscritAuCoursDuMentor(coursDuFormateur($this->collegue, 'Le cours du collègue'), 'Son inscrite');

    Livewire::actingAs($this->mentor, 'staff')
        ->test(MesEtudiants::class)
        ->assertDontSee('Son inscrite')
        ->assertSee('Aucun cours ne vous est attribué');
});

it('laisse un administrateur voir toute la plateforme', function () {
    inscritAuCoursDuMentor(coursDuFormateur($this->mentor, 'Mon cours'), 'Une inscrite');
    inscritAuCoursDuMentor(coursDuFormateur($this->collegue, 'Le cours du collègue'), 'Une autre');

    Livewire::actingAs($this->admin, 'staff')
        ->test(MesEtudiants::class)
        ->assertSee('Une inscrite')
        ->assertSee('Une autre');
});

it('borne enfin la LISTE des cours au mentor', function () {
    /*
     | `CoursePolicy::update()` empêchait déjà de modifier le cours d'un
     | collègue, mais la liste les affichait tous : le mentor voyait les
     | inscrits, la note et le tarif de cours qui ne le regardent pas, et
     | découvrait le refus au clic.
     */
    coursDuFormateur($this->mentor, 'Mon cours à moi');
    coursDuFormateur($this->collegue, 'Le cours du collègue');

    Livewire::actingAs($this->mentor, 'staff')
        ->test(EcranCours::class)
        ->assertSee('Mon cours à moi')
        ->assertDontSee('Le cours du collègue');
});

// ═══════════════════════════════════════════════════════════ progression

it('dérive « décroché » sans le stocker', function () {
    /*
     | Une inscription commencée, jamais terminée, muette depuis plus d'un mois.
     | Aucune colonne ne le dit : un statut stocké devient faux le jour où l'on
     | change le seuil, et il faut alors le recalculer sur toute la table.
     */
    $cours = coursDuFormateur($this->mentor, 'Mon cours');

    $actif = inscritAuCoursDuMentor($cours, 'Toujours là', 40);
    $decroche = inscritAuCoursDuMentor($cours, 'Parti depuis', 30);
    $decroche->forceFill(['last_activity_at' => now()->subMonths(3)])->save();

    Livewire::actingAs($this->mentor, 'staff')
        ->test(ProgressionDesApprenants::class)
        ->set('filtreEtat', 'decroche')
        ->assertSee('Parti depuis')
        ->assertDontSee('Toujours là');

    expect($actif->last_activity_at)->not->toBeNull();
});

it('exporte la cohorte filtrée, en CSV lisible par Excel', function () {
    $cours = coursDuFormateur($this->mentor, 'Mon cours');
    inscritAuCoursDuMentor($cours, 'Aïcha Dossou', 60);
    inscritAuCoursDuMentor(coursDuFormateur($this->collegue, 'Ailleurs'), 'Pas la mienne', 50);

    $reponse = Livewire::actingAs($this->mentor, 'staff')
        ->test(ProgressionDesApprenants::class)
        ->call('exporter')
        ->effects['download'] ?? null;

    /*
     | Le contenu d'un `streamDownload` n'existe qu'une fois la fermeture jouée.
     | On la joue dans un tampon plutôt que de fouiller les entrailles du
     | framework de test : c'est le CSV réellement écrit qu'on veut lire, et il
     | ne s'obtient pas autrement.
     */
    $flux = app(ProgressionDesApprenants::class);
    $flux->recherche = '';

    ob_start();
    app()->call([$flux, 'exporter'])->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain('Aïcha Dossou')
        // Le cloisonnement vaut aussi pour l'export : ce serait le moyen le plus
        // simple d'emporter la cohorte d'un collègue.
        ->and($csv)->not->toContain('Pas la mienne')
        /*
         | Le BOM UTF-8. Sans lui, Excel lit le fichier en ANSI et rend « Aïcha »
         | en « AÃ¯cha » : sur un export de noms de personnes, c'est la première
         | chose qu'on remarque et la dernière qu'on pense à corriger.
         */
        ->and(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF");

    expect($reponse)->not->toBeNull();
});

// ═══════════════════════════════════════════════════════════ performance

it('rend 0 % de complétion sur un cours sans inscrit, pas une division par zéro', function () {
    // Un cours qui vient d'être publié n'a pas « échoué » : il n'a pas commencé.
    coursDuFormateur($this->mentor, 'Tout neuf');

    Livewire::actingAs($this->mentor, 'staff')
        ->test(PerformanceDuMentor::class)
        ->assertOk()
        ->assertSee('Pas encore d’inscrit');
});

it('compte la complétion sur les inscriptions terminées', function () {
    $cours = coursDuFormateur($this->mentor, 'Mon cours');
    inscritAuCoursDuMentor($cours, 'A fini', 100);
    inscritAuCoursDuMentor($cours, 'En cours', 20);

    $lignes = app(EspaceDuMentor::class)->performance($this->mentor);

    expect($lignes)->toHaveCount(1)
        ->and($lignes[0]['apprenants'])->toBe(2)
        ->and($lignes[0]['completion'])->toBe(50);
});

// ══════════════════════════════════════════════════════════ mes contenus

it('liste les contenus de MES cours seulement', function () {
    coursDuFormateur($this->mentor, 'Mon cours');
    coursDuFormateur($this->collegue, 'Le cours du collègue');

    Livewire::actingAs($this->mentor, 'staff')
        ->test(MesContenus::class)
        ->assertSee('Une leçon de Mon cours')
        ->assertDontSee('Une leçon de Le cours du collègue');
});

it('filtre par format', function () {
    coursDuFormateur($this->mentor, 'Mon cours');

    Livewire::actingAs($this->mentor, 'staff')
        ->test(MesContenus::class)
        ->set('filtreFormat', 'quiz')
        ->assertDontSee('Une leçon de Mon cours');
});

// ═══════════════════════════════════════════════════════════ les gardes

it('refuse ces écrans à un citoyen, même connecté au personnel', function () {
    // `student.view` et `student.progress` n'appartiennent qu'au mentor et à
    // l'administration. Elles étaient déclarées et employées NULLE PART avant
    // ces écrans : ce test est le premier à les éprouver.
    $citoyen = User::factory()->actif()->create();
    $citoyen->assignRole(UserRole::Citoyen->value);

    expect($citoyen->can('student.view'))->toBeFalse()
        ->and($citoyen->can('student.progress'))->toBeFalse()
        ->and($this->mentor->can('student.view'))->toBeTrue()
        ->and($this->mentor->can('student.progress'))->toBeTrue();
});
