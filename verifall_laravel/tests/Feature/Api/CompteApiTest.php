<?php

declare(strict_types=1);

use App\Core\Identity\Services\SuppressionCompteService;
use App\Enums\PreferenceNotification;
use App\Enums\UserRole;
use App\Mail\OtpCodeMail;
use App\Models\DataExport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| API v1 — mon compte, mes réglages, mes données
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create([
        'first_name' => 'Rita',
        'last_name' => 'Dossou',
        'email' => 'rita@verifall.com',
        'password' => 'Un-Mot-De-Passe-2026!',
        'city' => 'Cotonou',
    ]);
    $this->citoyen->assignRole(UserRole::Citoyen->value);

    $this->jeton = $this->citoyen->createToken('téléphone')->plainTextToken;
});

describe('mon profil', function (): void {
    it('rend mon compte, et rien de plus que ce que l\'écran affiche', function (): void {
        $reponse = $this->withToken($this->jeton)->getJson('/api/v1/me');

        $reponse->assertOk()
            ->assertJsonPath('data.email', 'rita@verifall.com')
            ->assertJsonPath('data.prenom', 'Rita');

        /*
         | CE QUI NE DOIT JAMAIS SORTIR. Le hachage du mot de passe et le
         | secret 2FA n'ont aucune raison de traverser le réseau ; la clé
         | primaire entière non plus, puisque toute URL publique se route sur
         | l'uuid.
         */
        $donnees = $reponse->json('data');

        expect($donnees)->not->toHaveKeys(['password', 'id', 'two_factor_secret', 'remember_token']);
    });

    it('refuse sans jeton', function (): void {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'non_authentifie');
    });

    it('refuse un compte jamais activé', function (): void {
        $inactif = User::factory()->create(['otp_verified_at' => null]);
        $jeton = $inactif->createToken('téléphone')->plainTextToken;

        $this->withToken($jeton)->getJson('/api/v1/me')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'compte_inactif');
    });

    it('modifie seulement les champs envoyés', function (): void {
        $this->withToken($this->jeton)->patchJson('/api/v1/me', ['ville' => 'Porto-Novo'])
            ->assertOk()
            ->assertJsonPath('data.ville', 'Porto-Novo')
            // PATCH veut dire « ne change QUE ce que j'envoie » : envoyer la
            // ville seule ne doit pas effacer le prénom.
            ->assertJsonPath('data.prenom', 'Rita');
    });

    it('n\'accepte pas de changer l\'adresse e-mail par cette porte', function (): void {
        $this->withToken($this->jeton)
            ->patchJson('/api/v1/me', ['email' => 'quelquun-dautre@verifall.com'])
            ->assertOk();

        // L'adresse sert d'identifiant de connexion ET de canal de
        // récupération : la changer sans vérifier la nouvelle boîte offrirait
        // une prise de compte à qui emprunte un téléphone déverrouillé.
        expect($this->citoyen->fresh()?->email)->toBe('rita@verifall.com');
    });
});

describe('réglages', function (): void {
    it('rend chaque canal avec son libellé et son état', function (): void {
        $this->withToken($this->jeton)->getJson('/api/v1/me/settings')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'notifications' => [PreferenceNotification::ActiviteForum->value => ['libelle', 'active']],
                'newsletter',
            ]]);
    });

    it('enregistre une valeur voulue, jamais une bascule', function (): void {
        $canal = PreferenceNotification::ActiviteForum->value;

        $this->withToken($this->jeton)->patchJson('/api/v1/me/settings', [
            'notifications' => [$canal => false],
        ])->assertOk()->assertJsonPath('data.notifications.'.$canal.'.active', false);

        // Rejouée après une coupure réseau, la MÊME requête donne le MÊME
        // résultat. Une bascule aurait rendu l'inverse.
        $this->withToken($this->jeton)->patchJson('/api/v1/me/settings', [
            'notifications' => [$canal => false],
        ])->assertOk()->assertJsonPath('data.notifications.'.$canal.'.active', false);
    });

    it('refuse un canal inconnu plutôt que de l\'ignorer en silence', function (): void {
        $this->withToken($this->jeton)->patchJson('/api/v1/me/settings', [
            'notifications' => ['canal_invente' => true],
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation');
    });

    it('trace la lettre d\'information comme un consentement', function (): void {
        $this->withToken($this->jeton)->patchJson('/api/v1/me/settings', ['newsletter' => true])
            ->assertOk()
            ->assertJsonPath('data.newsletter', true);

        // Un consentement marketing s'horodate et se versionne — il ne se
        // range pas parmi des préférences d'affichage.
        expect($this->citoyen->consents()->where('type', 'newsletter')->exists())->toBeTrue();
    });
});

describe('export RGPD', function (): void {
    it('lance une préparation en tâche de fond, sans renvoyer l\'archive', function (): void {
        $reponse = $this->withToken($this->jeton)->postJson('/api/v1/me/export');

        $reponse->assertStatus(202)->assertJsonStructure(['data' => ['uuid', 'message']]);

        expect(DataExport::where('user_id', $this->citoyen->id)->count())->toBe(1);
    });

    it('refuse une seconde demande dans le quart d\'heure', function (): void {
        $this->withToken($this->jeton)->postJson('/api/v1/me/export')->assertStatus(202);

        // Chaque archive est une copie complète de données personnelles : en
        // produire à la chaîne multiplie les objets à protéger.
        $this->withToken($this->jeton)->postJson('/api/v1/me/export')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflit');
    });
});

describe('suppression du compte', function (): void {
    it('exige le mot exact', function (): void {
        $this->withToken($this->jeton)->deleteJson('/api/v1/me', [
            'confirmation' => 'oui',
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
        ])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['confirmation']]]);

        expect($this->citoyen->fresh()?->deletion_requested_at)->toBeNull();
    });

    it('exige le mot de passe', function (): void {
        $this->withToken($this->jeton)->deleteJson('/api/v1/me', [
            'confirmation' => SuppressionCompteService::MOT_DE_CONFIRMATION,
            'mot_de_passe' => 'pas-le-bon',
        ])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['mot_de_passe']]]);

        expect($this->citoyen->fresh()?->deletion_requested_at)->toBeNull();
    });

    it('envoie un code au premier appel, puis programme au second', function (): void {
        Mail::fake();

        // Premier temps : mot exact + mot de passe. Rien n'est supprimé.
        $this->withToken($this->jeton)->deleteJson('/api/v1/me', [
            'confirmation' => SuppressionCompteService::MOT_DE_CONFIRMATION,
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
        ])->assertStatus(202)->assertJsonPath('data.etape', 'code_envoye');

        expect($this->citoyen->fresh()?->deletion_requested_at)->toBeNull();

        $code = null;
        Mail::assertQueued(OtpCodeMail::class, function (OtpCodeMail $courriel) use (&$code): bool {
            $code = $courriel->code;

            return true;
        });

        // Second temps : le code prouve que la boîte est tenue.
        $this->withToken($this->jeton)->deleteJson('/api/v1/me', [
            'confirmation' => SuppressionCompteService::MOT_DE_CONFIRMATION,
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
            'code' => $code,
        ])->assertOk()->assertJsonPath('data.etape', 'suppression_programmee');

        expect($this->citoyen->fresh()?->deletion_requested_at)->not->toBeNull()
            // Tous les jetons partent : garder ouvert un accès à un compte
            // qu'on vient de fermer entretiendrait l'ambiguïté.
            ->and($this->citoyen->tokens()->count())->toBe(0);
    });

    it('refuse un code faux', function (): void {
        $this->withToken($this->jeton)->deleteJson('/api/v1/me', [
            'confirmation' => SuppressionCompteService::MOT_DE_CONFIRMATION,
            'mot_de_passe' => 'Un-Mot-De-Passe-2026!',
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation');

        expect($this->citoyen->fresh()?->deletion_requested_at)->toBeNull();
    });
});

describe('compte en cours de suppression', function (): void {
    beforeEach(function (): void {
        $this->citoyen->forceFill(['deletion_requested_at' => now()])->save();
        $this->enSuppression = $this->citoyen->createToken('téléphone')->plainTextToken;
    });

    it('refuse toute action', function (): void {
        $this->withToken($this->enSuppression)->postJson('/api/v1/verifications', [
            'type' => 'text',
            'affirmation' => 'Une affirmation suffisamment longue pour passer.',
        ])->assertStatus(403)->assertJsonPath('error.code', 'compte_en_suppression');
    });

    it('laisse lire son propre compte, pour pouvoir annoncer l\'échéance', function (): void {
        $this->withToken($this->enSuppression)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'rita@verifall.com')
            ->assertJsonStructure(['data' => ['suppression_demandee_le']]);
    });

    it('laisse se déconnecter', function (): void {
        // Refuser de fermer un accès reviendrait à imposer de garder un jeton
        // vivant sur un téléphone dont on ne veut plus.
        $this->withToken($this->enSuppression)->postJson('/api/v1/auth/logout')->assertOk();
    });
});
