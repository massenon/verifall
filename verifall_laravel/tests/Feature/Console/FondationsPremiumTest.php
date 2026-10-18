<?php

declare(strict_types=1);

use App\Support\Icones\Lucide;

/*
|--------------------------------------------------------------------------
| Fondations de la console premium (IMPLEMENTATION_PLAN, phase 1)
|--------------------------------------------------------------------------
| La feuille staff, ses polices et ses icônes. La feuille citoyenne reste
| sous la garde de BuildTailwindFigeTest : rien ici ne la touche.
*/

describe('la feuille staff-premium.css', function (): void {
    it('est construite et porte les briques du design system', function (): void {
        $chemin = public_path('assets/css/staff-premium.css');

        expect(file_exists($chemin))->toBeTrue('Lancez sh tools/staff-premium/build.sh.');

        $css = (string) file_get_contents($chemin);

        // Une brique déclarée dans tools/staff-premium/input.css et absente de
        // la feuille signale un build périmé : le relancer.
        foreach (['.carte', '.carte-tete', '.btn-primaire', '.btn-second', '.btn-icone', '.champ', '.pastille', '.p-bon', '.p-critique', '.nav-lien', '.onglet', '.tableau', '.menu-item', '.jauge', '.avatar', '.bascule'] as $brique) {
            // toContain() prend ses arguments suivants pour d'autres aiguilles :
            // pas de message ici, la brique en échec s'affiche d'elle-même.
            expect($css)->toContain($brique.'{');
        }

        expect($css)->toContain('.dark .carte')
            ->and($css)->toContain('Bricolage Grotesque');
    });

    it('a pour source une copie fidèle des jetons du prototype', function (): void {
        // Les couleurs de marque ne doivent pas diverger de la référence visuelle.
        $prototype = (string) file_get_contents(resource_path('prototype-premium/tailwind.config.js'));
        $console = (string) file_get_contents(base_path('tools/staff-premium/tailwind.config.cjs'));

        preg_match('/theme:\s*\{.*\n\};/s', $prototype, $jetonsPrototype);
        preg_match('/theme:\s*\{.*\n\};/s', $console, $jetonsConsole);

        expect($jetonsConsole[0] ?? null)->not->toBeNull()
            ->and($jetonsConsole[0] ?? '')->toBe($jetonsPrototype[0] ?? '');
    });
});

describe('les polices premium', function (): void {
    it('sont servies en local, chaque fichier déclaré existant', function (): void {
        $css = (string) file_get_contents(public_path('assets/css/fonts/premium.css'));

        preg_match_all("/url\\('([^']+)'\\)/", $css, $fichiers);

        expect($fichiers[1])->toHaveCount(4)
            ->and($css)->not->toContain('googleapis');

        foreach ($fichiers[1] as $fichier) {
            expect(file_exists(public_path('assets/css/fonts/'.$fichier)))->toBeTrue("Police absente : {$fichier}");
        }
    });
});

describe('les icônes Lucide', function (): void {
    it('rendent un SVG décoratif par défaut', function (): void {
        $svg = (string) Lucide::svg('zap', 'w-4 h-4');

        expect($svg)->toStartWith('<svg ')
            ->and($svg)->toContain('aria-hidden="true"')
            ->and($svg)->toContain('class="w-4 h-4"')
            ->and($svg)->toContain('stroke="currentColor"');
    });

    it('deviennent une image nommée quand on leur donne un titre', function (): void {
        $svg = (string) Lucide::svg('x', 'w-4 h-4', 'Fermer <le> panneau');

        expect($svg)->toContain('role="img"')
            ->and($svg)->toContain('aria-label="Fermer &lt;le&gt; panneau"')
            ->and($svg)->not->toContain('aria-hidden');
    });

    it('refusent un nom inconnu hors production', function (): void {
        Lucide::svg('icone-qui-n-existe-pas');
    })->throws(InvalidArgumentException::class);

    it('existent toutes, pour chaque nom écrit en dur dans une vue', function (): void {
        $inconnues = [];
        $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($fichiers as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all('/<x-lucide\s+name="([a-z0-9-]+)"/', (string) file_get_contents($fichier->getPathname()), $noms);

            foreach ($noms[1] as $nom) {
                if (! Lucide::existe($nom)) {
                    $inconnues[] = $fichier->getFilename().' : '.$nom;
                }
            }
        }

        expect($inconnues)->toBe([]);
    });
});
