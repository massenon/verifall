<?php

declare(strict_types=1);

use App\Console\Commands\AncienneBase\LitLAncienneBase;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\AnciensComptesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

/*
| `AnciensComptesSeeder` : les comptes de l'ancienne plateforme, identifiants
| INTACTS. Il délègue à `verifall:importer-ancienne-base` (dont le détail est
| testé dans `ImporterAncienneBaseTest`) ; ce qu'on tient ici, c'est la promesse
| propre au seeder — chaque empreinte relue et comparée au dump.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['verifall.ancienne_base.dump' => base_path('tests/Fixtures/ancienne-base.sql')]);
});

/** Les comptes du jeu d'essai, lus par le même lecteur que la reprise. */
function comptesDuDump(): array
{
    $lecteur = new class
    {
        use LitLAncienneBase;

        public function lire(string $chemin): array
        {
            return $this->lireTable($chemin, 'users');
        }
    };

    return $lecteur->lire(base_path('tests/Fixtures/ancienne-base.sql'));
}

it('reprend chaque compte avec son adresse et son empreinte à l’octet près', function (): void {
    $this->seed(AnciensComptesSeeder::class);

    $anciens = array_filter(comptesDuDump(), fn (array $c): bool => trim((string) $c['email']) !== '');

    expect($anciens)->not->toBeEmpty();

    foreach ($anciens as $ancien) {
        $empreinte = User::withTrashed()->where('email', mb_strtolower(trim((string) $ancien['email'])))->value('password');

        expect($empreinte)->toBe($ancien['password']);
    }
});

it('laisse l’ancien mot de passe ouvrir la session', function (): void {
    $this->seed(AnciensComptesSeeder::class);

    // Mot de passe en clair connu du jeu d'essai (voir ImporterAncienneBaseTest).
    $compte = User::query()->where('email', 'aicha@ancienne.test')->firstOrFail();

    expect(Hash::check('motdepasse', (string) $compte->password))->toBeTrue()
        ->and($compte->otp_verified_at)->not->toBeNull();
});

it('rejoué, ne duplique ni compte ni vérification', function (): void {
    $this->seed(AnciensComptesSeeder::class);
    $comptes = User::count();
    $verifications = Verification::count();

    $this->seed(AnciensComptesSeeder::class);

    expect(User::count())->toBe($comptes)
        ->and(Verification::count())->toBe($verifications);
});

it('garde le mot de passe d’un compte déjà inscrit, et le signale sans échouer', function (): void {
    $existant = User::factory()->create([
        'email' => 'aicha@ancienne.test',
        'password' => Hash::make('le-mot-de-passe-actuel'),
    ]);
    $empreinteActuelle = $existant->password;

    $this->artisan('db:seed', ['--class' => AnciensComptesSeeder::class])
        ->expectsOutputToContain('gardent leur mot de passe de la NOUVELLE plateforme')
        ->assertSuccessful();

    expect($existant->fresh()->password)->toBe($empreinteActuelle);
});

it('refuse de tourner sans dump, au lieu de ne rien faire en silence', function (): void {
    config(['verifall.ancienne_base.dump' => base_path('tests/Fixtures/inexistant.sql')]);

    $this->seed(AnciensComptesSeeder::class);
})->throws(RuntimeException::class, 'ANCIENNE_BASE_DUMP');

it('n’imprime jamais une adresse ni une empreinte', function (): void {
    $this->artisan('db:seed', ['--class' => AnciensComptesSeeder::class])
        ->doesntExpectOutputToContain('aicha@ancienne.test')
        ->doesntExpectOutputToContain('$2y$')
        ->assertSuccessful();
});
