<?php

declare(strict_types=1);

use App\Core\Application\Analytique\IndicateursLibres;
use App\Core\Application\Reglages\Reglages;
use App\Core\Marketing\Seo\DonneesGoogle;
use App\Core\Marketing\Seo\GoogleIndisponible;
use App\Enums\UserRole;
use App\Livewire\Staff\Analytique;
use App\Livewire\Staff\Parametres;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** Une vraie clé RSA jetable : la signature JWT passe par openssl, pas par un faux. */
function cleDeCompteDeService(): string
{
    $privee = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($privee, $pem);

    return json_encode(['type' => 'service_account', 'client_email' => 'lecteur@projet.iam.gserviceaccount.com', 'private_key' => $pem], JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);
    Cache::flush();
    app(Reglages::class)->oublier();
});

describe('paramètres › SEO', function (): void {
    it('enregistre la clé, la propriété et le site, sans jamais renvoyer la clé', function (): void {
        $cle = cleDeCompteDeService();

        Livewire::actingAs($this->admin, 'staff')->test(Parametres::class)
            ->call('changerDOnglet', 'seo')
            ->set('valeurs.google_service_account_json', $cle)
            ->set('valeurs.ga4_property_id', '123456789')
            ->set('valeurs.gsc_site_url', 'sc-domain:verifall.bj')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('valeurs.google_service_account_json', '')
            ->assertDontSee('BEGIN PRIVATE KEY', false);

        expect(app(Reglages::class)->get('ga4_property_id'))->toBe('123456789');
        expect(app(Reglages::class)->get('google_service_account_json'))->toBe($cle);
        expect(DB::table('settings')->where('key', 'google_service_account_json')->value('value'))->not->toContain('BEGIN PRIVATE KEY');
    });

    it('refuse une clé qui n\'est pas du JSON et une propriété mal formée', function (): void {
        Livewire::actingAs($this->admin, 'staff')->test(Parametres::class)
            ->call('changerDOnglet', 'seo')
            ->set('valeurs.google_service_account_json', 'pas du json')
            ->set('valeurs.ga4_property_id', 'G-ABC')
            ->set('valeurs.gsc_site_url', 'http://pas-https')
            ->call('enregistrer')
            ->assertHasErrors(['valeurs.google_service_account_json', 'valeurs.ga4_property_id', 'valeurs.gsc_site_url']);
    });

    it('teste la connexion et dit ce qui manque', function (): void {
        Livewire::actingAs($this->admin, 'staff')->test(Parametres::class)
            ->call('changerDOnglet', 'seo')
            ->call('testerGoogle')
            ->assertSet('essaiGoogle.ga4.ok', false)
            ->assertSee('manquant');
    });
});

describe('Google', function (): void {
    beforeEach(function (): void {
        $r = app(Reglages::class);
        $r->set('google_service_account_json', cleDeCompteDeService());
        $r->set('ga4_property_id', '123456789');
        $r->set('gsc_site_url', 'sc-domain:verifall.bj');
    });

    it('échange la clé contre un jeton, puis lit GA4 et Search Console', function (): void {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-lecture']),
            'analyticsdata.googleapis.com/*' => Http::response(['rows' => [['dimensionValues' => [['value' => 'Organic Search']], 'metricValues' => [['value' => '120'], ['value' => '90'], ['value' => '300'], ['value' => '0.62']]]]]),
            'www.googleapis.com/webmasters/*' => Http::response(['rows' => [['keys' => ['fact checking bénin'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.035, 'position' => 4.26]]]),
        ]);

        $google = app(DonneesGoogle::class);

        expect($google->searchConsole()['requetes'][0])->toMatchArray(['libelle' => 'fact checking bénin', 'clics' => 12, 'position' => 4.3]);
        expect($google->analytics()['sessions'])->toBe(120);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'oauth2') && str_contains((string) $req['assertion'], '.') && count(explode('.', (string) $req['assertion'])) === 3);
        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer jeton-lecture'));
    });

    it('traduit un refus 403 en consigne lisible', function (): void {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't']),
            '*' => Http::response([], 403),
        ]);

        expect(fn () => app(DonneesGoogle::class)->searchConsole())->toThrow(GoogleIndisponible::class, 'compte de service');
    });

    it('affiche les chiffres dans l\'onglet SEO et l\'erreur sans les inventer', function (): void {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't']),
            'analyticsdata.googleapis.com/*' => Http::response([], 404),
            'www.googleapis.com/webmasters/*' => Http::response(['rows' => [['clicks' => 77, 'impressions' => 900, 'ctr' => 0.08, 'position' => 6.1]]]),
        ]);

        Livewire::actingAs($this->admin, 'staff')->test(Analytique::class)->set('vue', 'seo')
            ->assertSee('Search Console')->assertSee('77')
            ->assertSee('Propriété ou site introuvable');
    });
});

it('invite à configurer Google quand rien n\'est renseigné', function (): void {
    Livewire::actingAs($this->admin, 'staff')->test(Analytique::class)->set('vue', 'seo')
        ->assertSee('Aucun accès Google renseigné');
});

describe('Business Intelligence', function (): void {
    it('compte les inscriptions par jour, avec les jours à zéro et la variation', function (): void {
        User::factory()->count(3)->create(['created_at' => now()->subDays(2)]);
        User::factory()->count(1)->create(['created_at' => now()->subDays(9)]);

        $serie = app(IndicateursLibres::class)->serie('comptes', 7);

        expect($serie['jours'])->toHaveCount(7)
            ->and($serie['total'])->toBeGreaterThanOrEqual(3)
            ->and(collect($serie['jours'])->where('valeur', 0)->count())->toBeGreaterThan(0);
    });

    it('ne connaît que les indicateurs de la liste', function (): void {
        expect(fn () => app(IndicateursLibres::class)->serie('users; drop table users', 30))->toThrow(InvalidArgumentException::class);

        Livewire::actingAs($this->admin, 'staff')->test(Analytique::class)->set('vue', 'bi')
            ->set('indicateur', 'inconnu')->assertSet('indicateur', 'verifications')
            ->set('duree', 9999)->assertSet('duree', 30);
    });

    it('exporte la série en CSV', function (): void {
        Livewire::actingAs($this->admin, 'staff')->test(Analytique::class)->set('vue', 'bi')
            ->call('exporterLaSerie')->assertFileDownloaded('indicateur-verifications-30j.csv');
    });

    it('reste fermée au rôle sans permission analytique', function (): void {
        $mentor = User::factory()->create();
        $mentor->assignRole(UserRole::Mentor->value);

        $this->actingAs($mentor, 'staff')->get(route('staff.analytics', ['vue' => 'seo']))->assertForbidden();
    });
});
