<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Deux défauts partagés du back-office (audit visuel du 14 septembre 2026)
|--------------------------------------------------------------------------
| 1. LE FIL D'ARIANE EN DOUBLE. La barre supérieure refondue porte le fil
|    « Back-office › rubrique › écran », lu dans le menu. Quarante et un écrans
|    en rendaient un second, écrit à la main dans leur en-tête — « Administration
|    / Écran » —, qui répétait le premier une ligne plus bas.
|
| 2. L'ACCORDÉON DES INDICATEURS. Invisible entre 768 et 1023 px (deux feuilles
|    en désaccord sur le seuil) et inopérant en mobile (le clic lié deux fois,
|    dans `citizen.js` et `verifall-app.js` : ouvert puis refermé aussitôt).
|    Rien de cela ne se voit dans une réponse HTTP : on garde donc les SOURCES
|    qui l'ont produit.
*/

/**
 * Les écrans qui gardent, à dessein, un fil d'Ariane de page.
 *
 * Chacun porte sa raison : l'entrée n'est acceptée que si le fil de la barre
 * supérieure ferait perdre une information.
 *
 * @return array<string, string>
 */
function filsDePageAssumes(): array
{
    return [
        'constructeur-de-cours.blade.php' => "Hors menu : la barre ne dit que « Constructeur de cours », et l'en-tête n'a aucun autre chemin vers « Tous les cours » ni le titre du cours abrégé.",
    ];
}

describe('le fil d’Ariane', function (): void {
    it('n’est plus rendu par les vues du back-office, la barre supérieure le porte', function (): void {
        $fautifs = [];

        foreach ((new Finder)->files()->in(resource_path('views/livewire/staff'))->name('*.blade.php') as $vue) {
            if (array_key_exists($vue->getFilename(), filsDePageAssumes())) {
                continue;
            }

            if (preg_match('/aria-label=["\']Fil d(\'|&#0?39;|’)Ariane["\']/u', $vue->getContents()) === 1) {
                $fautifs[] = $vue->getRelativePathname();
            }
        }

        expect($fautifs)->toBe([]);
    });

    it('ne garde aucune exemption devenue sans objet', function (): void {
        foreach (array_keys(filsDePageAssumes()) as $fichier) {
            $chemin = resource_path('views/livewire/staff/'.$fichier);

            expect(is_file($chemin))->toBeTrue()
                ->and((string) file_get_contents($chemin))->toContain('aria-label="Fil d\'Ariane"');
        }
    });

    it('apparaît une seule fois, et la barre nomme l’écran', function (string $route): void {
        /*
         | Un échantillon, pas le menu entier : rendre les 55 écrans prend plus de
         | quatre minutes sur le poste. Il couvre un écran de rubrique simple, un
         | ancien fil à trois niveaux (catégories du blog), l'écran pilote des
         | DataTables, et la route servie par deux entrées de menu.
         |
         | Le libellé attendu est celui de la PREMIÈRE entrée déclarée : c'est
         | celle que retient le gabarit (`DOUBLONS_ASSUMES`).
         */
        $libelle = null;
        foreach (MenuDuBackOffice::GROUPES as $entrees) {
            if ($libelle === null && isset($entrees[$route])) {
                $libelle = $entrees[$route][1];
            }
        }

        expect($libelle)->not->toBeNull();
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'otp_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $this->actingAs($admin, 'staff')->withSession(['staff.last_activity' => now()->timestamp]);

        $html = (string) $this->get(route($route))->assertOk()->getContent();

        expect(substr_count($html, 'aria-label="Fil d\'Ariane"'))->toBe(1)
            ->and($html)->toContain('<li aria-current="page" title="'.e((string) $libelle).'">');
    })->with([
        'staff.users',
        'staff.blog-categories',
        'staff.knowledge',
        'staff.unit-packs',
        'staff.newsletter',
        'staff.unit-journal',
    ]);
});

describe('l’accordéon des indicateurs', function (): void {
    it('n’est lié qu’une fois : aucun autre script ne pose d’écouteur sur son bouton', function (): void {
        $porteurs = [];

        foreach ((new Finder)->files()->in(public_path('assets/js'))->name('*.js')->notPath('vendor') as $script) {
            if (str_contains($script->getContents(), 'kpi-toggle')) {
                $porteurs[] = str_replace('\\', '/', $script->getRelativePathname());
            }
        }

        expect($porteurs)->toBe(['verifall-app.js'])
            ->and(substr_count((string) file_get_contents(public_path('assets/js/verifall-app.js')), "addEventListener('click'"))->toBe(1);
    });

    it('trouve son unique écouteur partout où `citizen.js` le portait autrefois', function (): void {
        /*
         | Le retrait de la liaison de `citizen.js` n'est sûr que si aucun
         | gabarit ne charge ce script SANS `verifall-app.js`.
         */
        $gabarits = [];

        foreach ((new Finder)->files()->in(resource_path('views/components/layouts'))->name('*.blade.php') as $gabarit) {
            $source = $gabarit->getContents();

            if (str_contains($source, 'assets/js/citizen.js')) {
                $gabarits[$gabarit->getFilename()] = str_contains($source, 'assets/js/verifall-app.js');
            }
        }

        ksort($gabarits);

        expect($gabarits)->toBe(['citizen.blade.php' => true, 'staff.blade.php' => true]);
    });

    it('se replie au même seuil dans les deux feuilles, et jamais au-delà de 767 px', function (): void {
        $app = (string) file_get_contents(public_path('assets/css/verifall-app.css'));
        $citoyen = (string) file_get_contents(public_path('assets/css/citizen.css'));

        preg_match('#accordéon KPI.*?visite guidée#su', $app, $bloc);

        expect($bloc)->not->toBeEmpty()
            ->and($bloc[0])->toContain('@media (max-width: 767px)')
            ->and($bloc[0])->toContain('@media (min-width: 768px)')
            ->and($bloc[0])->not->toContain('1024px')
            ->and($citoyen)->toContain('@media (max-width: 767px) { [data-kpi-accordion] .kpi-toggle { display: flex; } }');
    });

    it('distingue la variante staff pour l’écart du tableau de bord', function (): void {
        expect(Blade::render('<x-kpi-grid variant="staff"><x-kpi variant="staff" value="1" label="Un" /></x-kpi-grid>'))
            ->toContain('data-kpi-variant="staff"')
            ->and(Blade::render('<x-kpi-grid><x-kpi value="1" label="Un" /></x-kpi-grid>'))
            ->toContain('data-kpi-variant="citizen"');
    });
});
