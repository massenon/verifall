<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\ComptesDeDemonstrationSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

/*
 | Ce seeder répond à une question, et à une seule : « avec quoi je me
 | connecte pour voir tel écran ? »
 |
 | Il ne CRÉE aucun compte. Une première version en créait quatre, avec de
 | belles adresses prévisibles — et faisait tomber sept tests, à juste titre :
 | l'équipe du prototype couvre déjà chaque rôle, et la doubler rendait faux
 | tout ce qui compte les comptes.
 */

beforeEach(function (): void {
    config()->set('verifall.demo.enabled', true);
    config()->set('verifall.demo.password', 'MotDePasseDeTest2026!');
});

describe('les comptes de référence', function (): void {
    it('couvre chaque rôle, sans en créer aucun', function (): void {
        $this->seed(DemoSeeder::class);

        /*
         | Le point qui compte : un rôle sans compte de référence est un écran
         | que personne ne sait aller voir. Et exactement UN par rôle — deux
         | administrateurs ne reproduiraient plus le prototype.
         */
        foreach (ComptesDeDemonstrationSeeder::rolesCouverts() as $role) {
            expect(User::role($role->value)->count())->toBeGreaterThan(0);
        }

        expect(User::role(UserRole::Admin->value)->count())->toBe(1)
            ->and(User::role(UserRole::Mentor->value)->count())->toBe(1)
            ->and(User::role(UserRole::Moderateur->value)->count())->toBe(1);
    });

    it('rend les comptes réellement utilisables', function (): void {
        /*
         | Sans adresse vérifiée, chaque connexion réclame un code envoyé par
         | courriel — qui n'est envoyé nulle part en local. Les comptes
         | existeraient sans que personne puisse entrer avec.
         */
        $this->seed(DemoSeeder::class);

        foreach (['rene@verifall.com', 'koffi@verifall.com', 'adderly@verifall.com'] as $adresse) {
            $compte = User::where('email', $adresse)->first();

            expect($compte)->not->toBeNull()
                ->and($compte->email_verified_at)->not->toBeNull()
                ->and(Hash::check('MotDePasseDeTest2026!', $compte->password))->toBeTrue();
        }
    });

    it('ne fabrique pas un compte manquant, il le signale', function (): void {
        /*
         | Sur une base sans l'équipe du prototype, le seeder ne doit RIEN
         | créer. Fabriquer le compte absent recréerait exactement le doublon
         | qu'on vient d'éliminer.
         */
        $avant = User::count();

        (new ComptesDeDemonstrationSeeder)->run();

        expect(User::count())->toBe($avant);
    });
});

describe('les trois verrous', function (): void {
    it('refuse absolument en production', function (): void {
        /*
         | On appelle le seeder DIRECTEMENT. Découvert en écrivant ce test : en
         | production, Laravel intercale sa propre demande de confirmation avant
         | d'exécuter quoi que ce soit — si bien que passer par `$this->seed()`
         | n'atteignait jamais le garde-fou qu'on prétend vérifier, et le test
         | passait pour la mauvaise raison.
         */
        app()->detectEnvironment(static fn (): string => 'production');

        expect(fn () => (new ComptesDeDemonstrationSeeder)->run())
            ->toThrow(RuntimeException::class, 'interdits en production');
    });

    it("refuse sans l'interrupteur explicite", function (): void {
        config()->set('verifall.demo.enabled', false);

        expect(fn () => (new ComptesDeDemonstrationSeeder)->run())
            ->toThrow(RuntimeException::class, 'DEMO_SEED_ENABLED');
    });

    it('refuse sans mot de passe, plutôt que d’en inventer un', function (): void {
        /*
         | Le verrou le plus important. Un mot de passe de repli écrit dans le
         | dépôt serait connu de quiconque lit le code — et il survivrait
         | jusqu'en production, parce que personne ne pense à changer une valeur
         | qui « marche déjà ».
         */
        config()->set('verifall.demo.password', '');

        expect(fn () => (new ComptesDeDemonstrationSeeder)->run())
            ->toThrow(RuntimeException::class, 'DEMO_PASSWORD est vide');
    });
});

describe('ce qui ne doit pas fuir', function (): void {
    it("n'écrit aucun mot de passe en dur", function (): void {
        $source = file_get_contents(database_path('seeders/ComptesDeDemonstrationSeeder.php'));

        expect($source)->toContain('motDePasseDemo()')
            ->and($source)->not->toContain('Hash::make(\'');
    });

    it('ne pose aucune valeur de démonstration dans .env.example', function (): void {
        // `.env.example` est lu par toute nouvelle installation : une valeur
        // par défaut y deviendrait le mot de passe de tout le monde.
        $exemple = file_get_contents(base_path('.env.example'));

        expect($exemple)->toContain('DEMO_PASSWORD=')
            ->and($exemple)->not->toMatch('/DEMO_PASSWORD=\S/');
    });

    it("n'imprime jamais le mot de passe", function (): void {
        // Il finirait dans l'historique du terminal de chacun.
        $source = file_get_contents(database_path('seeders/ComptesDeDemonstrationSeeder.php'));

        /*
         | La première version de ce test interdisait la chaîne `$motDePasse)`
         | — ce qui condamnait `Hash::make($motDePasse)`, c'est-à-dire l'usage
         | LÉGITIME de la variable. Un test trop large ne protège de rien : il
         | oblige seulement à le contourner.
         |
         | Ce qu'il faut interdire, c'est de l'écrire sur une SORTIE.
         */
        expect($source)->toContain('la valeur de DEMO_PASSWORD');

        foreach (['line($motDePasse', 'info($motDePasse', 'warn($motDePasse',
            'error($motDePasse', 'comment($motDePasse'] as $fuite) {
            expect($source)->not->toContain($fuite);
        }
    });
});
