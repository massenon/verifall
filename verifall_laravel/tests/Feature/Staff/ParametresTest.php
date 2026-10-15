<?php

declare(strict_types=1);

use App\Core\Application\Reglages\CatalogueDesReglages;
use App\Core\Application\Reglages\Reglages;
use App\Enums\UserRole;
use App\Livewire\Staff\Parametres;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);

    app(Reglages::class)->oublier();
});

describe('accès', function (): void {
    it('refuse un visiteur anonyme', function (): void {
        $this->get(route('staff.settings'))->assertRedirect(route('staff.login'));
    });

    it('refuse un mentor', function (): void {
        /*
         | Cet écran porte les clés de paiement, les identifiants SMTP et le
         | mode qui ferme le site public. Ce n'est pas une question de confiance
         | envers les mentors : le périmètre d'un rôle doit correspondre à ce
         | qu'il a besoin de faire.
         */
        $mentor = User::factory()->create();
        $mentor->assignRole(UserRole::Mentor->value);

        $this->actingAs($mentor, 'staff')->get(route('staff.settings'))->assertForbidden();
    });

    it("laisse entrer l'administration", function (): void {
        $this->actingAs($this->admin, 'staff')->get(route('staff.settings'))->assertOk();
    });
});

describe('les secrets', function (): void {
    it('sont CHIFFRÉS en base', function (): void {
        /*
         | Un mot de passe SMTP en clair dans une table, c'est un mot de passe
         | SMTP dans chaque sauvegarde, chaque export, et chaque copie de base
         | envoyée à un prestataire.
         */
        app(Reglages::class)->set('mail_password', 'MonMotDePasseSmtp2026', $this->admin);

        $ligne = Setting::where('key', 'mail_password')->first();

        expect($ligne->value)->not->toBeNull()
            ->and($ligne->value)->not->toContain('MonMotDePasseSmtp2026')
            ->and($ligne->is_secret)->toBeTrue()
            // Mais le service, lui, sait le relire : c'est ce dont le client
            // SMTP a besoin.
            ->and(app(Reglages::class)->get('mail_password'))->toBe('MonMotDePasseSmtp2026');
    });

    it('ne repartent JAMAIS vers le navigateur', function (): void {
        /*
         | LE point de cet écran. Un formulaire qui pré-remplit la clé Stripe
         | l'expédie au client à chaque affichage de page : le champ est masqué
         | à l'écran, la valeur est dans la source.
         */
        app(Reglages::class)->set('stripe_secret_key', 'sk_test_fake_key_for_tests', $this->admin);

        $composant = Livewire::actingAs($this->admin, 'staff')
            ->test(Parametres::class)
            ->call('changerDOnglet', CatalogueDesReglages::GROUPE_PAIEMENT);

        expect($composant->html())->not->toContain('sk_test_fake_key_for_tests')
            ->and($composant->get('valeurs')['stripe_secret_key'])->toBe('');
    });

    it("annoncent qu'une valeur EXISTE, sans la montrer", function (): void {
        /*
         | Sans cette mention, on croirait le champ non renseigné et on le
         | ressaisirait à chaque passage — pour finir par se tromper.
         */
        app(Reglages::class)->set('stripe_secret_key', 'sk_test_fake_key_for_tests', $this->admin);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parametres::class)
            ->call('changerDOnglet', CatalogueDesReglages::GROUPE_PAIEMENT)
            ->assertSee('Une valeur est enregistrée');
    });

    it('CONSERVENT leur valeur quand le champ est laissé vide', function (): void {
        /*
         | Conséquence directe du non-renvoi : le formulaire arrive toujours
         | vide sur ces champs. Sans cette règle, ouvrir puis enregistrer
         | l'écran effacerait toutes les clés de la plateforme.
         */
        $reglages = app(Reglages::class);
        $reglages->set('stripe_secret_key', 'sk_test_fake_key_for_tests', $this->admin);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parametres::class)
            ->call('changerDOnglet', CatalogueDesReglages::GROUPE_PAIEMENT)
            ->call('enregistrer');

        expect(app(Reglages::class)->get('stripe_secret_key'))->toBe('');
    });

    it('se remplacent quand une nouvelle valeur est saisie', function (): void {
        $reglages = app(Reglages::class);
        $reglages->set('mail_password', 'ancien', $this->admin);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parametres::class)
            ->call('changerDOnglet', CatalogueDesReglages::GROUPE_EMAIL)
            ->set('valeurs.mail_password', 'nouveau')
            ->set('valeurs.mail_from_address', 'no-reply@verifall.com')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(app(Reglages::class)->get('mail_password'))->toBe('nouveau');
    });

    it("ne finissent PAS dans le journal d'audit", function (): void {
        /*
         | Le journal consigne les CLÉS modifiées, jamais les valeurs. Y
         | recopier l'avant et l'après reviendrait à archiver en clair, dans un
         | écran consultable, exactement ce qu'on chiffre par ailleurs.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Parametres::class)
            ->call('changerDOnglet', CatalogueDesReglages::GROUPE_PAIEMENT)
            ->set('valeurs.stripe_secret_key', 'sk_live_TresSecret')
            ->call('enregistrer');

        $journal = AuditLog::where('action', 'settings.update')->first();

        expect($journal)->not->toBeNull()
            ->and(json_encode($journal->after))->not->toContain('sk_live_TresSecret')
            // La clé, elle, doit y être : c'est l'information utile.
            ->and(json_encode($journal->after))->toContain('stripe_secret_key');
    });
});

describe('la validation', function (): void {
    it('refuse une URL non chiffrée', function (): void {
        /*
         | Une URL déclarée ici finit dans le pied de page, un manifeste ou une
         | balise OpenGraph. En HTTP, elle déclasse la page qui la porte et
         | expose ceux qui la suivent.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Parametres::class)
            ->set('valeurs.site_url', 'http://verifall.com')
            ->call('enregistrer')
            ->assertHasErrors('valeurs.site_url');
    });

    it('refuse un identifiant Google Analytics mal formé', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(Parametres::class)
            ->call('changerDOnglet', CatalogueDesReglages::GROUPE_ANALYTIQUE)
            ->set('valeurs.ga4_measurement_id', 'pas-un-identifiant')
            ->call('enregistrer')
            ->assertHasErrors('valeurs.ga4_measurement_id');
    });

    it('refuse une couleur de certificat hors format', function (): void {
        /*
         | Ce test portait sur `cert_name_coords`, une position en points sur un
         | fond A4. Les trois réglages de coordonnées ont été RETIRÉS au lot 4 :
         | ils décrivaient une composition PDF qui n'existe pas — aucune
         | bibliothèque PDF n'est installée — et le certificat est une page HTML
         | qui se recompose selon la largeur. Ils étaient enregistrables et lus
         | par personne.
         |
         | La couleur du décor les remplace, et elle mérite la même sévérité pour
         | une raison plus dure : elle est interpolée dans un attribut `style`.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Parametres::class)
            ->call('changerDOnglet', CatalogueDesReglages::GROUPE_CERTIFICATS)
            ->set('valeurs.cert_accent_color', 'vert foncé')
            ->call('enregistrer')
            ->assertHasErrors('valeurs.cert_accent_color');
    });
});

describe('le catalogue', function (): void {
    it('déclare chaque clé une seule fois', function (): void {
        // Deux définitions pour la même clé feraient que la seconde écrase la
        // première en silence — avec une validation différente.
        $cles = array_map(
            static fn ($r): string => $r->cle,
            CatalogueDesReglages::tous(),
        );

        expect(count($cles))->toBe(count(array_unique($cles)));
    });

    it('range chaque réglage dans un onglet qui existe', function (): void {
        $groupes = array_keys(CatalogueDesReglages::groupes());

        foreach (CatalogueDesReglages::tous() as $reglage) {
            expect($groupes)->toContain($reglage->groupe);
        }
    });

    it('marque comme secret TOUT ce qui ouvre une porte', function (): void {
        /*
         | Le test qui empêche l'oubli. Une clé d'API déclarée sans
         | `secret: true` serait en clair dans la base, renvoyée au navigateur
         | et archivée dans le journal — les trois protections tombent
         | ensemble, sur un simple oubli.
         */
        $doiventEtreSecrets = [
            'mail_password', 'stripe_secret_key', 'stripe_webhook_secret',
            'paypal_secret', 'gemini_api_key', 'google_vision_api_key',
            'google_safe_browsing_key', 'google_factcheck_key',
            'gptzero_api_key', 'tineye_api_key',
        ];

        foreach ($doiventEtreSecrets as $cle) {
            $definition = CatalogueDesReglages::trouver($cle);

            expect($definition)->not->toBeNull("Réglage absent du catalogue : {$cle}")
                ->and($definition->secret)->toBeTrue("Réglage non marqué secret : {$cle}");
        }
    });

    it("refuse d'écrire une clé hors catalogue", function (): void {
        // Sans ce garde-fou, un champ forgé dans le navigateur écrirait
        // n'importe quoi dans la table des réglages.
        $ecrit = app(Reglages::class)->set('cle_inventee_par_un_attaquant', 'valeur', $this->admin);

        expect($ecrit)->toBeFalse()
            ->and(Setting::where('key', 'cle_inventee_par_un_attaquant')->exists())->toBeFalse();
    });
});
