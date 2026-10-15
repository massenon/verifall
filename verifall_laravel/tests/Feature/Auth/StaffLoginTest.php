<?php

declare(strict_types=1);

use App\Core\Identity\Services\TwoFactorService;
use App\Enums\UserRole;
use App\Http\Middleware\StaffIdleTimeout;
use App\Livewire\Auth\StaffLogin;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| Back-office — guard séparé et 2FA TOTP obligatoire
|--------------------------------------------------------------------------
*/

const MDP_STAFF = 'Cotonou2026!Admin';

/** Crée un compte staff avec 2FA déjà confirmée, et renvoie [user, secret]. */
function staffAvec2fa(UserRole $role = UserRole::Admin): array
{
    $user = User::factory()->create([
        'email' => 'rene@verifall.com',
        'password' => MDP_STAFF,
        'otp_verified_at' => now(),
    ]);
    $user->assignRole($role->value);

    $service = app(TwoFactorService::class);
    ['secret' => $secret] = $service->preparer($user);
    $service->confirmer($user, app(Google2FA::class)->getCurrentOtp($secret));

    return [$user->fresh(), $secret];
}

function codeCourant(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    /*
     | Le second facteur s'exige desormais PAR ROLE, et la configuration livree
     | en dispense l'administrateur. Ce fichier decrit le comportement quand il
     | EST exige : il le declare donc explicitement, au lieu de dependre d'un
     | reglage d'exploitation qui peut changer sans que la 2FA soit en cause.
     |
     | La dispense elle-meme a son propre fichier :
     | tests/Feature/Auth/DeuxFacteursParRoleTest.php
     */
    config()->set('verifall.securite.deux_facteurs.roles', [
        UserRole::Admin->value,
        UserRole::Mentor->value,
        UserRole::Moderateur->value,
    ]);
});

it('affiche l\'écran d\'administration', function (): void {
    $this->get(route('staff.login'))->assertOk()->assertSee('Administration');
});

it('n\'est pas indexable', function (): void {
    $this->get(route('staff.login'))->assertSee('noindex', escape: false);
});

describe('étape 1 — identifiants', function (): void {
    it('demande le code après des identifiants valides, SANS connecter', function (): void {
        [$staff, $secret] = staffAvec2fa();

        Livewire::test(StaffLogin::class)
            ->set('email', 'rene@verifall.com')->set('motDePasse', MDP_STAFF)
            ->call('verifierIdentifiants')
            ->assertSet('attenteCode', true);

        // Point capital : sans cela, la 2FA se contournerait en fermant
        // simplement l'onglet après l'étape 1.
        expect(auth()->guard('staff')->check())->toBeFalse();
    });

    it('refuse un citoyen même avec les bons identifiants', function (): void {
        $citoyen = User::factory()->create([
            'email' => 'adderly@verifall.com',
            'password' => MDP_STAFF,
            'otp_verified_at' => now(),
        ]);
        $citoyen->assignRole(UserRole::Citoyen->value);

        // Le back-office se refuse par RÔLE, pas seulement par mot de passe.
        Livewire::test(StaffLogin::class)
            ->set('email', 'adderly@verifall.com')->set('motDePasse', MDP_STAFF)
            ->call('verifierIdentifiants')
            ->assertHasErrors('email')
            ->assertSet('attenteCode', false);
    });

    it('renvoie le même message quel que soit le motif du refus', function (): void {
        staffAvec2fa();

        $mauvaisMdp = Livewire::test(StaffLogin::class)
            ->set('email', 'rene@verifall.com')->set('motDePasse', 'Faux1!aAbbbb')
            ->call('verifierIdentifiants')->errors()->get('email');

        $inexistant = Livewire::test(StaffLogin::class)
            ->set('email', 'fantome@verifall.com')->set('motDePasse', 'Faux1!aAbbbb')
            ->call('verifierIdentifiants')->errors()->get('email');

        expect($mauvaisMdp)->toBe($inexistant);
    });

    it('efface le mot de passe de la mémoire du composant', function (): void {
        staffAvec2fa();

        Livewire::test(StaffLogin::class)
            ->set('email', 'rene@verifall.com')->set('motDePasse', MDP_STAFF)
            ->call('verifierIdentifiants')
            ->assertSet('motDePasse', '');
    });
});

describe('étape 2 — code TOTP', function (): void {
    it('connecte sur le guard staff avec un code valide', function (): void {
        [$staff, $secret] = staffAvec2fa();

        Livewire::test(StaffLogin::class)
            ->set('email', 'rene@verifall.com')->set('motDePasse', MDP_STAFF)
            ->call('verifierIdentifiants')
            ->set('code', codeCourant($secret))
            ->call('verifierCode')
            ->assertRedirect(route('staff.dashboard'));

        expect(auth()->guard('staff')->id())->toBe($staff->id)
            // Guards séparés : l'espace citoyen reste fermé.
            ->and(auth()->guard('web')->check())->toBeFalse();
    });

    it('refuse un code erroné', function (): void {
        [$staff, $secret] = staffAvec2fa();

        Livewire::test(StaffLogin::class)
            ->set('email', 'rene@verifall.com')->set('motDePasse', MDP_STAFF)
            ->call('verifierIdentifiants')
            ->set('code', '000000')
            ->call('verifierCode')
            ->assertHasErrors('code');

        expect(auth()->guard('staff')->check())->toBeFalse();
    });

    it('accepte un code de récupération, une seule fois', function (): void {
        $user = User::factory()->create([
            'email' => 'koffi@verifall.com', 'password' => MDP_STAFF, 'otp_verified_at' => now(),
        ]);
        $user->assignRole(UserRole::Mentor->value);

        $service = app(TwoFactorService::class);
        ['secret' => $secret, 'codes' => $codes] = $service->preparer($user);
        $service->confirmer($user, app(Google2FA::class)->getCurrentOtp($secret));

        $connexion = fn (string $code) => Livewire::test(StaffLogin::class)
            ->set('email', 'koffi@verifall.com')->set('motDePasse', MDP_STAFF)
            ->call('verifierIdentifiants')
            ->set('code', $code)
            ->call('verifierCode');

        $connexion($codes[0])->assertRedirect(route('staff.dashboard'));

        auth()->guard('staff')->logout();

        // Un code intercepté ne doit pas rester valable indéfiniment.
        $connexion($codes[0])->assertHasErrors('code');
    });

    it('expire l\'intention de connexion au bout de cinq minutes', function (): void {
        [$staff, $secret] = staffAvec2fa();

        $composant = Livewire::test(StaffLogin::class)
            ->set('email', 'rene@verifall.com')->set('motDePasse', MDP_STAFF)
            ->call('verifierIdentifiants');

        $this->travel(6)->minutes();

        $composant->set('code', codeCourant($secret))
            ->call('verifierCode')
            ->assertHasErrors('code');

        expect(auth()->guard('staff')->check())->toBeFalse();
    });

    it('ne connecte pas si l\'intention n\'a jamais été posée', function (): void {
        [$staff, $secret] = staffAvec2fa();

        // On saute l'étape 1 : un client modifiant la charge utile Livewire ne
        // doit pas pouvoir se connecter avec un simple code TOTP.
        Livewire::test(StaffLogin::class)
            ->set('attenteCode', true)
            ->set('email', 'rene@verifall.com')
            ->set('code', codeCourant($secret))
            ->call('verifierCode')
            ->assertHasErrors('code');

        expect(auth()->guard('staff')->check())->toBeFalse();
    });
});

describe('journalisation', function (): void {
    it('inscrit la connexion au journal d\'audit', function (): void {
        [$staff, $secret] = staffAvec2fa();

        Livewire::test(StaffLogin::class)
            ->set('email', 'rene@verifall.com')->set('motDePasse', MDP_STAFF)
            ->call('verifierIdentifiants')
            ->set('code', codeCourant($secret))->call('verifierCode');

        $trace = AuditLog::where('action', 'login')->first();

        expect($trace)->not->toBeNull()
            ->and($trace->actor_id)->toBe($staff->id)
            // Dénormalisé pour rester lisible après anonymisation RGPD.
            ->and($trace->actor_label)->not->toBeNull()
            ->and($trace->ip_hash)->toHaveLength(64);
    });
});

describe('protection du back-office', function (): void {
    it('refuse l\'accès sans connexion staff', function (): void {
        $this->get(route('staff.dashboard'))->assertRedirect();
    });

    it('refuse une session CITOYENNE, même pour un administrateur', function (): void {
        [$staff] = staffAvec2fa();

        // Connecté côté citoyen uniquement : le back-office reste fermé.
        $this->actingAs($staff, 'web')
            ->get(route('staff.dashboard'))
            ->assertRedirect();
    });

    it('autorise une session staff', function (): void {
        [$staff] = staffAvec2fa();

        $this->actingAs($staff, 'staff')
            ->withSession(['staff.last_activity' => now()->timestamp])
            ->get(route('staff.dashboard'))
            ->assertOk();
    });
});

describe('expiration d\'inactivité', function (): void {
    it('déconnecte après trente minutes sans action', function (): void {
        [$staff] = staffAvec2fa();

        $perime = now()->subMinutes(StaffIdleTimeout::MINUTES + 1)->timestamp;

        $this->actingAs($staff, 'staff')
            ->withSession(['staff.last_activity' => $perime])
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.login'))
            ->assertSessionHas('inactivite');
    });

    it('repousse l\'échéance à chaque action', function (): void {
        [$staff] = staffAvec2fa();

        $ancien = now()->subMinutes(5)->timestamp;

        $this->actingAs($staff, 'staff')
            ->withSession(['staff.last_activity' => $ancien])
            ->get(route('staff.dashboard'))
            ->assertOk();

        expect(Session::get('staff.last_activity'))->toBeGreaterThan($ancien);
    });
});

describe('service 2FA', function (): void {
    it('chiffre le secret au repos', function (): void {
        [$staff] = staffAvec2fa();

        $brut = DB::table('users')
            ->where('id', $staff->id)->value('two_factor_secret');

        // Une fuite de la base ne doit pas permettre de générer les codes.
        expect($brut)->not->toBe($staff->two_factor_secret)
            ->and($brut)->not->toBeNull();
    });

    it('hache les codes de récupération', function (): void {
        $user = User::factory()->create();
        ['codes' => $codes] = app(TwoFactorService::class)->preparer($user);

        // Ils ouvrent le compte à eux seuls : les stocker en clair reviendrait
        // à ranger le double des clés sous le paillasson.
        foreach ($user->fresh()->two_factor_recovery_codes as $stocke) {
            expect($codes)->not->toContain($stocke);
        }
    });

    it('n\'active la 2FA qu\'après confirmation d\'un premier code', function (): void {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);

        ['secret' => $secret] = $service->preparer($user);

        // Sans cette confirmation, une erreur de scan enfermerait dehors.
        expect($service->estActive($user->fresh()))->toBeFalse();

        $service->confirmer($user, app(Google2FA::class)->getCurrentOtp($secret));

        expect($service->estActive($user->fresh()))->toBeTrue();
    });

    it('exige mot de passe ET code pour désactiver', function (): void {
        [$staff, $secret] = staffAvec2fa();
        $service = app(TwoFactorService::class);

        // Sans le second facteur, un attaquant ayant le mot de passe retirerait
        // simplement la protection qu'il vient de rencontrer.
        expect($service->desactiver($staff, 'MauvaisMdp1!', codeCourant($secret)))->toBeFalse()
            ->and($service->desactiver($staff, MDP_STAFF, '000000'))->toBeFalse()
            ->and($service->desactiver($staff, MDP_STAFF, codeCourant($secret)))->toBeTrue();
    });
});
