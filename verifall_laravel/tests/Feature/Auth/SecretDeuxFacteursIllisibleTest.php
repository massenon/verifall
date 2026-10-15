<?php

declare(strict_types=1);

use App\Core\Identity\Services\TwoFactorService;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| UN SECRET 2FA DEVENU ILLISIBLE
|--------------------------------------------------------------------------
| Le cas se produit après une rotation d'`APP_KEY` : les secrets en base ont
| été chiffrés avec l'ancienne clé, et leur déchiffrement lève
| « The MAC is invalid ».
|
| Sans filet, cette exception remontait jusqu'à l'écran de connexion du
| back-office : TOUT LE PERSONNEL enfermé dehors sur une erreur 500, et aucun
| moyen d'en sortir par l'interface — l'écran qui permettrait de se réenrôler
| étant précisément celui qui plante.
|
| Trouvé sur la base de développement, où l'administrateur de démonstration
| était dans cet état exact.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);

    /*
     | On écrit un secret chiffré avec une AUTRE clé — ici, une chaîne qui n'est
     | pas un chiffré valide du tout. L'écriture passe par le constructeur de
     | requêtes : par le modèle, le cast rechiffrerait avec la bonne clé et le
     | test ne reproduirait rien.
     */
    DB::table('users')->where('id', $this->admin->id)->update([
        'two_factor_secret' => 'eyJpdiI6IkgrSDYrNmRIa01aMnVnUkpuY3MyRVE9PSIsInZhbHVlIjoiWVp5TER1eWczV21lalFoS1k4azBCMXVSdGVzMTVDWEk5TzFOWkMwUU5qOD0iLCJtYWMiOiJmNzA3NjNjMDZmNTU2ZGZmM2Q1YTZhYzUzNTZmOTE2NGY5MzZiOWZkNmNlYWEwNDEyZGNiM2FhMjgzNWVkM2Q2IiwidGFnIjoiIn0=',
        'two_factor_confirmed_at' => now(),
    ]);

    $this->admin = $this->admin->fresh();
});

it('ne compte PAS comme une double authentification active', function (): void {
    /*
     | Le cœur de la correction. « Confirmée » ne suffit pas : un secret que
     | l'application ne sait plus lire ne protège plus personne — il enferme
     | seulement son propriétaire dehors.
     |
     | En répondant faux, on renvoie la personne vers l'enrôlement, c'est-à-dire
     | vers le seul chemin qui la remette en état. Le mot de passe a déjà été
     | vérifié à ce stade : reconstituer un facteur qui n'en était plus un ne
     | coûte aucune sécurité.
     */
    expect(app(TwoFactorService::class)->estActive($this->admin))->toBeFalse();
});

it('refuse un code sans LEVER', function (): void {
    // La différence entre « code refusé » et « page 500 ». La seconde ne laisse
    // aucune issue ; la première renvoie vers l'enrôlement.
    $service = app(TwoFactorService::class);

    expect($service->verifierCode($this->admin, '123456'))->toBeFalse()
        ->and($service->verifier($this->admin, '123456'))->toBeFalse();
});

it('laisse purger le facteur cassé, puis en enrôler un neuf', function (): void {
    /*
     | La purge passe par le constructeur de requêtes, et c'est le point
     | délicat : `save()` compare l'état courant à l'état d'origine pour savoir
     | ce qui a changé, et cette comparaison déchiffre les anciennes valeurs.
     | On ne peut donc PAS effacer ces colonnes par le modèle qui bute dessus.
     */
    $service = app(TwoFactorService::class);

    $service->reinitialiser($this->admin);

    // Et l'enrôlement qui suit fonctionne — c'est tout l'objet de la purge.
    $preparation = $service->preparer($this->admin);

    expect($preparation['secret'])->not->toBeEmpty()
        ->and($preparation['codes'])->not->toBeEmpty()
        ->and($service->verifierCode(
            $this->admin->fresh(),
            app(Google2FA::class)->getCurrentOtp($preparation['secret']),
        ))->toBeTrue();
});

it("n'enferme pas le personnel dehors sur l'écran de connexion", function (): void {
    /*
     | Le test de bout en bout : la page qui plantait doit se charger.
     */
    $this->get(route('staff.login'))->assertOk();
});

describe('le réenrôlement, qui est la SORTIE de secours', function (): void {
    /*
     | LE GARDE-FOU CONDUISAIT DANS LE MUR QU'IL DEVAIT CONTOURNER.
     |
     | `estActive()` détecte bien un secret illisible et renvoie la personne
     | vers l'enrôlement — seul chemin qui remette le compte en état. Mais
     | l'enrôlement appelle `preparer()`, qui faisait `->save()` sur le modèle.
     |
     | Or `save()` appelle `isDirty()`, qui compare l'état courant à l'état
     | d'origine, et cette comparaison DÉCHIFFRE l'ancien secret pour savoir
     | s'il a changé. « The MAC is invalid » — avant même que la nouvelle
     | valeur ait eu une chance d'être écrite.
     |
     | Constaté en production : l'écran de connexion du back-office renvoyait
     | une 500, après que la clé d'application eut été régénérée sur le serveur.
     | Le journal montrait l'avertissement du filet, puis l'exception juste
     | après — le garde-fou faisait son travail, et la sortie qu'il désignait
     | était condamnée.
     */
    it('réenrôle un compte dont le secret est ILLISIBLE, sans planter', function (): void {
        $service = app(TwoFactorService::class);

        expect($service->estActive($this->admin))->toBeFalse(
            'Le filet doit voir que ce secret ne vaut plus rien.');

        $prepare = $service->preparer($this->admin);

        expect($prepare['secret'])->toBeString()->not->toBeEmpty()
            ->and($prepare['codes'])->not->toBeEmpty();

        // Et le nouveau secret est lisible : le compte est réellement réparé,
        // pas seulement débloqué.
        $this->admin->refresh();

        expect($this->admin->two_factor_secret)->toBe($prepare['secret'])
            ->and($this->admin->two_factor_confirmed_at)->toBeNull();
    });

    it('confirme ensuite avec un code valide', function (): void {
        // Le parcours complet de récupération, de bout en bout : un compte
        // enfermé dehors doit pouvoir revenir, pas seulement cesser de planter.
        $service = app(TwoFactorService::class);

        $prepare = $service->preparer($this->admin);

        $code = (new Google2FA)->getCurrentOtp($prepare['secret']);

        expect($service->confirmer($this->admin->refresh(), $code))->toBeTrue()
            ->and($service->estActive($this->admin->refresh()))->toBeTrue();
    });
});
