<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\OtpCodeMail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| API v1 — identité
|--------------------------------------------------------------------------
| Ce fichier vérifie autant les REFUS que les succès. Sur une API, un refus
| mal formé est plus grave qu'un succès manquant : le client ne sait pas quoi
| en faire et affiche « une erreur est survenue ».
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** Le code n'est lisible qu'à l'envoi : en base, il est haché. */
function codeApiEnvoyeA(string $adresse): string
{
    $code = null;

    Mail::assertQueued(OtpCodeMail::class, function (OtpCodeMail $courriel) use ($adresse, &$code): bool {
        if ($courriel->hasTo($adresse)) {
            $code = $courriel->code;

            return true;
        }

        return false;
    });

    return (string) $code;
}

describe('connexion', function (): void {
    it('rend un jeton porteur et le profil', function (): void {
        $citoyen = User::factory()->actif()->create([
            'email' => 'rita@verifall.com',
            'password' => 'Un-Mot-De-Passe-2026!',
        ]);
        $citoyen->assignRole(UserRole::Citoyen->value);

        $reponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'rita@verifall.com',
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
            'appareil' => 'Pixel 7 de Rita',
        ]);

        $reponse->assertOk()
            ->assertJsonStructure(['data' => ['token', 'type', 'expire_le', 'utilisateur' => ['uuid', 'email']]])
            ->assertJsonPath('data.type', 'Bearer');

        // Le jeton est nommé : une liste d'appareils lisible se révoque en
        // connaissance de cause.
        expect($citoyen->tokens()->first()?->name)->toBe('Pixel 7 de Rita');
    });

    it('refuse un mot de passe faux sans dire lequel des deux champs est en cause', function (): void {
        User::factory()->actif()->create([
            'email' => 'rita@verifall.com',
            'password' => 'Un-Mot-De-Passe-2026!',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rita@verifall.com',
            'mot_de_passe' => 'faux',
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'non_authentifie')
            ->assertJsonPath('error.message', 'Identifiants invalides.');
    });

    it('répond exactement pareil pour un compte inexistant', function (): void {
        // ANTI-ÉNUMÉRATION : distinguer les deux cas transformerait ce point
        // d'entrée en annuaire des inscrits.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'personne@verifall.com',
            'mot_de_passe' => 'faux',
        ])->assertStatus(401)
            ->assertJsonPath('error.message', 'Identifiants invalides.');
    });

    it('refuse un compte dont le code n\'a jamais été validé', function (): void {
        User::factory()->create([
            'email' => 'rita@verifall.com',
            'password' => 'Un-Mot-De-Passe-2026!',
            'otp_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rita@verifall.com',
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
        ])->assertStatus(401)
            ->assertJsonPath('error.message', 'Identifiants invalides.');
    });

    it('verrouille après cinq échecs et annonce le délai', function (): void {
        User::factory()->actif()->create([
            'email' => 'rita@verifall.com',
            'password' => 'Un-Mot-De-Passe-2026!',
        ]);

        foreach (range(1, 5) as $ignore) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rita@verifall.com',
                'mot_de_passe' => 'faux',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rita@verifall.com',
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
        ])->assertStatus(429)
            ->assertJsonPath('error.code', 'debit_depasse')
            ->assertHeader('Retry-After');
    });

    it('refuse une requête sans champ, dans l\'enveloppe de validation', function (): void {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['email', 'mot_de_passe']]]);
    });
});

describe('inscription', function (): void {
    it('crée un compte INACTIF et envoie un code, sans rendre de jeton', function (): void {
        Mail::fake();

        $reponse = $this->postJson('/api/v1/auth/register', [
            'prenom' => 'Rita',
            'nom' => 'Dossou',
            'telephone' => '97000000',
            'profession' => 'Journaliste',
            'email' => 'rita@verifall.com',
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
            'cgu' => true,
            'confidentialite' => true,
            'newsletter' => false,
        ]);

        $reponse->assertStatus(201)
            ->assertJsonPath('data.etape', 'code_envoye')
            // Aucun jeton : le compte n'a fait la preuve d'aucune maîtrise de
            // l'adresse déclarée.
            ->assertJsonMissingPath('data.token');

        $citoyen = User::where('email', 'rita@verifall.com')->firstOrFail();

        expect($citoyen->otp_verified_at)->toBeNull()
            ->and($citoyen->hasRole(UserRole::Citoyen->value))->toBeTrue()
            // Les trois consentements sont horodatés — y compris le refus.
            ->and($citoyen->consents()->count())->toBe(3);

        Mail::assertQueued(OtpCodeMail::class);
    });

    it('exige les deux consentements obligatoires', function (): void {
        $this->postJson('/api/v1/auth/register', [
            'prenom' => 'Rita',
            'nom' => 'Dossou',
            'telephone' => '97000000',
            'profession' => 'Journaliste',
            'email' => 'rita@verifall.com',
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
            'cgu' => false,
            'confidentialite' => false,
        ])->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['cgu', 'confidentialite']]]);

        expect(User::where('email', 'rita@verifall.com')->exists())->toBeFalse();
    });

    it('applique la même politique de mot de passe que le web', function (): void {
        $this->postJson('/api/v1/auth/register', [
            'prenom' => 'Rita',
            'nom' => 'Dossou',
            'telephone' => '97000000',
            'profession' => 'Journaliste',
            'email' => 'rita@verifall.com',
            'mot_de_passe' => 'court',
            'cgu' => true,
            'confidentialite' => true,
        ])->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['mot_de_passe']]]);
    });
});

describe('activation par code', function (): void {
    it('ouvre le compte et rend le premier jeton', function (): void {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', [
            'prenom' => 'Rita',
            'nom' => 'Dossou',
            'telephone' => '97000000',
            'profession' => 'Journaliste',
            'email' => 'rita@verifall.com',
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
            'cgu' => true,
            'confidentialite' => true,
        ])->assertStatus(201);

        $code = codeApiEnvoyeA('rita@verifall.com');

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'rita@verifall.com',
            'code' => $code,
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        expect(User::where('email', 'rita@verifall.com')->firstOrFail()->otp_verified_at)->not->toBeNull();
    });

    it('refuse un code faux, et laisse le compte fermé', function (): void {
        $citoyen = User::factory()->create(['email' => 'rita@verifall.com', 'otp_verified_at' => null]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'rita@verifall.com',
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation');

        expect($citoyen->fresh()?->otp_verified_at)->toBeNull();
    });

    it('répond pareil pour une adresse inconnue', function (): void {
        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'personne@verifall.com',
            'code' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('error.details.code.0', 'Code incorrect. Vérifiez votre saisie.');
    });

    it('n\'envoie aucun code à un compte DÉJÀ activé, sans le laisser paraître', function (): void {
        Mail::fake();

        User::factory()->actif()->create(['email' => 'rita@verifall.com']);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'rita@verifall.com'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['message']]);

        Mail::assertNothingQueued();
    });
});

describe('mot de passe oublié', function (): void {
    it('répond la même chose que le compte existe ou non', function (): void {
        Mail::fake();

        $premier = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'personne@verifall.com']);

        User::factory()->actif()->create(['email' => 'rita@verifall.com']);
        $second = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'rita@verifall.com']);

        expect($premier->json('data.message'))->toBe($second->json('data.message'));
        $premier->assertOk();
        $second->assertOk();
    });

    it('change le mot de passe et RÉVOQUE tous les jetons existants', function (): void {
        Mail::fake();

        $citoyen = User::factory()->actif()->create([
            'email' => 'rita@verifall.com',
            'password' => 'Un-Mot-De-Passe-2026!',
        ]);

        $ancien = $citoyen->createToken('ancien téléphone')->plainTextToken;

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'rita@verifall.com'])->assertOk();
        $code = codeApiEnvoyeA('rita@verifall.com');

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'rita@verifall.com',
            'code' => $code,
            'mot_de_passe' => 'Un-Autre-Mot-2026!',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        /*
         | LE POINT CAPITAL. Si l'on change le mot de passe parce qu'un tiers y
         | avait accès, laisser vivre le jeton de son téléphone rendrait
         | l'opération parfaitement inutile.
         */
        $this->withToken($ancien)->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'non_authentifie');
    });

    it('refuse un code faux', function (): void {
        User::factory()->actif()->create(['email' => 'rita@verifall.com']);

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'rita@verifall.com',
            'code' => '000000',
            'mot_de_passe' => 'Un-Autre-Mot-2026!',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation');
    });
});

describe('déconnexion', function (): void {
    it('révoque le jeton présenté, et lui seul', function (): void {
        $citoyen = User::factory()->actif()->create();
        $telephone = $citoyen->createToken('téléphone');
        $tablette = $citoyen->createToken('tablette');

        $this->withToken($telephone->plainTextToken)->postJson('/api/v1/auth/logout')->assertOk();

        /*
         | Vérifié EN BASE et non par un second appel : dans un test
         | fonctionnel, le conteneur survit d'une requête à l'autre et le
         | garde d'authentification garde en mémoire l'utilisateur résolu
         | précédemment. En production, chaque requête repart d'un processus
         | neuf — l'assertion sur la base est donc la seule honnête ici.
         |
         | Le refus d'un jeton révoqué est vérifié plus bas, dans un test qui
         | n'a authentifié personne avant lui.
         */
        expect($citoyen->tokens()->pluck('name')->all())->toBe(['tablette'])
            ->and($tablette->accessToken->fresh())->not->toBeNull();
    });

    it('refuse un jeton révoqué', function (): void {
        $citoyen = User::factory()->actif()->create();
        $jeton = $citoyen->createToken('téléphone perdu');
        $enClair = $jeton->plainTextToken;

        $jeton->accessToken->delete();

        $this->withToken($enClair)->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'non_authentifie');
    });

    it('refuse sans jeton', function (): void {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'non_authentifie');
    });
});
