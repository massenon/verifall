<?php

declare(strict_types=1);

use App\Core\Application\Services\ModeMaintenance;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Testing\TestResponse;

/*
| Les portes qui DOIVENT rester ouvertes pendant une maintenance — et celles
| qui doivent rester fermées. Voir l'arbitrage 181.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);

    $this->maintenance = app(ModeMaintenance::class);
});

/**
 * Rejoue l'appel `/livewire/update` que le navigateur ferait depuis une page.
 *
 * @return array{0: string, 1: string} le jeton CSRF et l'instantané du premier composant
 */
function instantaneDe(string $html): array
{
    preg_match('/name="csrf-token" content="([^"]+)"/', $html, $jeton);
    preg_match('/wire:snapshot="([^"]+)"/', $html, $instantane);

    return [$jeton[1] ?? '', html_entity_decode($instantane[1] ?? '', ENT_QUOTES)];
}

function appelLivewire(object $test, string $instantane, string $jeton): TestResponse
{
    return $test->withHeaders(['X-Livewire' => '1', 'X-CSRF-TOKEN' => $jeton])
        ->postJson(route('default.livewire.update'), [
            '_token' => $jeton,
            'components' => [['snapshot' => $instantane, 'updates' => new stdClass, 'calls' => []]],
        ]);
}

it('laisse le formulaire Livewire de connexion console fonctionner pendant la maintenance', function (): void {
    /*
     | LE trou que le test historique ne voyait pas : il vérifiait que la PAGE
     | de connexion répond, mais le formulaire poste sur `/livewire/update`.
     | Sans exemption ciblée, un membre non connecté voyait le formulaire et
     | ne pouvait pas s'en servir — donc personne ne pouvait rouvrir.
     */
    $this->maintenance->activer($this->admin);

    [$jeton, $instantane] = instantaneDe($this->get(route('staff.login'))->assertOk()->getContent());
    expect($instantane)->toContain('auth.staff-login');

    appelLivewire($this, $instantane, $jeton)->assertOk();
});

it('n’ouvre PAS /livewire/update aux composants publics', function (): void {
    $this->maintenance->activer($this->admin);

    // Un instantané forgé au nom d'un composant public : la porte reste fermée.
    $faux = json_encode(['data' => [], 'memo' => ['name' => 'public.accueil', 'id' => 'x'], 'checksum' => 'x']);

    appelLivewire($this, (string) $faux, 'jeton')->assertStatus(503);
});

it('ne laisse pas un composant public passer en se cachant derrière celui de la connexion', function (): void {
    $this->maintenance->activer($this->admin);

    $connexion = json_encode(['memo' => ['name' => 'auth.staff-login']]);
    $public = json_encode(['memo' => ['name' => 'citizen.reglages']]);

    $this->withHeaders(['X-Livewire' => '1'])->postJson(route('default.livewire.update'), [
        'components' => [
            ['snapshot' => $connexion, 'updates' => [], 'calls' => []],
            ['snapshot' => $public, 'updates' => [], 'calls' => []],
        ],
    ])->assertStatus(503);
});

it('laisse l’écran de verrouillage joignable', function (): void {
    // Un membre verrouillé est DÉCONNECTÉ de la garde staff : sans exemption,
    // verrouiller sa session pendant une maintenance l'enfermerait dehors.
    $this->maintenance->activer($this->admin);

    $this->actingAs($this->admin, 'staff')->post(route('staff.lock'))->assertRedirect(route('staff.verrou'));

    $this->get(route('staff.verrou'))->assertOk();
});

it('laisse passer les rappels de paiement Mobile Money', function (): void {
    /*
     | Un opérateur qui reçoit 503 ne rejoue pas forcément : l'unité payée ne
     | serait jamais créditée. La route vérifie sa signature HMAC elle-même ;
     | la maintenance n'a pas à s'interposer. Ici, signature absente : c'est
     | le CONTRÔLEUR qui refuse, plus le 503.
     */
    $this->maintenance->activer($this->admin);

    $reponse = $this->postJson(route('webhooks.momo', ['provider' => 'fake']), []);

    expect($reponse->getStatusCode())->not->toBe(503);
});

it('répond en JSON aux appels qui attendent du JSON', function (): void {
    $this->maintenance->activer($this->admin, now()->addHour()->toImmutable());

    $this->getJson(route('public.home'))
        ->assertStatus(503)
        ->assertHeader('Retry-After')
        ->assertJsonPath('maintenance', true)
        ->assertJsonStructure(['message', 'fin_annoncee']);
});

it('expose un état léger que la page publique interroge', function (): void {
    $this->getJson(route('maintenance.etat'))->assertOk()->assertJsonPath('actif', false);

    $this->maintenance->activer($this->admin);

    $this->getJson(route('maintenance.etat'))->assertOk()
        ->assertJsonPath('actif', true)
        ->assertJsonPath('en_retard', false);
});

it('montre au personnel un bandeau tant que le site est fermé', function (): void {
    $this->actingAs($this->admin, 'staff');

    $this->get(route('staff.maintenance-mode'))->assertOk()->assertDontSee('data-bandeau-maintenance', false);

    $this->maintenance->activer($this->admin);

    $this->get(route('staff.maintenance-mode'))->assertOk()
        ->assertSee('data-bandeau-maintenance', false)
        ->assertSee('Site fermé au public');
});

it('retrace les dernières maintenances depuis le journal d’audit', function (): void {
    $this->maintenance->activer($this->admin, null, 'Migration de la base');
    $this->travel(40)->minutes();
    $this->maintenance->desactiver($this->admin);

    $historique = $this->maintenance->historique();

    expect($historique)->toHaveCount(1)
        ->and($historique[0]['message'])->toBe('Migration de la base')
        ->and($historique[0]['duree_minutes'])->toBe(40)
        ->and($historique[0]['par'])->toBe($this->admin->name);
});

it('rend la page publique premium, autonome et sans indexation', function (): void {
    $html = $this->get(route('maintenance'))->assertOk()->getContent();

    expect($html)->toContain('noindex')
        ->toContain('fonts/premium.css')
        ->not->toContain('tailwind.css')
        ->toContain('data-retard');
});
