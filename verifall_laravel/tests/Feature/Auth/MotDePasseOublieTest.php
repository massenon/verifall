<?php

declare(strict_types=1);

use App\Core\Identity\Services\OtpService;
use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Livewire\Auth\ForgotPassword;
use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

const NOUVEAU_MDP = 'Porto-Novo2026!Sur';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();

    $this->citoyen = User::factory()->create([
        'email' => 'adderly@verifall.com',
        'password' => 'AncienMotDePasse1!',
        'otp_verified_at' => now(),
    ]);
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

it('affiche l\'écran', function (): void {
    $this->get(route('password.request'))->assertOk()->assertSee('Mot de passe oublié');
});

describe('anti-énumération', function (): void {
    it('affiche le même message que le compte existe ou non', function (string $email): void {
        Livewire::test(ForgotPassword::class)
            ->set('email', $email)
            ->call('demander')
            ->assertSet('etape', 2)
            ->assertSee('Si un compte existe');
    })->with([
        'compte existant' => ['adderly@verifall.com'],
        'compte inexistant' => ['personne@verifall.com'],
    ]);

    it('n\'émet un code que pour un compte réel, sans le laisser paraître', function (): void {
        Livewire::test(ForgotPassword::class)
            ->set('email', 'personne@verifall.com')->call('demander');

        // Aucun code, aucun courriel — mais l'écran affiche la même chose.
        expect(OtpCode::count())->toBe(0);
        Mail::assertNothingQueued();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'adderly@verifall.com')->call('demander');

        expect(OtpCode::count())->toBe(1);
        Mail::assertQueued(OtpCodeMail::class);
    });
});

describe('réinitialisation', function (): void {
    it('change le mot de passe avec un code valide', function (): void {
        $code = app(OtpService::class)->issue('adderly@verifall.com', OtpPurpose::PasswordReset);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'adderly@verifall.com')
            ->set('etape', 2)
            ->set('code', $code)
            ->set('motDePasse', NOUVEAU_MDP)
            ->set('motDePasseConfirmation', NOUVEAU_MDP)
            ->call('reinitialiser')
            ->assertRedirect(route('citizen.dashboard'));

        expect(Hash::check(NOUVEAU_MDP, $this->citoyen->fresh()->password))->toBeTrue();
    });

    it('ferme les AUTRES sessions ouvertes', function (): void {
        // Une session résiduelle appartenant à ce compte.
        DB::table('sessions')->insert([
            'id' => 'session-fantome',
            'user_id' => $this->citoyen->id,
            'ip_address' => '41.85.1.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $code = app(OtpService::class)->issue('adderly@verifall.com', OtpPurpose::PasswordReset);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'adderly@verifall.com')->set('etape', 2)->set('code', $code)
            ->set('motDePasse', NOUVEAU_MDP)->set('motDePasseConfirmation', NOUVEAU_MDP)
            ->call('reinitialiser');

        // Sans cela, changer son mot de passe parce qu'un tiers y avait accès
        // le laisserait connecté ailleurs : l'opération serait inutile.
        expect(DB::table('sessions')->where('id', 'session-fantome')->exists())->toBeFalse();
    });

    it('révoque les cookies « se souvenir de moi »', function (): void {
        $ancienJeton = $this->citoyen->getRememberToken();
        $code = app(OtpService::class)->issue('adderly@verifall.com', OtpPurpose::PasswordReset);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'adderly@verifall.com')->set('etape', 2)->set('code', $code)
            ->set('motDePasse', NOUVEAU_MDP)->set('motDePasseConfirmation', NOUVEAU_MDP)
            ->call('reinitialiser');

        expect($this->citoyen->fresh()->getRememberToken())->not->toBe($ancienJeton);
    });

    it('refuse un code erroné et laisse le mot de passe intact', function (): void {
        app(OtpService::class)->issue('adderly@verifall.com', OtpPurpose::PasswordReset);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'adderly@verifall.com')->set('etape', 2)->set('code', '000000')
            ->set('motDePasse', NOUVEAU_MDP)->set('motDePasseConfirmation', NOUVEAU_MDP)
            ->call('reinitialiser')
            ->assertHasErrors('code');

        expect(Hash::check('AncienMotDePasse1!', $this->citoyen->fresh()->password))->toBeTrue();
    });

    it('refuse un code rejoué', function (): void {
        $code = app(OtpService::class)->issue('adderly@verifall.com', OtpPurpose::PasswordReset);

        $reinitialiser = fn () => Livewire::test(ForgotPassword::class)
            ->set('email', 'adderly@verifall.com')->set('etape', 2)->set('code', $code)
            ->set('motDePasse', NOUVEAU_MDP)->set('motDePasseConfirmation', NOUVEAU_MDP)
            ->call('reinitialiser');

        $reinitialiser();
        $reinitialiser()->assertHasErrors('code');
    });

    it('n\'accepte pas un code émis pour l\'inscription', function (): void {
        // Cloisonnement des usages : un code d'activation ne doit pas servir
        // à s'emparer d'un compte par la voie du mot de passe oublié.
        $code = app(OtpService::class)->issue('adderly@verifall.com', OtpPurpose::Registration);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'adderly@verifall.com')->set('etape', 2)->set('code', $code)
            ->set('motDePasse', NOUVEAU_MDP)->set('motDePasseConfirmation', NOUVEAU_MDP)
            ->call('reinitialiser')
            ->assertHasErrors('code');
    });

    it('impose la même exigence de robustesse qu\'à l\'inscription', function (): void {
        $code = app(OtpService::class)->issue('adderly@verifall.com', OtpPurpose::PasswordReset);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'adderly@verifall.com')->set('etape', 2)->set('code', $code)
            ->set('motDePasse', 'faible')->set('motDePasseConfirmation', 'faible')
            ->call('reinitialiser')
            ->assertHasErrors('motDePasse');
    });
});
