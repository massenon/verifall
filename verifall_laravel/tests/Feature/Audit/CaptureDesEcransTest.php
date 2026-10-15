<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Capture des écrans pour le contrôle de conformité
|--------------------------------------------------------------------------
| Ce n'est pas un test : c'est un outil. Il rend chaque écran authentifié et
| écrit le HTML sur le disque, pour qu'un comparateur puisse le confronter à la
| page du prototype dont il sort.
|
| Pourquoi ici et pas dans une commande Artisan : rendre une page en tant que
| telle personne demande une session ouverte, et c'est exactement ce que le
| client HTTP de test sait faire. Une commande devrait fabriquer cette session à
| la main — donc ouvrir un chemin d'authentification hors du cadre normal, ce
| qu'on refuse d'ajouter au code livré pour les besoins d'un audit.
|
| Ne s'exécute que sur demande :
|   AUDIT_CAPTURE=<dossier> php artisan test --filter=CaptureDesEcrans
*/

it('capture les écrans authentifiés', function (): void {
    $dossier = env('AUDIT_CAPTURE');

    if (! is_string($dossier) || $dossier === '') {
        $this->markTestSkipped('AUDIT_CAPTURE non défini — capture non demandée.');
    }

    $this->seed(DemoSeeder::class);
    File::ensureDirectoryExists($dossier);

    $comptes = [
        'citoyen' => User::role(UserRole::Citoyen->value)->first(),
        'admin' => User::role(UserRole::Admin->value)->first(),
        'mentor' => User::role(UserRole::Mentor->value)->first(),
    ];

    $journal = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $uri = $route->uri();
        $nom = $route->getName() ?? '';

        // On ne capture que les écrans sans paramètre : une page de détail
        // exige une entité précise, elle se compare à part.
        if (str_contains($uri, '{') || $nom === '' || str_starts_with($uri, 'livewire')) {
            continue;
        }

        $qui = match (true) {
            str_starts_with($nom, 'citizen.') => 'citoyen',
            str_starts_with($nom, 'staff.') => str_starts_with($nom, 'staff.mentor') ? 'mentor' : 'admin',
            default => null,
        };

        if ($qui === null) {
            continue; // Les pages publiques se capturent sans session, par HTTP.
        }

        $utilisateur = $comptes[$qui];

        if ($utilisateur === null) {
            $journal[$nom] = 'aucun compte '.$qui;

            continue;
        }

        $garde = $qui === 'citoyen' ? 'web' : 'staff';
        $reponse = $this->actingAs($utilisateur, $garde)->get('/'.ltrim($uri, '/'));

        $journal[$nom] = $reponse->getStatusCode();

        if ($reponse->getStatusCode() === 200) {
            File::put($dossier.DIRECTORY_SEPARATOR.str_replace('.', '_', $nom).'.html',
                $reponse->getContent());
        }
    }

    File::put($dossier.DIRECTORY_SEPARATOR.'_journal.json',
        json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    expect($journal)->not->toBeEmpty();
});
