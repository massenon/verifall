<?php

declare(strict_types=1);

use App\Core\Identity\Services\PreferencesService;
use App\Core\Identity\Services\TwoFactorService;
use App\Enums\ConsentType;
use App\Enums\PreferenceNotification;
use App\Enums\UserRole;
use App\Livewire\Citizen\Reglages;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\NewsletterSubscriber;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| Réglages — sécurité, sessions, notifications
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create([
        'email' => 'adderly@verifall.com',
        'password' => 'MotDePasseActuel2026!',
    ]);
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

/** Fabrique une ligne de session, comme le ferait un vrai appareil. */
function sessionDe(User $utilisateur, string $id, string $agent, int $ageMinutes = 0): string
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $utilisateur->id,
        'ip_address' => '41.85.0.1',
        'user_agent' => $agent,
        'payload' => '',
        'last_activity' => now()->subMinutes($ageMinutes)->timestamp,
    ]);

    return $id;
}

it('refuse la page à un visiteur anonyme', function (): void {
    $this->get(route('citizen.settings'))->assertRedirect(route('login'));
});

describe('notifications', function (): void {
    it('active tout par défaut, sans avoir rien écrit en base', function (): void {
        // L'absence de valeur vaut « réglages par défaut » : ajouter une
        // préférence ne doit pas obliger à rétro-remplir tous les comptes.
        expect($this->citoyen->notification_preferences)->toBeNull();

        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->assertSet('etape2fa', '');

        foreach (PreferenceNotification::cases() as $canal) {
            expect(app(PreferencesService::class)
                ->estActive($this->citoyen, $canal))->toBeTrue();
        }
    });

    it('bascule un canal et le persiste', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('basculer', PreferenceNotification::ActiviteForum->value);

        expect($this->citoyen->fresh()->notification_preferences['forum_activity'])->toBeFalse();
    });

    it('ignore une clé inconnue au lieu de la deviner', function (): void {
        // Une clé qui n'existe pas vient forcément du client.
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('basculer', 'tout_desactiver');

        expect($this->citoyen->fresh()->notification_preferences)->toBeNull();
    });

    it('traite la lettre d\'information comme un consentement, pas une préférence', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('basculerNewsletter');

        $consentement = Consent::where('user_id', $this->citoyen->id)
            ->where('type', ConsentType::Newsletter->value)->latest('id')->first();

        // Horodaté et rattaché à une version de document : c'est la preuve
        // exigée par le RGPD, qu'une colonne de préférences ne fournirait pas.
        expect($consentement)->not->toBeNull()
            ->and($consentement->granted)->toBeTrue()
            ->and($consentement->document_version)->not->toBeEmpty()
            ->and($this->citoyen->fresh()->notification_preferences)->toBeNull();
    });

    it('conserve l\'historique des retraits au lieu de l\'écraser', function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Reglages::class);

        $composant->call('basculerNewsletter');   // accord
        $composant->call('basculerNewsletter');   // retrait

        // Deux lignes, pas une modifiée : l'historique des accords et des
        // retraits est exactement ce qu'il faut produire en cas de contrôle.
        expect(Consent::where('user_id', $this->citoyen->id)
            ->where('type', ConsentType::Newsletter->value)->count())->toBe(2);
    });

    it("inscrit RÉELLEMENT sur la liste d'envoi, pas seulement au registre", function (): void {
        /*
         | Le consentement et l'abonnement sont deux choses distinctes : le
         | premier est la preuve RGPD que la personne a dit oui, le second est
         | la ligne qui la fait recevoir la lettre du lundi.
         |
         | Ils n'étaient pas reliés. La bascule écrivait un `Consent` et
         | affichait « activée », et la personne ne recevait jamais rien — sans
         | qu'aucune erreur ne se produise nulle part.
         */
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('basculerNewsletter');

        $abonne = NewsletterSubscriber::where('email', mb_strtolower($this->citoyen->email))->first();

        expect($abonne)->not->toBeNull()
            // Confirmé d'emblée : l'adresse est déjà vérifiée par la connexion,
            // un second courriel n'apprendrait rien et ferait échouer un
            // abonnement que la personne vient tout juste de demander.
            ->and($abonne->estDestinataire())->toBeTrue();
    });

    it("retire de la liste d'envoi quand la bascule est éteinte", function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Reglages::class);

        $composant->call('basculerNewsletter');   // accord
        $composant->call('basculerNewsletter');   // retrait

        $abonne = NewsletterSubscriber::where('email', mb_strtolower($this->citoyen->email))->first();

        // La ligne demeure — c'est elle qui porte la trace du désabonnement —
        // mais elle n'est plus destinataire.
        expect($abonne)->not->toBeNull()
            ->and($abonne->estDestinataire())->toBeFalse();
    });
});

describe('mot de passe', function (): void {
    it('exige le mot de passe actuel', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->set('motDePasseActuel', 'PasLeBon2026!')
            ->set('nouveauMotDePasse', 'UnTresBonMotDePasse2026!')
            ->set('confirmation', 'UnTresBonMotDePasse2026!')
            ->call('changerMotDePasse')
            ->assertHasErrors('motDePasseActuel');

        expect(Hash::check('MotDePasseActuel2026!', $this->citoyen->fresh()->password))->toBeTrue();
    });

    it('journalise un échec : sur une session ouverte, il ressemble à un écran trouvé déverrouillé', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->set('motDePasseActuel', 'PasLeBon2026!')
            ->set('nouveauMotDePasse', 'UnTresBonMotDePasse2026!')
            ->set('confirmation', 'UnTresBonMotDePasse2026!')
            ->call('changerMotDePasse');

        expect(SecurityLog::where('event', 'login_failed')->exists())->toBeTrue();
    });

    it('applique la règle de sécurité et non les 8 caractères du prototype', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->set('motDePasseActuel', 'MotDePasseActuel2026!')
            ->set('nouveauMotDePasse', 'Court12!')
            ->set('confirmation', 'Court12!')
            ->call('changerMotDePasse')
            ->assertHasErrors('nouveauMotDePasse');
    });

    it('refuse une confirmation qui ne correspond pas', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->set('motDePasseActuel', 'MotDePasseActuel2026!')
            ->set('nouveauMotDePasse', 'UnTresBonMotDePasse2026!')
            ->set('confirmation', 'UnAutreMotDePasse2026!')
            ->call('changerMotDePasse')
            ->assertHasErrors('nouveauMotDePasse');
    });

    it('change le mot de passe et ferme les AUTRES sessions', function (): void {
        Livewire::actingAs($this->citoyen);

        $courante = Session::getId();
        sessionDe($this->citoyen, $courante, 'Chrome/Windows');
        sessionDe($this->citoyen, 'session-telephone', 'Android');
        sessionDe($this->citoyen, 'session-bureau', 'Macintosh');

        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->set('motDePasseActuel', 'MotDePasseActuel2026!')
            ->set('nouveauMotDePasse', 'UnTresBonMotDePasse2026!')
            ->set('confirmation', 'UnTresBonMotDePasse2026!')
            ->call('changerMotDePasse')
            ->assertHasNoErrors();

        expect(Hash::check('UnTresBonMotDePasse2026!', $this->citoyen->fresh()->password))->toBeTrue()
            // Un mot de passe changé parce qu'on le croit compromis ne protège
            // de rien si les sessions ouvertes avec l'ancien survivent.
            ->and(DB::table('sessions')->where('id', 'session-telephone')->exists())->toBeFalse()
            ->and(DB::table('sessions')->where('id', 'session-bureau')->exists())->toBeFalse()
            // Déconnecter quelqu'un du geste par lequel il se protège serait absurde.
            ->and(DB::table('sessions')->where('id', $courante)->exists())->toBeTrue();
    });

    it('journalise le changement', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->set('motDePasseActuel', 'MotDePasseActuel2026!')
            ->set('nouveauMotDePasse', 'UnTresBonMotDePasse2026!')
            ->set('confirmation', 'UnTresBonMotDePasse2026!')
            ->call('changerMotDePasse');

        expect(AuditLog::where('module', 'security')
            ->where('target_label', 'Changement de mot de passe')->exists())->toBeTrue();
    });
});

describe('double authentification', function (): void {
    it('n\'active rien tant que le premier code n\'est pas fourni', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('activer2fa')
            ->assertSet('etape2fa', 'enrolement');

        // Sans cette preuve que l'application a bien enregistré le secret,
        // activer enfermerait dehors.
        expect(app(TwoFactorService::class)->estActive($this->citoyen->fresh()))->toBeFalse();
    });

    it('refuse un code d\'enrôlement erroné', function (): void {
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('activer2fa')
            ->set('code2fa', '000000')
            ->call('confirmer2fa')
            ->assertHasErrors('code2fa');

        expect(app(TwoFactorService::class)->estActive($this->citoyen->fresh()))->toBeFalse();
    });

    it('active et montre les codes de récupération une seule fois', function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Reglages::class)->call('activer2fa');

        $secret = $this->citoyen->fresh()->two_factor_secret;
        $composant->set('code2fa', (new Google2FA)->getCurrentOtp($secret))
            ->call('confirmer2fa')
            ->assertHasNoErrors()
            ->assertSet('etape2fa', 'codes');

        expect(app(TwoFactorService::class)->estActive($this->citoyen->fresh()))->toBeTrue()
            ->and($composant->get('codesRecuperation'))->toHaveCount(8);

        // Ils ne sont plus récupérables ensuite : la base n'en garde qu'une
        // empreinte, ce que l'écran annonce explicitement.
        expect(Session::get('2fa.codes.attente'))->toBeNull();
    });

    it('exige mot de passe ET code pour désactiver', function (): void {
        $deuxFacteurs = app(TwoFactorService::class);
        $deuxFacteurs->preparer($this->citoyen);
        $deuxFacteurs->confirmer($this->citoyen, (new Google2FA)->getCurrentOtp($this->citoyen->two_factor_secret));

        // Le bon code mais pas le bon mot de passe : refusé. Sinon une session
        // laissée ouverte suffirait à retirer la protection.
        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('ouvrirDesactivation2fa')
            ->set('motDePasse2fa', 'PasLeBon2026!')
            ->set('code2fa', (new Google2FA)->getCurrentOtp($this->citoyen->two_factor_secret))
            ->call('desactiver2fa')
            ->assertHasErrors('code2fa');

        expect($deuxFacteurs->estActive($this->citoyen->fresh()))->toBeTrue();
    });

    it('journalise la désactivation autant que l\'activation', function (): void {
        $deuxFacteurs = app(TwoFactorService::class);
        $deuxFacteurs->preparer($this->citoyen);
        $deuxFacteurs->confirmer($this->citoyen, (new Google2FA)->getCurrentOtp($this->citoyen->two_factor_secret));

        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('ouvrirDesactivation2fa')
            ->set('motDePasse2fa', 'MotDePasseActuel2026!')
            ->set('code2fa', (new Google2FA)->getCurrentOtp($this->citoyen->two_factor_secret))
            ->call('desactiver2fa')
            ->assertHasNoErrors();

        // Retirer une protection est au moins aussi sensible que l'ajouter.
        expect(AuditLog::where('target_label', 'Double authentification désactivée')->exists())->toBeTrue()
            ->and($deuxFacteurs->estActive($this->citoyen->fresh()))->toBeFalse();
    });
});

describe('sessions actives', function (): void {
    it('ferme une session distante', function (): void {
        sessionDe($this->citoyen, 'session-telephone', 'Android');

        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('fermerSession', 'session-telephone');

        expect(DB::table('sessions')->where('id', 'session-telephone')->exists())->toBeFalse();
    });

    it('ne ferme jamais la session de quelqu\'un d\'autre', function (): void {
        $autre = User::factory()->actif()->create();
        sessionDe($autre, 'session-de-l-autre', 'Android');

        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('fermerSession', 'session-de-l-autre');

        // Sans la clause sur le propriétaire, un identifiant deviné serait un
        // déni de service à un clic.
        expect(DB::table('sessions')->where('id', 'session-de-l-autre')->exists())->toBeTrue();
    });

    it('n\'affiche aucune adresse IP', function (): void {
        sessionDe($this->citoyen, 'session-telephone', 'Mozilla/5.0 (Linux; Android 14) Chrome/120');

        $html = Livewire::actingAs($this->citoyen)->test(Reglages::class)->html();

        // Une page de réglages ne doit pas devenir un annuaire d'adresses à la
        // portée de qui emprunte l'écran quelques secondes.
        expect($html)->not->toContain('41.85.0.1')
            ->and($html)->toContain('Android');
    });

    it('journalise la fermeture', function (): void {
        sessionDe($this->citoyen, 'session-telephone', 'Android');

        Livewire::actingAs($this->citoyen)->test(Reglages::class)
            ->call('fermerSession', 'session-telephone');

        expect(AuditLog::where('module', 'session')->exists())->toBeTrue();
    });
});
