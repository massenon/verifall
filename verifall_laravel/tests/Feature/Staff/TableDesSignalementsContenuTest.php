<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotSignalementsContenu;
use App\Enums\UserRole;
use App\Livewire\Staff\SignalementsContenu;
use App\Models\ContentReport;
use App\Models\Thread;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| La table DataTables « Contenus signalés », côté serveur (14 sept. 2026)
|--------------------------------------------------------------------------
|
| Clone du pilote `TableDesUtilisateursTest`. Ce que ces tests tiennent :
|
|   · la porte — même garde que l'écran, ouverte au modérateur, fermée au
|     mentor, et PAS d'export (aucune permission ne le prévoit) ;
|   · ce qui sort — du texte saisi par le public, tronqué et sans balise, et
|     ni adresse de signaleur, ni note de décision, ni classe PHP, ni `id` ;
|   · la règle de la file — ce qui se répand vite passe devant, comme avant ;
|   · le jeu entier, borné — recherche, filtres, tri en liste blanche ;
|   · le coût — le nombre de requêtes ne grandit pas avec la page.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create(['name' => 'Admin Principal']);
    $this->admin->assignRole(UserRole::Admin->value);

    $this->auteur = User::factory()->actif()->create();
});

/** GET sur la source JSON, en session du personnel. */
function tableSignalementsContenu(User $membre, array $parametres = []): TestResponse
{
    return test()->actingAs($membre, 'staff')
        ->getJson(route('staff.content-reports.data', $parametres));
}

/** Un sujet du forum et N signalements dessus, à la date voulue. */
function signalementPourTable(array $attributs = [], ?Thread $sujet = null): ContentReport
{
    $sujet ??= Thread::query()->forceCreate([
        'uuid' => (string) Str::uuid(),
        'slug' => 'sujet-'.Str::random(8),
        'user_id' => test()->auteur->id,
        'title' => 'Un sujet du forum',
        'body' => '<p>Contenu.</p>',
        'last_activity_at' => now(),
    ]);

    return ContentReport::query()->forceCreate(array_merge([
        'uuid' => (string) Str::uuid(),
        'user_id' => User::factory()->actif()->create()->id,
        'reportable_type' => Thread::class,
        'reportable_id' => $sujet->id,
        'reason' => 'desinformation',
        'status' => 'pending',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ], $attributs));
}

// ═══════════════════════════════════════════════════════════════ la porte

it('refuse la source JSON au mentor et à qui n’a pas content_report.view', function (): void {
    $mentor = User::factory()->actif()->create();
    $mentor->assignRole(UserRole::Mentor->value);

    expect($mentor->can('content_report.view'))->toBeFalse();

    tableSignalementsContenu($mentor)->assertForbidden();
    tableSignalementsContenu(User::factory()->actif()->create())->assertForbidden();
});

it('ouvre la source JSON au modérateur, comme l’écran', function (): void {
    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);

    tableSignalementsContenu($moderateur)->assertOk();
});

it('refuse la source JSON sans session du personnel', function (): void {
    $this->getJson(route('staff.content-reports.data'))->assertUnauthorized();
});

it('porte exactement la même garde de route que l’écran', function (): void {
    $garde = static fn (string $nom): array => array_values(array_filter(
        Route::getRoutes()->getByName($nom)->gatherMiddleware(),
        static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'can:'),
    ));

    expect($garde('staff.content-reports.data'))->toBe($garde('staff.content-reports'))
        ->and($garde('staff.content-reports.data'))->not->toBe([]);
});

it('refuse l’export, faute de permission qui le prévoie', function (): void {
    // `MatricePermissions` ne déclare aucun `content_report.export` (§8 du contrat).
    tableSignalementsContenu($this->admin, ['export' => 1])->assertForbidden();
});

// ═══════════════════════════════════════════════════════════ ce qui sort

it('ne renvoie que les champs autorisés — ni id, ni adresse, ni note, ni classe PHP', function (): void {
    $signaleur = User::factory()->actif()->create(['name' => 'Awa Signaleuse', 'email' => 'awa.signaleuse@example.test']);

    signalementPourTable([
        'user_id' => $signaleur->id,
        'handling_note' => 'NOTE-INTERNE-A-NE-PAS-FUIR',
    ]);

    $reponse = tableSignalementsContenu($this->admin, ['draw' => 4])->assertOk()->assertJsonPath('draw', 4);

    expect((string) $reponse->headers->get('Cache-Control'))->toContain('no-store');

    $attendus = [
        'uuid', 'type', 'extrait', 'contenuSupprime', 'detail', 'propagation', 'flambee', 'motif',
        'signalePar', 'statut', 'statutLibelle', 'statutBadge', 'recuLe', 'recuLeTexte', 'actions',
    ];

    $ligne = $reponse->json('data.0');

    expect(array_keys($ligne))->toEqualCanonicalizing($attendus)
        ->and(array_keys($ligne['actions']))->toEqualCanonicalizing(['ouvrir', 'trancher'])
        ->and($ligne['type'])->toBe('Thread')
        ->and($ligne['motif'])->toBe('Fausse information')
        ->and($ligne['signalePar'])->toBe('Awa Signaleuse');

    expect($reponse->getContent())
        ->not->toContain('awa.signaleuse@example.test')
        ->not->toContain('NOTE-INTERNE-A-NE-PAS-FUIR')
        ->not->toContain('App\\\\Models')
        ->not->toContain('"id"')
        ->not->toContain('reportable');
});

it('envoie du TEXTE tronqué, jamais une balise saisie par le public', function (): void {
    $sujet = Thread::query()->forceCreate([
        'uuid' => (string) Str::uuid(),
        'slug' => 'sujet-piege',
        'user_id' => $this->auteur->id,
        'title' => '<img src=x onerror=alert(1)>'.str_repeat('Rumeur ', 60),
        'body' => '<p>Corps</p>',
        'last_activity_at' => now(),
    ]);

    signalementPourTable(['detail' => '<script>alert("xss")</script>Voici pourquoi'], $sujet);

    $ligne = tableSignalementsContenu($this->admin)->json('data.0');

    expect($ligne['extrait'])->not->toContain('<img')
        ->and(mb_strlen($ligne['extrait']))->toBeLessThanOrEqual(143)
        ->and($ligne['detail'])->not->toContain('<script')
        ->and($ligne['detail'])->toContain('Voici pourquoi');
});

it('dit quand le contenu visé n’existe plus, et quand le signaleur est inconnu', function (): void {
    signalementPourTable(['reportable_id' => 999999, 'user_id' => null]);

    $ligne = tableSignalementsContenu($this->admin)->json('data.0');

    expect($ligne['contenuSupprime'])->toBeTrue()
        ->and($ligne['extrait'])->toBeNull()
        ->and($ligne['signalePar'])->toBeNull();
});

it('ne propose de trancher qu’à qui détient content_report.handle, sur un signalement en attente', function (): void {
    $enAttente = signalementPourTable();
    $tranche = signalementPourTable(['status' => 'dismissed']);

    $lecteur = User::factory()->actif()->create();
    $lecteur->givePermissionTo('content_report.view');

    $admin = collect(tableSignalementsContenu($this->admin)->json('data'))->keyBy('uuid');
    $lecture = collect(tableSignalementsContenu($lecteur)->json('data'))->keyBy('uuid');

    expect($admin[$enAttente->uuid]['actions'])->toBe(['ouvrir' => true, 'trancher' => true])
        ->and($admin[$tranche->uuid]['actions']['trancher'])->toBeFalse()
        ->and($lecture[$enAttente->uuid]['actions'])->toBe(['ouvrir' => true, 'trancher' => false]);
});

// ══════════════════════════════════════════════════════ la règle de la file

it('met la flambée devant par défaut, puis l’ancienneté — comme la file d’avant', function (): void {
    $ancien = signalementPourTable(['created_at' => now()->subHours(30)]);
    $recent = signalementPourTable(['created_at' => now()->subHours(3)]);

    $sujetEnFlambee = Thread::query()->forceCreate([
        'uuid' => (string) Str::uuid(), 'slug' => 'flambee', 'user_id' => $this->auteur->id,
        'title' => 'Rumeur', 'body' => '<p>Rumeur</p>', 'last_activity_at' => now(),
    ]);
    $flambee = signalementPourTable(['created_at' => now()->subHours(2)], $sujetEnFlambee);
    signalementPourTable(['created_at' => now()->subHour()], $sujetEnFlambee);

    $parDefaut = tableSignalementsContenu($this->admin, ['order' => [['name' => 'propagation', 'dir' => 'desc']]]);
    $uuids = $parDefaut->json('data.*.uuid');

    expect($uuids[0])->toBe($flambee->uuid)
        ->and($parDefaut->json('data.0.flambee'))->toBeTrue()
        ->and($parDefaut->json('data.0.propagation'))->toContain('2 alertes')
        ->and(array_search($ancien->uuid, $uuids, true))->toBeLessThan(array_search($recent->uuid, $uuids, true));

    // Et même sans tri demandé, l'ancienneté reste le départage.
    expect(tableSignalementsContenu($this->admin)->json('data.0.uuid'))->toBe($ancien->uuid);

    // La même règle que le dépôt paginé qui la portait avant.
    expect(app(DepotSignalementsContenu::class)->paginer(null, null, 20)->first()->uuid)->toBe($flambee->uuid);
});

// ══════════════════════════════════════════════════ le jeu entier, borné

it('recherche dans TOUT le jeu, sans tenir compte de la casse — signaleur, détail et libellé de motif', function (): void {
    for ($i = 0; $i < 30; $i++) {
        signalementPourTable(['created_at' => now()->subMinutes($i)]);
    }

    $signaleur = User::factory()->actif()->create(['name' => 'Rhodes Agossou']);
    $cible = signalementPourTable(['user_id' => $signaleur->id, 'reason' => 'haine', 'created_at' => now()]);

    // La plus récente : hors de la première page de 10 (tri par ancienneté).
    $parNom = tableSignalementsContenu($this->admin, ['search' => ['value' => 'RHODES'], 'length' => 10]);
    $parNom->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('recordsTotal', 31);
    expect($parNom->json('data.0.uuid'))->toBe($cible->uuid);

    // « haineux » est dans le LIBELLÉ du motif, pas dans sa clé stockée.
    expect(tableSignalementsContenu($this->admin, ['search' => ['value' => 'HAINEUX']])->json('data.*.uuid'))->toBe([$cible->uuid]);
});

it('pagine côté SQL et plafonne la longueur à 100', function (): void {
    $sujet = signalementPourTable()->reportable;

    for ($i = 0; $i < 110; $i++) {
        signalementPourTable(['user_id' => null, 'created_at' => now()->subMinutes($i)], $sujet);
    }

    expect(tableSignalementsContenu($this->admin, ['start' => 100, 'length' => 10])->json('data'))->toHaveCount(10)
        ->and(tableSignalementsContenu($this->admin, ['length' => 1000000])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_MAXIMUM)
        ->and(tableSignalementsContenu($this->admin, ['length' => -1])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_PAR_DEFAUT);
});

it('trie sur une colonne de la liste blanche, et ignore le reste', function (): void {
    $retenu = signalementPourTable(['status' => 'upheld']);
    $rejete = signalementPourTable(['status' => 'dismissed']);

    $parStatut = tableSignalementsContenu($this->admin, [
        'columns' => [['data' => 'type'], ['data' => 'propagation'], ['data' => 'motif'], ['data' => 'signalePar'], ['data' => 'statut']],
        'order' => [['column' => 4, 'dir' => 'desc']],
    ]);
    expect($parStatut->json('data.*.uuid'))->toBe([$retenu->uuid, $rejete->uuid]);

    foreach (['handling_note', 'id; DROP TABLE content_reports', 'signalePar', 'reportable_type'] as $colonne) {
        tableSignalementsContenu($this->admin, [
            'columns' => [['data' => $colonne]],
            'order' => [['column' => 0, 'dir' => 'asc'], ['name' => $colonne, 'dir' => 'desc; --']],
        ])->assertOk()->assertJsonPath('recordsTotal', 2);
    }
});

it('applique les filtres rapides — statut, motif, type, période — et ignore les valeurs inconnues', function (): void {
    $retenu = signalementPourTable(['status' => 'upheld', 'reason' => 'spam', 'created_at' => '2026-03-10 12:00:00']);
    $enAttente = signalementPourTable(['status' => 'pending', 'reason' => 'arnaque', 'created_at' => '2026-05-01 12:00:00']);

    $uuids = static fn (array $filtres): array => tableSignalementsContenu(test()->admin, ['filtres' => $filtres])->json('data.*.uuid');

    expect($uuids(['statut' => 'upheld']))->toBe([$retenu->uuid])
        ->and($uuids(['motif' => 'arnaque']))->toBe([$enAttente->uuid])
        ->and($uuids(['type' => Thread::class]))->toHaveCount(2)
        ->and($uuids(['recu_du' => '2026-03-01', 'recu_au' => '2026-03-31']))->toBe([$retenu->uuid])
        // Inconnu, type absent de la base, date impossible : ignorés, pas une erreur 500.
        ->and($uuids(['statut' => 'supprime', 'motif' => 'n-importe', 'type' => 'App\\Models\\User', 'recu_du' => '2026-02-31']))->toHaveCount(2);
});

// ════════════════════════════════════════════════════════════════ le coût

it('ne multiplie pas les requêtes avec la taille de la page', function (): void {
    $compter = function (int $longueur): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        tableSignalementsContenu($this->admin, ['length' => $longueur])->assertOk();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    };

    for ($i = 0; $i < 40; $i++) {
        signalementPourTable(['created_at' => now()->subMinutes($i)]);
    }

    $compter(5); // amorce les caches de permissions

    expect($compter(40))->toBe($compter(5));
});

// ══════════════════════════════════════════════════════════ le pont Livewire

it('demande à la table de se recharger après une décision, toujours journalisée', function (): void {
    $signalement = signalementPourTable();

    Livewire::actingAs($this->admin, 'staff')
        ->test(SignalementsContenu::class)
        ->call('ouvrir', $signalement->uuid)
        ->call('trancher', 'upheld')
        ->assertDispatched(SignalementsContenu::EVENEMENT_TABLE_A_RECHARGER);

    expect($signalement->refresh()->status->value)->toBe('upheld');
    $this->assertDatabaseHas('audit_logs', ['action' => 'content_report.upheld', 'actor_id' => $this->admin->id]);
});

it('sert l’écran avec la zone DataTables DANS la racine, le panneau hors de wire:ignore', function (): void {
    $signalement = signalementPourTable(['detail' => 'Détail lisible dans le panneau.']);

    $html = Livewire::actingAs($this->admin, 'staff')
        ->test(SignalementsContenu::class)
        ->call('ouvrir', $signalement->uuid)
        ->html();

    // Livewire ne suit que la racine : zone et panneau doivent y être tous deux.
    expect($html)->toContain('data-vdt="signalements-contenu"')
        ->and($html)->toContain('id="signalement-ouvert"')
        ->and($html)->toContain('Détail lisible dans le panneau.')
        ->and(strpos($html, 'id="signalement-ouvert"'))->toBeLessThan(strpos($html, 'data-vdt="signalements-contenu"'));

    $this->actingAs($this->admin, 'staff')
        ->get(route('staff.content-reports', ['statut' => 'pending']))
        ->assertOk()
        ->assertSee(route('staff.content-reports.data'), escape: false)
        ->assertSee('data-vdt-livewire="filtreStatut"', escape: false)
        ->assertSee('<option value="pending" selected', escape: false)
        ->assertSee('assets/js/pages/staff-signalements-contenu.js', escape: false)
        ->assertDontSee('data-vdt-export', escape: false);
});
