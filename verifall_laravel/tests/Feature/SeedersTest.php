<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\AuditLogSeeder;
use Database\Seeders\CitizenSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StaffSeeder;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Seeders de démonstration
|--------------------------------------------------------------------------
| Ils créent des comptes — dont un administrateur — au mot de passe connu de
| toute l'équipe. Les garde-fous comptent donc autant que les données.
*/

beforeEach(function (): void {
    config()->set('verifall.demo.enabled', true);
    config()->set('verifall.demo.password', 'MotDePasseDeDemonstration2026!');
});

describe('garde-fous', function (): void {
    it('refuse de s\'exécuter en production', function (): void {
        app()->detectEnvironment(fn () => 'production');

        // Le seeder est invoqué DIRECTEMENT, sans passer par la commande : en
        // production, `db:seed` réclame d'abord une confirmation au clavier, et
        // le test se serait arrêté sur cette invite au lieu d'atteindre le
        // garde-fou. Or c'est le garde-fou qu'on vérifie ici — la confirmation
        // console ne protège pas un appel programmatique.
        expect(fn () => app(StaffSeeder::class)->run())
            ->toThrow(RuntimeException::class, 'interdits en production');
    });

    it('refuse tant que le jeu de démonstration n\'est pas activé', function (): void {
        config()->set('verifall.demo.enabled', false);

        // Un interrupteur distinct du mot de passe : une variable oubliée dans
        // un .env recopié ne doit pas suffire à peupler une base.
        expect(fn () => $this->seed(StaffSeeder::class))
            ->toThrow(RuntimeException::class, 'désactivé');
    });

    it('refuse sans mot de passe, plutôt que d\'en inventer un', function (): void {
        config()->set('verifall.demo.password', null);

        // Une valeur de repli finirait en production : c'est exactement ce que
        // « jamais de secret en dur » existe pour empêcher.
        expect(fn () => $this->seed(StaffSeeder::class))
            ->toThrow(RuntimeException::class, 'DEMO_PASSWORD');
    });

    it('ne crée aucun compte par le seeder par défaut', function (): void {
        // `php artisan db:seed` doit rester exécutable partout, production
        // comprise : il ne pose que le RBAC.
        $this->seed();

        expect(User::count())->toBe(0)
            ->and(Role::count())->toBeGreaterThan(0);
    });
});

describe('jeu de démonstration', function (): void {
    beforeEach(function (): void {
        $this->seed(DemoSeeder::class);
    });

    it('reproduit l\'équipe et les citoyens du prototype', function (): void {
        expect(User::role(UserRole::Admin->value)->count())->toBe(1)
            ->and(User::role(UserRole::Mentor->value)->count())->toBe(1)
            ->and(User::role(UserRole::Moderateur->value)->count())->toBe(1)
            ->and(User::role(UserRole::Citoyen->value)->count())->toBe(9);
    });

    it('reprend les compteurs affichés dans les maquettes', function (): void {
        $adderly = User::where('email', 'adderly@verifall.com')->first();

        // Une démonstration dont les chiffres diffèrent de la maquette fait
        // douter de la maquette autant que du code.
        expect($adderly->units)->toBe(34)
            ->and($adderly->xp)->toBe(1280)
            ->and($adderly->streak_days)->toBe(7);
    });

    it('donne un identifiant public à chaque compte', function (): void {
        // getRouteKeyName() est `uuid` : un compte sans uuid n'est adressable
        // par aucune route, et la colonne étant nullable rien ne s'en plaint.
        expect(User::whereNull('uuid')->count())->toBe(0);
    });

    it('n\'active pas la 2FA, pour que la démonstration montre l\'enrôlement', function (): void {
        $admin = User::where('email', 'rene@verifall.com')->first();

        // La pré-activer obligerait en outre à stocker un secret TOTP.
        expect($admin->two_factor_confirmed_at)->toBeNull()
            ->and($admin->otp_verified_at)->not->toBeNull();
    });

    it('peuple les deux journaux du back-office', function (): void {
        expect(AuditLog::count())->toBeGreaterThan(0)
            ->and(SecurityLog::count())->toBeGreaterThan(0)
            // Le compteur « actions destructives » de la page d'audit doit
            // avoir de quoi compter.
            ->and(AuditLog::whereIn('action', ['delete', 'role_change'])->count())
            ->toBeGreaterThanOrEqual(2);
    });

    it('est rejouable sans dupliquer', function (): void {
        $avant = User::count();

        $this->seed(DemoSeeder::class);

        // La matrice évoluera à chaque sprint : rejouer le jeu de données doit
        // rester sans danger.
        expect(User::count())->toBe($avant);
    });

    it('conserve l\'identifiant public d\'un compte rejoué', function (): void {
        $uuid = User::where('email', 'adderly@verifall.com')->value('uuid');

        $this->seed(CitizenSeeder::class);

        // Le régénérer casserait toute URL déjà partagée.
        expect(User::where('email', 'adderly@verifall.com')->value('uuid'))->toBe($uuid);
    });
});

it('n\'écrit pas de journal sans acteur, et le dit', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Sans administrateur, les lignes seraient anonymes : le seeder s'abstient
    // au lieu de produire un journal qui ne désigne personne.
    $this->seed(AuditLogSeeder::class);

    expect(AuditLog::count())->toBe(0);
});
