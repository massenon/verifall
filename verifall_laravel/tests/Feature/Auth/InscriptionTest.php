<?php

declare(strict_types=1);

use App\Core\Identity\Services\ConsentService;
use App\Core\Identity\Services\OtpService;
use App\Enums\ConsentType;
use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Livewire\Auth\Register;
use App\Mail\OtpCodeMail;
use App\Models\Consent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Inscription citoyenne — stepper 4 étapes
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
});

/** Remplit l'étape 1 puis l'étape 2, et rend le composant à l'étape 3. */
function inscriptionJusquAuCode(array $surcharges = []): Testable
{
    $donnees = array_merge([
        'prenom' => 'Adderly',
        'nom' => 'Houngbedji',
        'telephone' => '+229 97 00 00 00',
        'profession' => 'Journaliste',
        'email' => 'adderly@verifall.com',
        'motDePasse' => 'Cotonou2026!Fact',
        'motDePasseConfirmation' => 'Cotonou2026!Fact',
        'accepteCgu' => true,
        'acceptePrivacy' => true,
        'accepteNewsletter' => false,
    ], $surcharges);

    return Livewire::test(Register::class)
        ->set('prenom', $donnees['prenom'])
        ->set('nom', $donnees['nom'])
        ->set('telephone', $donnees['telephone'])
        ->set('profession', $donnees['profession'])
        ->call('versEtape2')
        ->set('email', $donnees['email'])
        ->set('motDePasse', $donnees['motDePasse'])
        ->set('motDePasseConfirmation', $donnees['motDePasseConfirmation'])
        ->set('accepteCgu', $donnees['accepteCgu'])
        ->set('acceptePrivacy', $donnees['acceptePrivacy'])
        ->set('accepteNewsletter', $donnees['accepteNewsletter'])
        ->call('versEtape3');
}

it('affiche le formulaire', function (): void {
    $this->get(route('register'))->assertOk()->assertSee('Créez votre compte');
});

it('progresse de l\'étape 1 à l\'étape 2', function (): void {
    Livewire::test(Register::class)
        ->set('prenom', 'Adderly')->set('nom', 'Houngbedji')
        ->set('telephone', '+229 97 00 00 00')->set('profession', 'Journaliste')
        ->call('versEtape2')
        ->assertSet('etape', 2);
});

it('ne persiste rien à l\'étape 1', function (): void {
    Livewire::test(Register::class)
        ->set('prenom', 'Adderly')->set('nom', 'Houngbedji')
        ->set('telephone', '+229 97 00 00 00')->set('profession', 'Journaliste')
        ->call('versEtape2');

    expect(User::count())->toBe(0);
});

it('refuse d\'avancer sans les champs d\'identité', function (): void {
    Livewire::test(Register::class)
        ->call('versEtape2')
        ->assertHasErrors(['prenom', 'nom', 'telephone', 'profession'])
        ->assertSet('etape', 1);
});

describe('consentements RGPD', function (): void {
    it('bloque la création sans acceptation des CGU', function (): void {
        // Ajout au prototype, imposé par SECURITE_RGPD §5.
        inscriptionJusquAuCode(['accepteCgu' => false])
            ->assertHasErrors('accepteCgu')
            ->assertSet('etape', 2);

        expect(User::count())->toBe(0);
    });

    it('bloque la création sans lecture de la politique de confidentialité', function (): void {
        inscriptionJusquAuCode(['acceptePrivacy' => false])
            ->assertHasErrors('acceptePrivacy');

        expect(User::count())->toBe(0);
    });

    it('laisse la newsletter facultative', function (): void {
        inscriptionJusquAuCode(['accepteNewsletter' => false])->assertSet('etape', 3);

        expect(User::count())->toBe(1);
    });

    it('trace chaque consentement avec la version du texte', function (): void {
        inscriptionJusquAuCode();

        $user = User::first();

        // Une colonne booléenne ne dirait pas à QUELLE VERSION la personne a
        // consenti après une refonte des CGU.
        foreach ([ConsentType::Cgu, ConsentType::Privacy, ConsentType::Newsletter] as $type) {
            $trace = Consent::where('user_id', $user->id)->where('type', $type->value)->first();

            expect($trace)->not->toBeNull("consentement {$type->value} absent")
                ->and($trace->document_version)->toBe($type->versionCourante());
        }
    });

    it('trace aussi le REFUS de la newsletter', function (): void {
        inscriptionJusquAuCode(['accepteNewsletter' => false]);

        $trace = Consent::where('type', ConsentType::Newsletter->value)->first();

        // Pouvoir prouver qu'une personne n'a PAS accepté protège autant.
        expect($trace)->not->toBeNull()
            ->and($trace->granted)->toBeFalse();
    });

    it('ne stocke jamais l\'IP du consentement en clair', function (): void {
        inscriptionJusquAuCode();

        expect(Consent::first()->ip_hash)->toHaveLength(64)
            ->and(Consent::first()->ip_hash)->not->toContain('127.0.0.1');
    });
});

describe('création du compte', function (): void {
    it('crée un compte citoyen INACTIF et envoie le code', function (): void {
        inscriptionJusquAuCode()->assertSet('etape', 3);

        $user = User::first();

        // Sans cette inactivité, une adresse dont on n'a pas la maîtrise
        // suffirait à créer un compte utilisable.
        expect($user->otp_verified_at)->toBeNull()
            ->and($user->hasRole(UserRole::Citoyen->value))->toBeTrue()
            ->and($user->uuid)->not->toBeNull();

        Mail::assertQueued(OtpCodeMail::class);
    });

    it('hache le mot de passe', function (): void {
        inscriptionJusquAuCode();

        expect(User::first()->password)->not->toBe('Cotonou2026!Fact')
            ->and(password_get_info(User::first()->password)['algoName'])->not->toBe('unknown');
    });

    it('refuse un mot de passe trop faible', function (string $faible): void {
        inscriptionJusquAuCode(['motDePasse' => $faible, 'motDePasseConfirmation' => $faible])
            ->assertHasErrors('motDePasse');

        expect(User::count())->toBe(0);
    })->with([
        'trop court' => ['Abc1!'],
        'sans symbole' => ['MotDePasse2026'],
        'sans majuscule' => ['cotonou2026!fact'],
    ]);

    it('refuse une adresse déjà utilisée', function (): void {
        User::factory()->create(['email' => 'adderly@verifall.com']);

        inscriptionJusquAuCode()->assertHasErrors('email');
    });

    it('normalise l\'adresse en minuscules', function (): void {
        inscriptionJusquAuCode(['email' => 'Adderly@VerifAll.com']);

        expect(User::first()->email)->toBe('adderly@verifall.com');
    });
});

describe('vérification du code', function (): void {
    it('active le compte et connecte le citoyen', function (): void {
        $composant = inscriptionJusquAuCode();
        $code = app(OtpService::class)->issue('adderly@verifall.com', OtpPurpose::Registration);

        $composant->set('code', $code)->call('verifier')->assertSet('etape', 4);

        expect(User::first()->otp_verified_at)->not->toBeNull()
            ->and(auth()->check())->toBeTrue();
    });

    it('refuse un code erroné sans activer le compte', function (): void {
        inscriptionJusquAuCode()
            ->set('code', '000000')
            ->call('verifier')
            ->assertHasErrors('code')
            ->assertSet('etape', 3);

        expect(User::first()->otp_verified_at)->toBeNull()
            ->and(auth()->check())->toBeFalse();
    });

    it('exige six chiffres', function (): void {
        inscriptionJusquAuCode()->set('code', '12ab')->call('verifier')->assertHasErrors('code');
    });
});

it('conserve le service de consentement comme source de vérité', function (): void {
    inscriptionJusquAuCode();

    $service = app(ConsentService::class);
    $user = User::first();

    expect($service->hasGranted($user, ConsentType::Cgu))->toBeTrue()
        ->and($service->hasGranted($user, ConsentType::Newsletter))->toBeFalse()
        ->and($service->aJourSurLesObligatoires($user))->toBeTrue();
});
