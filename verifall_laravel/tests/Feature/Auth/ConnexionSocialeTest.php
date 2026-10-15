<?php

declare(strict_types=1);

use App\Core\Identity\Exceptions\ConnexionSocialeRefusee;
use App\Core\Identity\Services\ConnexionSociale;
use App\Enums\ConsentType;
use App\Enums\FournisseurSocial;
use App\Enums\UserRole;
use App\Models\Consent;
use App\Models\SocialAccount;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Two\User as ProfilSocialite;

/*
|--------------------------------------------------------------------------
| Connexion sociale
|--------------------------------------------------------------------------
| La faille classique de la connexion sociale est silencieuse : rattacher un
| compte existant sur la seule foi d'une adresse revient à donner ce compte à
| quiconque parvient à faire écrire cette adresse par le fournisseur. Ces tests
| existent d'abord pour ça.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Sans identifiants, le fournisseur est réputé indisponible : les tests qui
    // portent sur la disponibilité les posent eux-mêmes.
    config()->set('services.google.client_id', 'test-id');
    config()->set('services.google.client_secret', 'test-secret');
});

/** Un profil tel que Socialite le rend. */
function profilSocial(
    string $id = '11223344',
    string $adresse = 'aicha@example.com',
    string $nom = 'Aïcha Kora',
    bool $adresseVerifiee = true,
): ProfilSocialite {
    $profil = new ProfilSocialite;
    $profil->id = $id;
    $profil->name = $nom;
    $profil->email = $adresse;
    $profil->avatar = 'https://example.com/a.jpg';
    $profil->user = ['email_verified' => $adresseVerifiee];

    return $profil;
}

describe('création de compte', function (): void {
    it('crée un compte actif sans redemander de code', function (): void {
        $utilisateur = app(ConnexionSociale::class)
            ->connecter(FournisseurSocial::Google, profilSocial());

        // L'adresse a été vérifiée par le fournisseur : redemander un code par
        // courriel ferait refaire à la personne ce qu'elle vient de faire.
        expect($utilisateur->email)->toBe('aicha@example.com')
            ->and($utilisateur->estActive())->toBeTrue()
            ->and($utilisateur->email_verified_at)->not->toBeNull()
            ->and($utilisateur->hasRole(UserRole::Citoyen->value))->toBeTrue();
    });

    it('enregistre les consentements, newsletter refusée par défaut', function (): void {
        $utilisateur = app(ConnexionSociale::class)
            ->connecter(FournisseurSocial::Google, profilSocial());

        // Un compte créé par Google sans trace de consentement serait un compte
        // non conforme. Et une newsletter cochée d'office n'est pas un
        // consentement libre, donc pas un consentement.
        $consentements = Consent::where('user_id', $utilisateur->id)->pluck('granted', 'type');

        expect($consentements[ConsentType::Cgu->value])->toBeTrue()
            ->and($consentements[ConsentType::Privacy->value])->toBeTrue()
            ->and($consentements[ConsentType::Newsletter->value])->toBeFalse();
    });

    it('ne laisse jamais le mot de passe vide', function (): void {
        $utilisateur = app(ConnexionSociale::class)
            ->connecter(FournisseurSocial::Google, profilSocial());

        // Un champ vide autoriserait une connexion sans mot de passe si une
        // vérification venait à être oubliée ailleurs.
        expect($utilisateur->password)->not->toBeEmpty()
            ->and(Hash::check('', $utilisateur->password))->toBeFalse();
    });

    it('se rabat sur l\'adresse quand le fournisseur ne donne pas de nom', function (): void {
        $utilisateur = app(ConnexionSociale::class)
            ->connecter(FournisseurSocial::Google, profilSocial(nom: ''));

        expect($utilisateur->name)->toBe('aicha');
    });
});

describe('la faille à ne pas ouvrir', function (): void {
    it('refuse de rattacher un compte existant si l\'adresse n\'est pas attestée', function (): void {
        $existant = User::factory()->actif()->create(['email' => 'rene@example.com']);
        $existant->assignRole(UserRole::Citoyen->value);

        // Le fournisseur ne dit pas avoir vérifié l'adresse : le rattacher
        // donnerait le compte à qui aurait déclaré cette adresse chez lui.
        expect(fn () => app(ConnexionSociale::class)->connecter(
            FournisseurSocial::Google,
            profilSocial(adresse: 'rene@example.com', adresseVerifiee: false),
        ))->toThrow(ConnexionSocialeRefusee::class);

        expect(SocialAccount::count())->toBe(0);
    });

    it('refuse toujours via un fournisseur qui n\'atteste rien', function (): void {
        config()->set('services.facebook.client_id', 'test-id');
        config()->set('services.facebook.client_secret', 'test-secret');

        $existant = User::factory()->actif()->create(['email' => 'rene@example.com']);
        $existant->assignRole(UserRole::Citoyen->value);

        // Facebook ne fournit aucune garantie équivalente : même en prétendant
        // l'adresse vérifiée, on ne rattache pas.
        expect(fn () => app(ConnexionSociale::class)->connecter(
            FournisseurSocial::Facebook,
            profilSocial(adresse: 'rene@example.com', adresseVerifiee: true),
        ))->toThrow(ConnexionSocialeRefusee::class);
    });

    it('rattache quand le fournisseur atteste l\'adresse', function (): void {
        $existant = User::factory()->actif()->create(['email' => 'rene@example.com']);
        $existant->assignRole(UserRole::Citoyen->value);

        $utilisateur = app(ConnexionSociale::class)->connecter(
            FournisseurSocial::Google,
            profilSocial(adresse: 'rene@example.com', adresseVerifiee: true),
        );

        expect($utilisateur->id)->toBe($existant->id)
            ->and(SocialAccount::first()->email_verified_by_provider)->toBeTrue()
            // Aucun compte en double.
            ->and(User::where('email', 'rene@example.com')->count())->toBe(1);
    });

    it('reconnaît la personne par son identifiant, pas par son adresse', function (): void {
        $service = app(ConnexionSociale::class);

        $utilisateur = $service->connecter(FournisseurSocial::Google, profilSocial());

        // La personne change d'adresse chez Google : c'est toujours elle.
        $revenu = $service->connecter(
            FournisseurSocial::Google,
            profilSocial(adresse: 'aicha.kora@example.com'),
        );

        expect($revenu->id)->toBe($utilisateur->id)
            ->and(User::count())->toBe(1)
            ->and(SocialAccount::count())->toBe(1);
    });

    it('refuse un compte en cours de suppression', function (): void {
        $service = app(ConnexionSociale::class);
        $utilisateur = $service->connecter(FournisseurSocial::Google, profilSocial());

        $utilisateur->forceFill(['deletion_requested_at' => now()])->save();

        expect(fn () => $service->connecter(FournisseurSocial::Google, profilSocial()))
            ->toThrow(ConnexionSocialeRefusee::class);
    });

    it('refuse quand le fournisseur ne transmet aucune adresse', function (): void {
        expect(fn () => app(ConnexionSociale::class)->connecter(
            FournisseurSocial::Google,
            profilSocial(adresse: ''),
        ))->toThrow(ConnexionSocialeRefusee::class);
    });

    it('ne laisse pas deux comptes revendiquer la même identité sociale', function (): void {
        $service = app(ConnexionSociale::class);
        $premier = $service->connecter(FournisseurSocial::Google, profilSocial());

        $autre = User::factory()->actif()->create(['email' => 'autre@example.com']);
        $autre->assignRole(UserRole::Citoyen->value);

        expect(fn () => $service->relier($autre, FournisseurSocial::Google, profilSocial()))
            ->toThrow(ConnexionSocialeRefusee::class);

        expect(SocialAccount::first()->user_id)->toBe($premier->id);
    });
});

describe('les routes', function (): void {
    it('répond 404 sur un fournisseur inconnu', function (): void {
        // Sans énumération, un `{fournisseur}` forgé irait instancier n'importe
        // quel pilote de Socialite.
        $this->get('/auth/monsite/redirection')->assertNotFound();
    });

    it('répond 404 sur un fournisseur connu mais non configuré', function (): void {
        config()->set('services.facebook.client_id', null);
        config()->set('services.facebook.client_secret', null);

        $this->get('/auth/facebook/redirection')->assertNotFound();
    });

    it('n\'est pas ouverte à quelqu\'un de déjà connecté', function (): void {
        $citoyen = User::factory()->actif()->create();
        $citoyen->assignRole(UserRole::Citoyen->value);

        // Relier un service depuis un compte connecté se fait dans les
        // réglages, pas par cette porte.
        $this->actingAs($citoyen)->get('/auth/google/redirection')->assertRedirect();
    });

    it('ramène sans alarme quand la personne refuse chez le fournisseur', function (): void {
        $this->get('/auth/google/retour?error=access_denied')
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();
    });
});

describe('les boutons', function (): void {
    it('n\'affiche rien quand aucun fournisseur n\'est configuré', function (): void {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.facebook.client_id', null);

        // Proposer une connexion qui se solde par une erreur du fournisseur est
        // pire que ne rien proposer — pas même le séparateur.
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('ou continuer avec');
    });

    it('affiche le bouton d\'un fournisseur configuré, avec la mention de consentement', function (): void {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ou continuer avec')
            ->assertSee(route('social.redirect', 'google'))
            // Le consentement doit être lu AVANT le clic, pas après.
            //
            // PAS de `escape: false` : depuis que la mention passe par
            // `{{ __() }}`, l'apostrophe est rendue `&#039;` et la recherche du
            // littéral brut ne trouvait plus rien. En laissant `assertSee`
            // échapper l'attendu, les deux côtés parlent la même langue — et
            // l'assertion redevient sensible à une disparition réelle de la
            // mention, ce qui est tout son intérêt.
            ->assertSee('conditions d\'utilisation');
    });
});
