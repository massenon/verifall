<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\DiffuserInfolettre;
use App\Livewire\Staff\Infolettre;
use App\Models\Campaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Infolettre, onglet « Abonnés » sous DataTables, côté serveur (14 sept. 2026)
|--------------------------------------------------------------------------
|
| Clone du pilote `TableDesUtilisateursTest`, avec une contrainte de plus :
| l'écran a trois onglets et l'éditeur de « Diffuser » a été réparé le jour
| même (`DiffusionInfolettreTest`). Seul l'onglet Abonnés change ; les deux
| autres ne doivent pas bouger, et AUCUN envoi ne part d'ici.
|
| La liste d'abonnés est un fichier d'adresses : c'est la donnée la plus
| facilement exfiltrable de la plateforme. Jetons et empreinte d'IP n'en
| sortent jamais, et il n'y a pas d'export.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Ceinture et bretelles : rien, dans ce fichier, ne doit envoyer quoi que ce soit.
    Mail::fake();
    Queue::fake();

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

function tableAbonnes(User $membre, array $parametres = []): TestResponse
{
    return test()->actingAs($membre, 'staff')
        ->getJson(route('staff.newsletter.data', $parametres));
}

function abonnePourTable(array $attributs = []): NewsletterSubscriber
{
    // `forceCreate` : `created_at` n'est pas au `$fillable`, et c'est juste.
    return NewsletterSubscriber::query()->forceCreate(array_merge([
        'uuid' => (string) Str::uuid(),
        'email' => 'abonne'.Str::random(10).'@example.test',
        'name' => 'Aïcha',
        'confirmation_token' => 'JETON-CONFIRMATION-'.Str::random(40),
        'unsubscribe_token' => 'JETON-DESABONNEMENT-'.Str::random(40),
        'consent_ip_hash' => 'EMPREINTE-IP-'.Str::random(40),
        'confirmed_at' => now()->subDays(3),
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ], $attributs));
}

// ═══════════════════════════════════════════════════════════════ la porte

it('refuse la source JSON au mentor et au modérateur', function (): void {
    foreach ([UserRole::Mentor, UserRole::Moderateur] as $role) {
        $membre = User::factory()->actif()->create();
        $membre->assignRole($role->value);

        tableAbonnes($membre)->assertForbidden();
    }
});

it('refuse la source JSON sans session du personnel', function (): void {
    $this->getJson(route('staff.newsletter.data'))->assertUnauthorized();
});

it('porte exactement la même garde de route que l’écran', function (): void {
    $garde = static fn (string $nom): array => array_values(array_filter(
        Route::getRoutes()->getByName($nom)->gatherMiddleware(),
        static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'can:'),
    ));

    expect($garde('staff.newsletter.data'))->toBe($garde('staff.newsletter'))
        ->and($garde('staff.newsletter.data'))->not->toBe([]);
});

it('refuse l’export, faute de permission qui le prévoie', function (): void {
    abonnePourTable();

    tableAbonnes($this->admin, ['export' => 1])->assertForbidden();
});

// ═══════════════════════════════════════════════════════════ ce qui sort

it('ne renvoie ni id, ni jeton, ni empreinte d’IP', function (): void {
    abonnePourTable(['email' => 'rene@example.test', 'name' => 'René']);

    $reponse = tableAbonnes($this->admin, ['draw' => 2])->assertOk()->assertJsonPath('draw', 2);

    expect((string) $reponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_keys($reponse->json('data.0')))->toEqualCanonicalizing([
            'uuid', 'email', 'nom', 'etat', 'inscritLe', 'inscritLeTexte',
            'confirmeLe', 'confirmeLeTexte', 'desabonneLeTexte', 'actions',
        ])
        ->and($reponse->json('data.0.email'))->toBe('rene@example.test')
        ->and($reponse->json('data.0.etat'))->toBe('confirme');

    expect($reponse->getContent())
        ->not->toContain('JETON-CONFIRMATION')
        ->not->toContain('JETON-DESABONNEMENT')
        ->not->toContain('EMPREINTE-IP')
        ->not->toContain('token')
        ->not->toContain('"id"');
});

it('ne propose « Désabonner » qu’à qui détient newsletter.send, et sur une adresse encore abonnée', function (): void {
    $abonne = abonnePourTable();
    $parti = abonnePourTable(['unsubscribed_at' => now()]);

    $lecteur = User::factory()->actif()->create();
    $lecteur->givePermissionTo('newsletter.view');

    $admin = collect(tableAbonnes($this->admin)->json('data'))->keyBy('uuid');
    $lecture = collect(tableAbonnes($lecteur)->json('data'))->keyBy('uuid');

    expect($admin[$abonne->uuid]['actions'])->toBe(['desabonner' => true])
        ->and($admin[$parti->uuid]['actions'])->toBe(['desabonner' => false])
        ->and($admin[$parti->uuid]['etat'])->toBe('desabonne')
        ->and($lecture[$abonne->uuid]['actions'])->toBe(['desabonner' => false]);
});

// ══════════════════════════════════════════════════ le jeu entier, borné

it('recherche dans TOUT le jeu, sans tenir compte de la casse', function (): void {
    for ($i = 0; $i < 30; $i++) {
        abonnePourTable(['created_at' => now()->subMinutes($i)]);
    }

    $cible = abonnePourTable(['name' => 'Rhodes Agossou', 'created_at' => now()->subYears(2)]);

    $reponse = tableAbonnes($this->admin, ['search' => ['value' => 'RHODES'], 'length' => 10]);

    $reponse->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('recordsTotal', 31);
    expect($reponse->json('data.0.uuid'))->toBe($cible->uuid);
});

it('pagine côté SQL et plafonne la longueur à 100', function (): void {
    for ($i = 0; $i < 110; $i++) {
        abonnePourTable();
    }

    expect(tableAbonnes($this->admin, ['start' => 100, 'length' => 10])->json('data'))->toHaveCount(10)
        ->and(tableAbonnes($this->admin, ['length' => 1000000])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_MAXIMUM)
        ->and(tableAbonnes($this->admin, ['length' => -1])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_PAR_DEFAUT);
});

it('trie sur une colonne de la liste blanche, et ignore le reste', function (): void {
    abonnePourTable(['email' => 'zoe@example.test']);
    abonnePourTable(['email' => 'albert@example.test']);

    $parIndice = tableAbonnes($this->admin, [
        'columns' => [['data' => 'email'], ['data' => 'inscritLe']],
        'order' => [['column' => 0, 'dir' => 'asc']],
    ]);
    expect($parIndice->json('data.0.email'))->toBe('albert@example.test');

    foreach (['confirmation_token', 'unsubscribe_token', 'consent_ip_hash', 'id; DROP TABLE newsletter_subscribers', 'etat'] as $colonne) {
        tableAbonnes($this->admin, [
            'columns' => [['data' => $colonne]],
            'order' => [['column' => 0, 'dir' => 'asc'], ['name' => $colonne, 'dir' => 'desc']],
        ])->assertOk()->assertJsonPath('recordsTotal', 2);
    }
});

it('filtre par état — mêmes valeurs que ?etat= — et par période d’inscription', function (): void {
    $confirme = abonnePourTable(['created_at' => '2026-03-10 12:00:00']);
    $attente = abonnePourTable(['confirmed_at' => null, 'created_at' => '2026-05-01 12:00:00']);
    $parti = abonnePourTable(['unsubscribed_at' => now(), 'created_at' => '2026-05-02 12:00:00']);

    $uuids = static fn (array $filtres): array => tableAbonnes(test()->admin, ['filtres' => $filtres])->json('data.*.uuid');

    expect($uuids(['etat' => 'confirmes']))->toBe([$confirme->uuid])
        ->and($uuids(['etat' => 'en-attente']))->toBe([$attente->uuid])
        ->and($uuids(['etat' => 'desabonnes']))->toBe([$parti->uuid])
        ->and($uuids(['inscrit_du' => '2026-05-01', 'inscrit_au' => '2026-05-31']))->toEqualCanonicalizing([$attente->uuid, $parti->uuid])
        ->and($uuids(['etat' => 'tous-les-jetons', 'inscrit_du' => '2026-13-01']))->toHaveCount(3);
});

it('ne multiplie pas les requêtes avec la taille de la page', function (): void {
    $compter = function (int $longueur): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        tableAbonnes($this->admin, ['length' => $longueur])->assertOk();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    };

    for ($i = 0; $i < 40; $i++) {
        abonnePourTable();
    }

    $compter(5);

    expect($compter(40))->toBe($compter(5));
});

// ══════════════════════════════════════════════════════════ le pont Livewire

it('demande à la table de se recharger après un désabonnement, toujours journalisé', function (): void {
    $abonne = abonnePourTable();

    Livewire::actingAs($this->admin, 'staff')
        ->test(Infolettre::class)
        ->call('retirer', $abonne->uuid)
        ->assertDispatched(Infolettre::EVENEMENT_TABLE_A_RECHARGER);

    expect($abonne->refresh()->estDestinataire())->toBeFalse()
        // La preuve du consentement passé est conservée.
        ->and($abonne->confirmed_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', ['module' => 'newsletter', 'target_label' => $abonne->email]);
});

// ══════════════════════════════════════════ les trois onglets, sans régression

it('rend la zone DataTables dans la racine, visible sur « Abonnés », et aucune adresse dans le HTML', function (): void {
    abonnePourTable(['email' => 'ne-pas-rendre@example.test']);

    $html = (string) $this->actingAs($this->admin, 'staff')
        ->get(route('staff.newsletter', ['q' => 'rene', 'etat' => 'en-attente']))
        ->assertOk()
        ->assertSee(route('staff.newsletter.data'), escape: false)
        ->assertSee('assets/js/pages/staff-infolettre-abonnes.js', escape: false)
        ->assertDontSee('data-vdt-export', escape: false)
        ->getContent();

    // L'adresse ne vit plus dans la page : elle arrive par la source JSON.
    expect($html)->not->toContain('ne-pas-rendre@example.test')
        ->and($html)->toMatch('/<div wire:key="infolettre-abonnes" data-ifl-panneau="abonnes"\s*>/')
        // `?q=` et `?etat=` gardent leur sens à l'arrivée.
        ->and($html)->toContain('value="rene"')
        ->and($html)->toContain('<option value="en-attente" selected');
});

it('garde le panneau des abonnés MASQUÉ sur « Diffuser », et l’éditeur Quill intact', function (): void {
    abonnePourTable();

    $html = (string) $this->actingAs($this->admin, 'staff')
        ->get(route('staff.newsletter', ['vue' => 'diffuser']))
        ->assertOk()
        ->assertSee('Diffuser une Newsletter')
        ->getContent();

    // Masqué, donc la couche JS ne monte rien (§9 bis du contrat).
    expect($html)->toMatch('/data-ifl-panneau="abonnes"\s+hidden\s*>/')
        // Le conteneur Quill et son `wire:ignore`, exactement comme avant.
        ->and($html)->toMatch('/<div class="[^"]*editeur-riche[^"]*" wire:ignore>\s*<div data-quill-diffusion><\/div>/')
        ->and($html)->toContain("Livewire.hook('morphed'")
        ->and($html)->not->toContain('Aucune édition envoyée');
});

it('bascule Abonnés → Diffuser → Historique sans perdre l’éditeur ni l’historique', function (): void {
    abonnePourTable();

    $composant = Livewire::actingAs($this->admin, 'staff')->test(Infolettre::class);

    $composant->assertSee('data-vdt="abonnes"', false)->assertDontSee('data-quill-diffusion', false);

    $composant->call('changerOnglet', 'diffuser')
        ->assertSee('data-quill-diffusion', false)
        ->assertSee('Votre message sera envoyé à')
        // Toujours dans le DOM, mais masqué : la table montée n'est pas détruite.
        ->assertSee('data-vdt="abonnes"', false);

    expect($composant->html())->toMatch('/data-ifl-panneau="abonnes"\s+hidden\s*>/');

    $composant->call('changerOnglet', 'historique')
        ->assertDontSee('data-quill-diffusion', false)
        ->assertSee('Aucune édition envoyée');

    $composant->call('changerOnglet', 'abonnes');
    expect($composant->html())->toMatch('/data-ifl-panneau="abonnes"\s*>/');

    // Aucune bascule n'a créé de campagne ni mis d'envoi en file.
    expect(Campaign::count())->toBe(0);
    Queue::assertNotPushed(DiffuserInfolettre::class);
    Mail::assertNothingSent();
});
