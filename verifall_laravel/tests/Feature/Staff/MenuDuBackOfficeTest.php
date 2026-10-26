<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| La barre latérale du back-office
|--------------------------------------------------------------------------
|
| Elle affichait **56 liens à tout le monde**. Quatre étaient conditionnés ; les
| cinquante-deux autres promettaient à un mentor des écrans que son rôle lui
| refuse — le clic répondait 403.
|
| Ce que ces tests tiennent :
|
|   · **le menu ne peut pas mentir** — la visibilité est LUE sur le middleware
|     `can:` de chaque route, jamais déclarée à côté ;
|   · **chaque entrée est gardée**, sauf le tableau de bord, nommé ;
|   · **chaque entrée pointe une route qui existe** ;
|   · un mentor et un administrateur ne voient pas la même chose.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** Un compte du personnel, avec le rôle demandé. */
function membreAvecRole(string $role): User
{
    $membre = User::factory()->actif()->create();
    $membre->assignRole($role);

    return $membre;
}

/** @return list<string> toutes les routes déclarées au menu */
function routesDuMenu(): array
{
    $routes = [];

    foreach (MenuDuBackOffice::GROUPES as $entrees) {
        foreach (array_keys($entrees) as $route) {
            $routes[] = $route;
        }
    }

    return $routes;
}

// ══════════════════════════════════════════════════ la structure du menu

it('ne déclare que des routes qui EXISTENT', function () {
    /*
     | Une entrée vers une route supprimée lève `RouteNotFoundException` au
     | rendu — donc sur TOUTES les pages du back-office à la fois. Le menu est
     | le seul endroit du produit où une faute de frappe casse tout l'écran.
     */
    $manquantes = array_values(array_filter(
        routesDuMenu(),
        static fn (string $route): bool => Route::getRoutes()->getByName($route) === null,
    ));

    expect($manquantes)->toBe([]);
});

it('ne déclare deux fois QUE les routes nommées comme telles', function () {
    /*
     | Un doublon n'est pas forcément un défaut, et j'ai appris la nuance en la
     | cassant : j'ai retiré celui de `staff.unit-journal` en le prenant pour une
     | négligence. Il est délibéré — deux portes vers un même écran, pour que le
     | responsable sécurité et le gestionnaire d'unités y arrivent chacun par son
     | groupe — et `SecuriteEtMaintenanceTest` le documentait déjà.
     |
     | Le test tient donc la règle utile : un doublon est permis s'il est NOMMÉ,
     | avec sa raison. Un doublon qui apparaît sans être déclaré fait échouer.
     */
    $routes = routesDuMenu();
    $doubles = array_values(array_unique(array_diff_assoc($routes, array_unique($routes))));
    $nonAssumes = array_values(array_diff($doubles, array_keys(MenuDuBackOffice::DOUBLONS_ASSUMES)));

    expect($nonAssumes)->toBe([], sprintf(
        "Ces routes apparaissent deux fois sans être déclarées dans DOUBLONS_ASSUMES :\n  %s",
        implode("\n  ", $nonAssumes),
    ));
});

it('donne une RAISON à chaque doublon assumé', function () {
    // Même discipline que les permissions orphelines : une liste d'exceptions
    // sans motifs se transforme en liste qu'on allonge.
    $sansRaison = array_keys(array_filter(
        MenuDuBackOffice::DOUBLONS_ASSUMES,
        static fn (string $raison): bool => mb_strlen(trim($raison)) <= 20,
    ));

    expect($sansRaison)->toBe([]);
});

it('garde CHAQUE entrée, sauf celles nommées', function () {
    /*
     | Une route de back-office sans `can:` est ouverte à quiconque franchit la
     | porte du personnel — y compris un compte dont le seul rôle est « Mentor ».
     |
     | Ce test a trouvé `staff.unit-journal` : sa garde vivait DANS le composant,
     | invisible du routeur et du menu. Elle est devenue une Gate nommée.
     */
    $sansGarde = array_values(array_filter(
        routesDuMenu(),
        static fn (string $route): bool => ! MenuDuBackOffice::estGardee($route)
            && ! in_array($route, MenuDuBackOffice::SANS_GARDE_ASSUMEE, true),
    ));

    expect($sansGarde)->toBe([], sprintf(
        "Ces routes de menu n'ont aucune garde `can:` :\n  %s\n".
        'Ajoutez-la sur la route — pas dans le composant, que le menu ne sait pas lire.',
        implode("\n  ", $sansGarde),
    ));
});

// ═══════════════════════════════════════════════ ce que chacun voit

it('ne montre à un MENTOR que ce qu’il peut atteindre', function () {
    $mentor = membreAvecRole(UserRole::Mentor->value);

    $vues = collect(MenuDuBackOffice::pour($mentor))->flatten(1)->pluck('route');

    // Ce qu'il doit voir : son espace, et l'Académie qu'il alimente.
    expect($vues)->toContain('staff.my-students')
        ->and($vues)->toContain('staff.courses')
        // Ce qu'il ne doit PAS voir : les comptes, les rôles, les réglages.
        ->and($vues)->not->toContain('staff.users')
        ->and($vues)->not->toContain('staff.roles')
        ->and($vues)->not->toContain('staff.settings')
        ->and($vues)->not->toContain('staff.backups');
});

it('montre à l’ADMINISTRATEUR ce que le mentor ne voit pas', function () {
    $admin = membreAvecRole(UserRole::Admin->value);

    $vues = collect(MenuDuBackOffice::pour($admin))->flatten(1)->pluck('route');

    expect($vues)->toContain('staff.users')
        ->and($vues)->toContain('staff.roles')
        ->and($vues)->toContain('staff.settings');
});

it('donne au mentor un menu strictement PLUS PETIT', function () {
    // La formulation « chacun sa barre » se vérifie ainsi : ce ne sont pas deux
    // menus écrits séparément, c'est le même filtré — donc l'un est inclus dans
    // l'autre.
    $mentor = collect(MenuDuBackOffice::pour(membreAvecRole(UserRole::Mentor->value)))
        ->flatten(1)->pluck('route');
    $admin = collect(MenuDuBackOffice::pour(membreAvecRole(UserRole::Admin->value)))
        ->flatten(1)->pluck('route');

    // Seule exception, voulue : la porte d'entrée. Le mentor a SON tableau de
    // bord (P4-2), l'administrateur celui de la plateforme — un seul chacun.
    expect($mentor->count())->toBeLessThan($admin->count())
        ->and($mentor->diff($admin)->values()->all())->toBe(['staff.mentor.dashboard']);
});

it('n’affiche AUCUN groupe vide', function () {
    /*
     | Un intitulé « EXAMENS » suivi de rien se lit comme une panne. Les groupes
     | sans entrée visible ne sont pas rendus.
     */
    $menu = MenuDuBackOffice::pour(membreAvecRole(UserRole::Mentor->value));

    foreach ($menu as $groupe => $entrees) {
        expect($entrees)->not->toBe([], "Le groupe « {$groupe} » est vide.");
    }
});

it('ne rend RIEN à un compte sans personne', function () {
    expect(MenuDuBackOffice::pour(null))->toBe([]);
});

// ══════════════════════════════════════════════════ le rendu réel

it('rend la barre sans lien interdit, sur une page réelle', function () {
    /*
     | Le test qui compte pour de vrai : ce que le mentor VOIT dans la page, pas
     | ce que la classe calcule. Un gabarit qui oublierait le filtre passerait
     | tous les tests ci-dessus.
     */
    $mentor = membreAvecRole(UserRole::Mentor->value);

    $this->actingAs($mentor, 'staff')
        ->get(route('staff.my-students'))
        ->assertOk()
        ->assertSee('Mes apprenants')
        ->assertDontSee('Rôles et permissions')
        ->assertDontSee('Modèles de courriels');
});

it('ouvre le groupe de la page courante', function () {
    // Sans cela, on arrive sur une page dont l'entrée de menu est repliée, et
    // l'on ne voit pas où l'on se trouve.
    $admin = membreAvecRole(UserRole::Admin->value);

    $html = $this->actingAs($admin, 'staff')
        ->get(route('staff.settings'))
        ->assertOk()
        ->content();

    // Le groupe « Système » est rendu ouvert et marqué comme courant.
    expect(preg_match('/data-groupe="systeme"\s+open\s+data-courant/', $html))->toBe(1);
});
