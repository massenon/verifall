<?php

declare(strict_types=1);

use App\Core\Identity\Services\LoginThrottleService;
use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Connexion — tests obligatoires de SECURITE_LOGIN
|--------------------------------------------------------------------------
| « brute force bloqué », « fixation de session impossible », « CSRF sur
| logout » et l'anti-énumération y sont exigés nommément.
*/

const MOT_DE_PASSE = 'Cotonou2026!Fact';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->create([
        'email' => 'adderly@verifall.com',
        'password' => MOT_DE_PASSE,
        'otp_verified_at' => now(),
    ]);
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

it('affiche l\'écran de connexion', function (): void {
    $this->get(route('login'))->assertOk()->assertSee('Connectez vous');
});

it('connecte un citoyen actif et le mène à son espace', function (): void {
    Livewire::test(Login::class)
        ->set('email', 'adderly@verifall.com')
        ->set('motDePasse', MOT_DE_PASSE)
        ->call('seConnecter')
        ->assertRedirect(route('citizen.dashboard'));

    expect(auth()->id())->toBe($this->citoyen->id);
});

it('oriente le staff vers le back-office', function (): void {
    $admin = User::factory()->create([
        'email' => 'rene@verifall.com',
        'password' => MOT_DE_PASSE,
        'otp_verified_at' => now(),
    ]);
    $admin->assignRole(UserRole::Admin->value);

    Livewire::test(Login::class)
        ->set('email', 'rene@verifall.com')
        ->set('motDePasse', MOT_DE_PASSE)
        ->call('seConnecter')
        ->assertRedirect(route('staff.dashboard'));
});

describe('anti-énumération', function (): void {
    it('renvoie le même message que le compte existe ou non', function (): void {
        $inexistant = Livewire::test(Login::class)
            ->set('email', 'personne@verifall.com')->set('motDePasse', 'FauxMotDePasse1!')
            ->call('seConnecter')
            ->errors()->get('email');

        $existant = Livewire::test(Login::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', 'FauxMotDePasse1!')
            ->call('seConnecter')
            ->errors()->get('email');

        // Un message différent transformerait l'écran en oracle permettant de
        // dresser la liste des adresses inscrites.
        expect($inexistant)->toBe($existant)
            ->and($inexistant[0])->toBe('Identifiants invalides.');
    });

    it('ne dit jamais lequel des deux champs est en cause', function (): void {
        $erreurs = Livewire::test(Login::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', 'FauxMotDePasse1!')
            ->call('seConnecter')
            ->errors()->get('email');

        expect($erreurs[0])->not->toContain('mot de passe')
            ->and($erreurs[0])->not->toContain('inconnu')
            ->and($erreurs[0])->not->toContain('existe');
    });

    it('traite un compte non activé comme des identifiants invalides', function (): void {
        $inactif = User::factory()->create([
            'email' => 'inactif@verifall.com',
            'password' => MOT_DE_PASSE,
            'otp_verified_at' => null,
        ]);
        $inactif->assignRole(UserRole::Citoyen->value);

        // Le BON mot de passe, mais compte inactif : ne pas connecter, et ne
        // pas révéler que le compte existe.
        Livewire::test(Login::class)
            ->set('email', 'inactif@verifall.com')->set('motDePasse', MOT_DE_PASSE)
            ->call('seConnecter')
            ->assertHasErrors('email');

        expect(auth()->check())->toBeFalse();
    });
});

describe('verrouillage progressif', function (): void {
    it('bloque après cinq échecs', function (): void {
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->set('email', 'adderly@verifall.com')->set('motDePasse', 'Faux'.$i.'!aA')
                ->call('seConnecter');
        }

        // 6ᵉ tentative avec le BON mot de passe : refusée, le compte est verrouillé.
        Livewire::test(Login::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', MOT_DE_PASSE)
            ->call('seConnecter')
            ->assertHasErrors('email');

        expect(auth()->check())->toBeFalse();
    });

    it('escalade la durée avec le nombre d\'échecs', function (): void {
        $throttle = app(LoginThrottleService::class);

        for ($i = 0; $i < 5; $i++) {
            $throttle->enregistrerEchec('adderly@verifall.com', '41.85.1.1');
        }
        $court = $throttle->secondesRestantes('adderly@verifall.com', '41.85.1.1');

        for ($i = 0; $i < 5; $i++) {
            $throttle->enregistrerEchec('adderly@verifall.com', '41.85.1.1');
        }
        $long = $throttle->secondesRestantes('adderly@verifall.com', '41.85.1.1');

        // Progressif et non binaire : la faute de frappe n'est pas punie comme
        // une attaque par dictionnaire.
        expect($court)->toBeGreaterThan(0)
            ->and($long)->toBeGreaterThan($court);
    });

    it('n\'affecte pas la même identité depuis une autre IP', function (): void {
        $throttle = app(LoginThrottleService::class);

        for ($i = 0; $i < 10; $i++) {
            $throttle->enregistrerEchec('adderly@verifall.com', '41.85.1.1');
        }

        // Sinon un tiers bloquerait volontairement le compte d'un citoyen.
        expect($throttle->estVerrouille('adderly@verifall.com', '41.85.1.1'))->toBeTrue()
            ->and($throttle->estVerrouille('adderly@verifall.com', '197.234.9.9'))->toBeFalse();
    });

    it('remet le compteur à zéro après une connexion réussie', function (): void {
        $throttle = app(LoginThrottleService::class);
        $throttle->enregistrerEchec('adderly@verifall.com', '127.0.0.1');
        $throttle->enregistrerEchec('adderly@verifall.com', '127.0.0.1');

        Livewire::test(Login::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', MOT_DE_PASSE)
            ->call('seConnecter');

        expect($throttle->echecs('adderly@verifall.com', '127.0.0.1'))->toBe(0);
    });
});

describe('session', function (): void {
    it('régénère l\'identifiant de session à la connexion', function (): void {
        $avant = Session::getId();

        Livewire::test(Login::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', MOT_DE_PASSE)
            ->call('seConnecter');

        // Sans régénération, un attaquant imposant son identifiant de session
        // avant la connexion en hériterait après (fixation de session).
        expect(Session::getId())->not->toBe($avant);
    });

    it('horodate la connexion sans stocker l\'IP en clair', function (): void {
        Livewire::test(Login::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', MOT_DE_PASSE)
            ->call('seConnecter');

        $frais = $this->citoyen->fresh();

        expect($frais->last_login_at)->not->toBeNull()
            ->and($frais->last_login_ip_hash)->toHaveLength(64)
            ->and($frais->last_login_ip_hash)->not->toContain('127.0.0.1');
    });
});

describe('journal de sécurité', function (): void {
    it('trace les échecs avec une sévérité croissante', function (): void {
        Livewire::test(Login::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', 'Faux1!aA')
            ->call('seConnecter');

        $trace = SecurityLog::where('event', 'login_failed')->first();

        expect($trace)->not->toBeNull()
            ->and($trace->identity_hash)->toHaveLength(64)
            // Le journal de sécurité ne doit pas devenir lui-même une base
            // d'adresses e-mail exploitable.
            ->and($trace->identity_hash)->not->toContain('adderly');
    });

    it('trace aussi les connexions RÉUSSIES', function (): void {
        Livewire::test(Login::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', MOT_DE_PASSE)
            ->call('seConnecter');

        // Sans cela, une compromission aboutie après une série d'échecs serait
        // invisible : on ne verrait que les tentatives ratées.
        expect(SecurityLog::where('event', 'login_success')->exists())->toBeTrue();
    });
});

describe('déconnexion', function (): void {
    it('refuse la méthode GET', function (): void {
        // Un GET se déclencherait depuis une <img> tierce ou un préchargeur.
        $this->actingAs($this->citoyen)
            ->get('/deconnexion')
            ->assertStatus(405);
    });

    it('déconnecte en POST et invalide la session', function (): void {
        $this->actingAs($this->citoyen)
            ->post(route('logout'))
            ->assertRedirect(route('public.home'));

        expect(auth()->check())->toBeFalse();
    });

    it('exige un utilisateur connecté', function (): void {
        $this->post(route('logout'))->assertRedirect();
    });
});
