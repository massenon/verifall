<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

/*
 | Le comportement du service worker, pas seulement son texte.
 |
 | PwaTest vérifie que les bonnes phrases figurent dans `/sw.js`. Il n'a pas vu
 | venir l'incident du tableau de bord : « Uncaught (in promise) TypeError:
 | Failed to fetch at servirUneRessource ». Les phrases étaient toutes là ; c'est
 | le CHEMIN qui était faux — le chargement de page de `wire:navigate` partait
 | dans la branche des feuilles de style, sans `catch`.
 |
 | Ce test exécute donc le fichier tel que Laravel le rend, dans Node, et rejoue
 | les scénarios : session valide, sans session, 401/403/500, hors ligne, quota
 | plein, API, purge, mise à jour. Le détail est dans le banc d'essai.
 */
it('se comporte correctement sur chaque scénario du banc d’essai', function (): void {
    if (Process::run('node --version')->failed()) {
        $this->markTestSkipped('Node est absent : le banc d’essai du service worker ne peut pas tourner.');
    }

    $fichier = tempnam(sys_get_temp_dir(), 'sw-').'.js';
    file_put_contents($fichier, (string) $this->get('/sw.js')->getContent());

    try {
        $resultat = Process::run([
            'node',
            base_path('tests/Fixtures/service-worker/banc-d-essai.mjs'),
            $fichier,
            rtrim((string) config('app.url'), '/'),
        ]);
    } finally {
        @unlink($fichier);
    }

    expect($resultat->exitCode())->toBe(0, $resultat->output().$resultat->errorOutput())
        ->and($resultat->output())->toContain('Tout est vert')
        ->and($resultat->output())->not->toContain('FAIL');
});

it('traite le chargement de page de wire:navigate comme une page, pas comme une ressource', function (): void {
    $js = (string) $this->get('/sw.js')->getContent();

    expect($js)->toContain("requete.headers.has('X-Livewire-Navigate')")
        // Une page obtenue au bout d'une redirection (connexion) n'est jamais rangée.
        ->and($js)->toContain('if (reponse.redirected) return false;');
});
