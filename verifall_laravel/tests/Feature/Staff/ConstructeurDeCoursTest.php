<?php

declare(strict_types=1);

use App\Core\Lms\Services\ConstructionDeCours;
use App\Livewire\Staff\CategoriesDeCours;
use App\Livewire\Staff\ConstructeurDeCours;
use App\Livewire\Staff\Cours;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| L'Académie côté administration — lot 6
|--------------------------------------------------------------------------
| Trois invariants portent ce lot, et aucun n'est un CRUD :
|
| 1. `lessons` porte `unique(course_id, position)`. Échanger deux leçons en
|    écrivant l'une puis l'autre viole la contrainte AU MILIEU. Le service passe
|    par une position tampon — et c'est le genre de détail qu'une réécriture
|    depuis un écran perdrait au premier refactoring.
|
| 2. `course.publish` est séparé de `course.update` par la matrice, parce que
|    publier engage la plateforme. La case « publier » ne doit donc PAS figurer
|    au formulaire d'édition, sinon le droit devient contournable.
|
| 3. Le mentor est cantonné à SES ressources. La matrice l'écrit ; sans borne,
|    `course.update` laisserait réécrire le cours d'un autre.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/** Un cours minimal, prêt à recevoir des leçons. */
function unCours(?User $formateur = null): Course
{
    return Course::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::lower(Str::random(6)),
        'title' => 'Vérifier une image',
        'description' => str_repeat('Contenu pédagogique. ', 5),
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '2 h',
        'cost_units' => 0,
        'instructor_id' => $formateur?->id,
        'delivers_certificate' => true,
        'is_published' => false,
    ]);
}

describe('l\'ordre des leçons', function (): void {
    it('échange deux leçons malgré la contrainte d\'unicité', function (): void {
        /*
         | LE TEST QUI JUSTIFIE LE SERVICE. Sans position tampon, la première
         | écriture heurte `unique(course_id, position)` et l'échange échoue —
         | avec une erreur SQL brute à l'écran.
         */
        $cours = unCours();
        $construction = app(ConstructionDeCours::class);

        foreach (['Introduction', 'Les métadonnées', 'La recherche inversée'] as $titre) {
            $construction->ajouterUneLecon($cours, [
                'titre' => $titre, 'type' => 'text', 'minutes' => 5,
                'contenu' => '<p>Texte.</p>', 'video' => null,
            ]);
        }

        $deuxieme = Lesson::query()->where('course_id', $cours->id)->where('position', 2)->firstOrFail();

        $construction->deplacerUneLecon($deuxieme, 'haut');

        $ordre = Lesson::query()->where('course_id', $cours->id)
            ->orderBy('position')->pluck('title')->all();

        expect($ordre)->toBe(['Les métadonnées', 'Introduction', 'La recherche inversée']);
    });

    it('ne fait rien — et ne casse rien — en bout de plan', function (): void {
        // Les flèches sont masquées en bout de liste, mais un bouton masqué
        // n'est pas une garde : l'appel direct doit être inoffensif.
        $cours = unCours();
        $construction = app(ConstructionDeCours::class);

        $premiere = $construction->ajouterUneLecon($cours, [
            'titre' => 'Seule', 'type' => 'text', 'minutes' => 5,
            'contenu' => '<p>Texte.</p>', 'video' => null,
        ]);

        $construction->deplacerUneLecon($premiere, 'haut');

        expect($premiere->refresh()->position)->toBe(1);
    });

    it('referme le trou laissé par une suppression', function (): void {
        /*
         | Sans resserrement, les positions deviendraient 1, 2, 4. L'ordre
         | resterait juste — mais le prochain déplacement chercherait un voisin
         | en position 3 et n'en trouverait pas : le bouton paraîtrait mort.
         */
        $cours = unCours();
        $construction = app(ConstructionDeCours::class);

        foreach (['A', 'B', 'C'] as $titre) {
            $construction->ajouterUneLecon($cours, [
                'titre' => $titre, 'type' => 'text', 'minutes' => 5,
                'contenu' => '<p>Texte.</p>', 'video' => null,
            ]);
        }

        $construction->supprimerUneLecon(
            Lesson::query()->where('course_id', $cours->id)->where('position', 1)->firstOrFail()
        );

        expect(Lesson::query()->where('course_id', $cours->id)->orderBy('position')->pluck('position')->all())
            ->toBe([1, 2]);
    });
});

describe('le contenu des leçons', function (): void {
    it('purifie le HTML AVANT persistance', function (): void {
        // La leçon est rendue à des citoyens : un seul chemin d'écriture qui
        // oublierait la purification suffirait à exécuter chez eux ce qui a
        // été stocké.
        $lecon = app(ConstructionDeCours::class)->ajouterUneLecon(unCours(), [
            'titre' => 'Leçon piégée',
            'type' => 'text',
            'minutes' => 5,
            'contenu' => '<p>Bonjour</p><script>alert(1)</script><img src=x onerror="alert(2)">',
            'video' => null,
        ]);

        expect($lecon->content)
            ->toContain('Bonjour')
            ->not->toContain('<script')
            ->not->toContain('onerror');
    });

    it('oublie l\'adresse vidéo quand la leçon redevient un texte', function (): void {
        /*
         | Sans ce nettoyage, une leçon « texte » garderait l'adresse d'une
         | vidéo : invisible à l'écran, et prête à ressurgir au prochain
         | changement de type.
         */
        $construction = app(ConstructionDeCours::class);

        $lecon = $construction->ajouterUneLecon(unCours(), [
            'titre' => 'Démonstration', 'type' => 'video', 'minutes' => 8,
            'contenu' => null, 'video' => 'https://exemple-video.com/a',
        ]);

        expect($lecon->video_url)->toBe('https://exemple-video.com/a');

        $construction->modifierUneLecon($lecon, [
            'titre' => 'Démonstration', 'type' => 'text', 'minutes' => 8,
            'contenu' => '<p>Transcription.</p>', 'video' => 'https://exemple-video.com/a',
        ]);

        expect($lecon->refresh()->video_url)->toBeNull();
    });
});

describe('les questions de quiz', function (): void {
    it('refuse une question sans bonne réponse', function (): void {
        /*
         | LA GARDE QUI COMPTE. Notée « fausse » quoi que le citoyen coche, une
         | telle question lui fait perdre le quiz sans qu'il puisse comprendre —
         | et le mentor ne le découvre que par un taux d'échec de 100 %.
         */
        $cours = unCours();
        $construction = app(ConstructionDeCours::class);

        $quiz = $construction->ajouterUnQuiz($cours, [
            'titre' => 'Contrôle', 'minutes' => 5, 'seuil' => 70, 'xp' => 10,
        ]);

        expect(fn () => $construction->enregistrerUneQuestion(
            quiz: $quiz, question: null,
            enonce: 'Quelle est la capitale du Bénin ?',
            explication: null,
            propositions: [
                ['label' => 'Cotonou', 'correcte' => false],
                ['label' => 'Porto-Novo', 'correcte' => false],
            ],
        ))->toThrow(RuntimeException::class, 'correcte');
    });

    it('refuse une question à moins de deux propositions', function (): void {
        $construction = app(ConstructionDeCours::class);

        $quiz = $construction->ajouterUnQuiz(unCours(), [
            'titre' => 'Contrôle', 'minutes' => 5, 'seuil' => 70, 'xp' => 0,
        ]);

        expect(fn () => $construction->enregistrerUneQuestion(
            quiz: $quiz, question: null, enonce: 'Vrai ou faux ?', explication: null,
            propositions: [['label' => 'Vrai', 'correcte' => true], ['label' => '  ', 'correcte' => false]],
        ))->toThrow(RuntimeException::class, 'deux propositions');
    });

    it('remplace les propositions plutôt que de les fusionner', function (): void {
        // Fusionner demanderait de faire correspondre anciennes et nouvelles par
        // leur libellé — et corriger une faute de frappe créerait alors une
        // proposition de plus au lieu de la corriger.
        $construction = app(ConstructionDeCours::class);

        $quiz = $construction->ajouterUnQuiz(unCours(), [
            'titre' => 'Contrôle', 'minutes' => 5, 'seuil' => 70, 'xp' => 0,
        ]);

        $question = $construction->enregistrerUneQuestion(
            quiz: $quiz, question: null, enonce: 'Capitale ?', explication: null,
            propositions: [
                ['label' => 'Porto-Novo', 'correcte' => true],
                ['label' => 'Cotonu', 'correcte' => false],
            ],
        );

        $construction->enregistrerUneQuestion(
            quiz: $quiz, question: $question, enonce: 'Capitale ?', explication: null,
            propositions: [
                ['label' => 'Porto-Novo', 'correcte' => true],
                ['label' => 'Cotonou', 'correcte' => false],
            ],
        );

        expect($question->refresh()->answers()->pluck('label')->all())
            ->toBe(['Porto-Novo', 'Cotonou']);
    });
});

describe('la publication d\'un cours', function (): void {
    it('refuse un cours sans leçon', function (): void {
        /*
         | Le catalogue l'afficherait, quelqu'un s'y inscrirait — en dépensant
         | peut-être des unités — et tomberait sur une page vide.
         */
        $cours = unCours();

        Livewire::actingAs($this->admin, 'staff')
            ->test(Cours::class)
            ->call('basculerLaPublication', $cours->uuid);

        expect($cours->refresh()->is_published)->toBeFalse();
    });

    it('publie dès qu\'il y a une leçon, et ne réécrit pas la date d\'origine', function (): void {
        $cours = unCours();

        app(ConstructionDeCours::class)->ajouterUneLecon($cours, [
            'titre' => 'Introduction', 'type' => 'text', 'minutes' => 5,
            'contenu' => '<p>Texte.</p>', 'video' => null,
        ]);

        $composant = Livewire::actingAs($this->admin, 'staff')->test(Cours::class);

        $composant->call('basculerLaPublication', $cours->uuid);
        $premiereMiseEnLigne = $cours->refresh()->published_at;

        expect($cours->is_published)->toBeTrue()
            ->and($premiereMiseEnLigne)->not->toBeNull();

        // Dépublier puis republier ne doit pas rajeunir le cours : c'est cette
        // date qui ordonne les nouveautés du catalogue.
        $composant->call('basculerLaPublication', $cours->uuid);
        $composant->call('basculerLaPublication', $cours->uuid);

        expect($cours->refresh()->published_at?->timestamp)->toBe($premiereMiseEnLigne?->timestamp);
    });

    it('n\'offre PAS la publication dans le formulaire d\'édition', function (): void {
        /*
         | `course.publish` est séparé de `course.update` par la matrice. Une
         | case « publier » au formulaire rendrait le droit contournable par
         | quiconque peut modifier — le test lit le composant, pas la vue,
         | pour attraper aussi une propriété ajoutée sans champ.
         */
        $composant = Livewire::actingAs($this->admin, 'staff')->test(Cours::class)->instance();

        expect(property_exists($composant, 'publie'))->toBeFalse()
            ->and(property_exists($composant, 'estPublie'))->toBeFalse()
            // Et la publication reste joignable, par sa méthode dédiée.
            ->and(method_exists($composant, 'basculerLaPublication'))->toBeTrue();
    });
});

describe('le mentor est cantonné à ses cours', function (): void {
    it('ne peut pas modifier le cours d\'un autre', function (): void {
        $mentor = User::factory()->create();
        $mentor->assignRole('Mentor');

        $autre = User::factory()->create();
        $autre->assignRole('Mentor');

        $cours = unCours($autre);

        expect($mentor->can('update', $cours))->toBeFalse()
            ->and($autre->can('update', $cours))->toBeTrue();
    });

    it('ne publie jamais, même sur son propre cours', function (): void {
        // Publier engage la plateforme : le cours s'affiche au catalogue, se
        // vend en unités et délivre un certificat.
        $mentor = User::factory()->create();
        $mentor->assignRole('Mentor');

        expect($mentor->can('publish', unCours($mentor)))->toBeFalse();
    });

    it('ne voit que ses cours dans la liste', function (): void {
        // Le filtre est dans la REQUÊTE : une Policy autorise une action sur
        // une ligne qu'on lui présente, elle n'empêche pas de la lister.
        $mentor = User::factory()->create();
        $mentor->assignRole('Mentor');

        $autre = User::factory()->create();
        $autre->assignRole('Mentor');

        $sien = unCours($mentor);
        $sien->forceFill(['title' => 'Le cours du mentor'])->save();

        $celuiDeLAutre = unCours($autre);
        $celuiDeLAutre->forceFill(['title' => 'Le cours de quelqu\'un d\'autre'])->save();

        Livewire::actingAs($mentor, 'staff')
            ->test(Cours::class)
            ->assertSee('Le cours du mentor')
            ->assertDontSee('Le cours de quelqu\'un d\'autre');
    });
});

describe('le constructeur', function (): void {
    it('refuse une leçon appartenant à un AUTRE cours', function (): void {
        /*
         | La Policy vérifie le formateur, pas l'appartenance au cours affiché.
         | Sans le `where('course_id')` du composant, un uuid emprunté à un
         | autre cours passerait — et le journal ne le dirait qu'après coup.
         */
        $cours = unCours();
        $autreCours = unCours();

        $leconEtrangere = app(ConstructionDeCours::class)->ajouterUneLecon($autreCours, [
            'titre' => 'Ailleurs', 'type' => 'text', 'minutes' => 5,
            'contenu' => '<p>Texte.</p>', 'video' => null,
        ]);

        expect(fn () => Livewire::actingAs($this->admin, 'staff')
            ->test(ConstructeurDeCours::class, ['slug' => $cours->slug])
            ->call('ouvrirEditionLecon', $leconEtrangere->uuid))
            ->toThrow(ModelNotFoundException::class);
    });

    it('répond et journalise l\'ajout d\'une leçon', function (): void {
        $cours = unCours();

        Livewire::actingAs($this->admin, 'staff')
            ->test(ConstructeurDeCours::class, ['slug' => $cours->slug])
            ->call('ouvrirCreationLecon')
            ->set('leconTitre', 'Reconnaître une image retouchée')
            ->set('leconType', 'text')
            ->set('leconMinutes', '12')
            ->set('leconContenu', '<p>Le contenu de la leçon.</p>')
            ->call('enregistrerLaLecon')
            ->assertHasNoErrors();

        expect($cours->lessons()->count())->toBe(1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson.create']);
    });

    it('exige une adresse pour une leçon vidéo, et pas de contenu', function (): void {
        // Exiger les deux forcerait à écrire un paragraphe de remplissage sous
        // chaque vidéo.
        $cours = unCours();

        Livewire::actingAs($this->admin, 'staff')
            ->test(ConstructeurDeCours::class, ['slug' => $cours->slug])
            ->call('ouvrirCreationLecon')
            ->set('leconTitre', 'La démonstration')
            ->set('leconType', 'video')
            ->set('leconMinutes', '8')
            ->set('leconContenu', '')
            ->set('leconVideo', '')
            ->call('enregistrerLaLecon')
            ->assertHasErrors('leconVideo')
            ->assertHasNoErrors('leconContenu');
    });
});

describe('les catégories de cours', function (): void {
    it('propagent leur renommage à la colonne que lit le catalogue citoyen', function (): void {
        /*
         | `courses.category` reste ce que lisent le catalogue, la fiche de
         | cours et l'API v1. Sans propagation, le rayon garderait l'ancien nom
         | pour toujours — et le seul symptôme serait « le renommage n'a pas
         | marché ».
         */
        $categorie = CourseCategory::query()->create([
            'uuid' => (string) Str::uuid(), 'name' => 'securite',
            'slug' => 'securite-xxxx', 'sort_order' => 10, 'is_published' => true,
        ]);

        $cours = unCours();
        $cours->forceFill(['course_category_id' => $categorie->id, 'category' => 'securite'])->save();

        Livewire::actingAs($this->admin, 'staff')
            ->test(CategoriesDeCours::class)
            ->call('ouvrirEdition', $categorie->uuid)
            ->set('nom', 'Sécurité numérique')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($cours->refresh()->category)->toBe('Sécurité numérique');
    });

    it('ne supprime pas les cours de la catégorie supprimée', function (): void {
        $categorie = CourseCategory::query()->create([
            'uuid' => (string) Str::uuid(), 'name' => 'À retirer',
            'slug' => 'a-retirer-xxxx', 'sort_order' => 10, 'is_published' => true,
        ]);

        $cours = unCours();
        $cours->forceFill(['course_category_id' => $categorie->id])->save();

        Livewire::actingAs($this->admin, 'staff')
            ->test(CategoriesDeCours::class)
            ->call('demanderLaSuppression', $categorie->uuid)
            ->call('supprimer');

        expect(Course::query()->whereKey($cours->id)->exists())->toBeTrue()
            ->and($cours->refresh()->course_category_id)->toBeNull();
    });
});
