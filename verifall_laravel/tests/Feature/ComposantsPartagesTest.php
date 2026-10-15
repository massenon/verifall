<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| Composants partagés — conformité au design system
|--------------------------------------------------------------------------
| Ces tests verrouillent ce qu'une relecture visuelle laisse passer : le
| vocabulaire de classes du prototype, les libellés de verdict imposés, et
| l'absence de double pastille sur les badges staff.
*/

/**
 * Les DEUX espaces authentifiés sont désormais protégés — le back-office par le
 * guard staff et la 2FA, l'espace citoyen par CompteActif. Ces tests portent
 * sur le RENDU des shells, pas sur l'authentification : on ouvre donc la porte
 * explicitement plutôt que de relâcher la protection.
 */
function commeStaff()
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $staff = User::factory()->actif()->create();
    $staff->assignRole(UserRole::Admin->value);

    return test()->actingAs($staff, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp]);
}

function commeCitoyen()
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $citoyen = User::factory()->actif()->create();
    $citoyen->assignRole(UserRole::Citoyen->value);

    return test()->actingAs($citoyen, 'web');
}

/**
 * Ouvre la porte correspondant à l'espace visé.
 *
 * Chaque shell a son garde : s'authentifier sur le mauvais guard produit une
 * redirection, dont le corps vide fait passer à tort toute assertion d'absence.
 */
function visiteurDe(string $route)
{
    if (str_starts_with($route, 'staff.')) {
        return commeStaff();
    }

    if (str_starts_with($route, 'citizen.')) {
        return commeCitoyen();
    }

    return test();
}

it('rend les trois shells sans erreur', function (string $route): void {
    visiteurDe($route)->get(route($route))->assertOk();
})->with([
    'public' => 'public.why',
    'citoyen' => 'citizen.dashboard',
    'staff' => 'staff.dashboard',
]);

it('respecte la cascade CSS imposée, tailwind en dernier', function (): void {
    $html = commeStaff()->get(route('staff.dashboard'))->content();

    preg_match_all('#assets/css/([^"\']+\.css)#', $html, $m);
    $ordre = array_values(array_unique($m[1]));

    /*
     | fonts -> citizen -> staff -> quill -> tailwind -> shell staff -> couche
     | applicative. Une inversion casserait la spécificité, donc le rendu.
     |
     | `staff-shell.css` est APRÈS tailwind : c'est la place qu'occupe, dans les
     | 59 pages du prototype, le bloc <style> dont il est l'extraction. Devant,
     | ses règles de sidebar seraient écrasées et les rubriques repliables
     | retomberaient à la moitié de leur hauteur.
     */
    expect($ordre)->toBe([
        'fonts/fonts.css',
        'citizen.css',
        'staff.css',
        'vendor/quill.snow.css',
        'tailwind.css',
        'staff-shell.css',
        'verifall-app.css',
        // Après la couche applicative : la barre supérieure redéfinit
        // `--topbar-h`, dont dépendent la barre latérale et le contenu.
        'staff-topbar.css',
        // Les feuilles d'écran (`@stack('styles')`) ferment toujours la marche.
        'pages/staff-tableau-de-bord.css',
    ]);
});

it('ne référence aucun CDN tiers', function (string $route): void {
    // SEO_PERFORMANCE : polices et CSS auto-hébergées, aucune dépendance réseau.
    $reponse = visiteurDe($route)->get(route($route))->assertOk();

    // L'assertOk() n'est pas décoratif : sur une redirection, le corps est vide
    // et « ne contient pas de CDN » serait vrai sans rien prouver.
    expect($reponse->content())
        ->not->toContain('cdn.tailwindcss.com')
        ->not->toContain('fonts.googleapis.com');
})->with(['public.why', 'citizen.dashboard', 'staff.dashboard']);

it('marque une seule entrée de navigation comme active', function (): void {
    $html = commeCitoyen()->get(route('citizen.catalogue'))->assertOk()->content();

    expect(substr_count($html, 'sidebar-link is-active'))->toBe(1)
        ->and(substr_count($html, 'aria-current="page"'))->toBeGreaterThanOrEqual(1);
});

it('n\'envoie jamais le staff dans l\'espace citoyen', function (): void {
    // Régression : compte-user/dashboard.html et compte-admin/dashboard.html
    // portent le même nom de fichier ; le shell staff pointait vers le citoyen.
    $html = commeStaff()->get(route('staff.dashboard'))->content();

    expect($html)->toContain(route('staff.dashboard'))
        ->and($html)->not->toContain(route('citizen.dashboard'));
});

describe('badge de statut', function (): void {
    it('emploie les libellés de verdict exacts du dictionnaire', function (string $statut, string $attendu): void {
        expect(Blade::render('<x-badge-statut :statut="$s" />', ['s' => $statut]))
            ->toContain($attendu);
    })->with([
        ['vrai', 'VRAI'],
        ['faux', 'FAUX'],
        ['trompeur', 'TROMPEUR'],
        ['non-verifiable', 'NON VÉRIFIABLE'],
    ]);

    it('porte une pastille en variante citoyen, où le CSS n\'en fournit pas', function (): void {
        expect(Blade::render('<x-badge-statut statut="faux" />'))
            ->toContain('●')
            ->toContain('vbadge-false');
    });

    it('n\'en ajoute pas en variante staff, où .st-badge::before existe déjà', function (): void {
        // Sans cette distinction, le staff affichait deux points côte à côte.
        expect(Blade::render('<x-badge-statut variant="staff" statut="publie" />'))
            ->not->toContain('●');
    });
});

describe('accordéon KPI', function (): void {
    it('est présent sur chaque grille, comme l\'exige la spécification', function (): void {
        $html = Blade::render('<x-kpi-grid><x-kpi value="4" label="Vérifications" /></x-kpi-grid>');

        expect($html)->toContain('data-kpi-accordion')
            ->toContain('kpi-toggle')
            ->toContain('aria-expanded="false"');
    });

    it('utilise le vocabulaire de classes propre à chaque design system', function (): void {
        expect(Blade::render('<x-kpi value="8" label="Badges" />'))
            ->toContain('kpi-card')->toContain('kpi-value')
            ->and(Blade::render('<x-kpi variant="staff" value="8" label="Badges" />'))
            ->toContain('st-kpi')->toContain('st-kpi-v');
    });
});

describe('table et cartes', function (): void {
    it('réserve la table au desktop et les cartes au mobile', function (): void {
        // « Jamais de table miniature » : les deux rendus sont exclusifs.
        expect(Blade::render('<x-data-table><x-slot:head><th>A</th></x-slot:head><tr><td>1</td></tr></x-data-table>'))
            ->toContain('hidden lg:block')
            ->and(Blade::render('<x-card-list>carte</x-card-list>'))
            ->toContain('lg:hidden');
    });
});

describe('suppression', function (): void {
    it('nomme la cible et énonce les cascades', function (): void {
        $html = Blade::render(
            '<x-delete-modal cible="OSINT Fondamentaux" :cascades="$c" />',
            ['c' => ['3 sections et 8 leçons', '412 inscriptions']],
        );

        expect($html)->toContain('OSINT Fondamentaux')
            ->toContain('3 sections et 8 leçons')
            ->toContain('412 inscriptions')
            // Une action irréversible s'annonce comme telle et reste annulable.
            ->toContain('Annuler')
            ->toContain('role="alertdialog"');
    });
});

describe('pages d\'erreur', function (): void {
    it('s\'affiche avec le copywriting Vérif\'All et sans détail technique', function (int $code, string $extrait): void {
        $html = view("errors.{$code}")->render();

        expect($html)->toContain((string) $code)
            ->toContain($extrait)
            ->toContain('boussole contre la désinformation numérique')
            // Aucune fuite technique, quelle que soit la panne (SECURITE_RGPD §3).
            ->not->toContain('vendor/laravel')
            ->not->toContain('Stack trace');
    })->with([
        [404, 'égarée… mais pas vous'],   // sans apostrophe : Blade l'échappe en &#039;
        [419, 'Session expirée par sécurité'],
        [429, 'Doucement'],
        [500, 'Nos équipes sont dessus'],
        [503, 'Maintenance en cours'],
        [403, 'Accès non autorisé'],
    ]);

    it('ne dépend ni de la session ni du générateur de nonce', function (): void {
        // Une page d'erreur doit s'afficher quand tout le reste est tombé.
        $html = view('errors.500')->render();

        expect($html)->not->toContain('csrf')
            ->not->toContain('livewire');
    });
});
