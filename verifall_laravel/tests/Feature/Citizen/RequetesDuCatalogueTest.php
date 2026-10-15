<?php

declare(strict_types=1);

use App\Enums\NiveauCours;
use App\Enums\StatutInscription;
use App\Enums\UserRole;
use App\Livewire\Citizen\Catalogue;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le catalogue interroge la base un nombre CONSTANT de fois
|--------------------------------------------------------------------------
|
| ── UNE PAIRE DE PARENTHÈSES CONTREDISAIT LE COMMENTAIRE JUSTE AU-DESSUS ──
|
| `#[Computed]` ne met en cache que l'accès EN PROPRIÉTÉ — `$this->inscriptions`.
| Écrit `$this->inscriptions()`, avec les parenthèses, c'est un appel de méthode
| ordinaire : le cache est contourné et la requête rejouée. À chaque carte de
| cours affichée.
|
| Le commentaire de la méthode annonçait pourtant « Chargées EN UNE FOIS :
| interroger l'inscription depuis chaque carte ferait une requête par cours
| affiché ». L'intention était juste, le code disait le contraire, et rien ne
| les départageait.
|
| C'est un défaut qu'aucun test fonctionnel n'attrape : les pages s'affichent,
| les données sont exactes, les assertions passent. Seul le NOMBRE de requêtes
| change — et il croît avec le catalogue, donc le mal empire à mesure que le
| produit marche.
|
| D'où la forme de ce test : il ne fixe pas un nombre de requêtes, il vérifie
| que ce nombre NE BOUGE PAS quand le catalogue triple. Un seuil chiffré se
| périme à la première optimisation ; l'invariant, lui, reste vrai.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

/** Un cours publié, minimal — seul le nombre compte ici. */
function coursDuCatalogue(int $rang): Course
{
    $cours = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-catalogue-'.$rang,
        'title' => 'Cours '.$rang,
        'description' => 'Description du cours '.$rang,
        'category' => 'OSINT',
        'level' => NiveauCours::Debutant->value,
        'duration_label' => '30 min',
        'cost_units' => 0,
        'outcomes' => ['Un acquis'],
        'is_published' => true,
        'published_at' => now(),
    ]);

    Lesson::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'position' => 1,
        'title' => 'Leçon 1',
        'minutes' => 5,
        'content' => '<p>Contenu.</p>',
    ]);

    return $cours;
}

/** Le nombre de requêtes qu'exige un rendu du catalogue. */
function requetesPourLeCatalogue(User $citoyen): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($citoyen)->test(Catalogue::class)->assertOk();

    $total = count(DB::getQueryLog());

    DB::disableQueryLog();
    DB::flushQueryLog();

    return $total;
}

describe('le nombre de requêtes', function (): void {
    it('ne croît PAS avec le nombre de cours affichés', function (): void {
        foreach (range(1, 3) as $rang) {
            $cours = coursDuCatalogue($rang);

            Enrollment::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $this->citoyen->id,
                'course_id' => $cours->id,
                'status' => StatutInscription::EnCours->value,
                'progress' => 10,
                'started_at' => now(),
            ]);
        }

        $avecTrois = requetesPourLeCatalogue($this->citoyen);

        foreach (range(4, 9) as $rang) {
            $cours = coursDuCatalogue($rang);

            Enrollment::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $this->citoyen->id,
                'course_id' => $cours->id,
                'status' => StatutInscription::EnCours->value,
                'progress' => 10,
                'started_at' => now(),
            ]);
        }

        $avecNeuf = requetesPourLeCatalogue($this->citoyen);

        /*
         | Le catalogue a TRIPLÉ, et chaque cours porte une inscription — le cas
         | exact où l'appel par carte se déclenchait. Le compte doit rester le
         | même : les inscriptions sont chargées une fois, puis lues en mémoire.
         |
         | Avec les parenthèses, ce test échoue en montrant l'écart plutôt qu'en
         | affirmant un seuil : c'est la croissance qui est le défaut, pas le
         | nombre absolu.
         */
        expect($avecNeuf)->toBe($avecTrois,
            "Le catalogue passe de {$avecTrois} à {$avecNeuf} requêtes quand il triple : "
            .'une requête est faite PAR COURS au lieu d\'une seule pour tous.');
    });

    it('lit les inscriptions en PROPRIÉTÉ, jamais en appel de méthode', function (): void {
        /*
         | Le test ci-dessus prouve l'effet ; celui-ci nomme la cause, pour que
         | la prochaine personne à lire l'échec sache quoi corriger.
         |
         | La vérification porte sur le source parce que la différence entre
         | `$this->inscriptions` et `$this->inscriptions()` est invisible à
         | l'exécution : les deux renvoient la même chose, l'un depuis le cache
         | et l'autre depuis la base.
         */
        foreach (['app/Livewire/Citizen/Catalogue.php', 'app/Livewire/Citizen/Forum.php'] as $fichier) {
            $source = file_get_contents(base_path($fichier));

            // `unset($this->mesJaime)` est l'invalidation du cache, pas un appel.
            $appels = preg_match_all('/\$this->(inscriptions|mesJaime)\s*\(/', $source);

            expect($appels)->toBe(0,
                "{$fichier} appelle une propriété #[Computed] avec des parenthèses : "
                .'le cache de Livewire est contourné et la requête rejouée à chaque carte.');
        }
    });
});
