<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotUtilisateurs;
use App\Enums\UserRole;
use App\Infrastructure\Persistence\DepotUtilisateursEloquent;
use App\Livewire\Staff\Utilisateurs;
use App\Models\AuditLog;
use App\Models\KycSubmission;
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
| La table DataTables « Utilisateurs », côté serveur (pilote du 14 sept. 2026)
|--------------------------------------------------------------------------
|
| Ce que ces tests tiennent, dans l'ordre où un défaut coûterait le plus :
|
|   · **la porte** — la source JSON n'est pas moins gardée que l'écran, et
|     l'export demande SA permission ;
|   · **ce qui sort** — une liste fermée de champs, pas d'`id`, pas de secret ;
|   · **ce qui entre** — un nom de colonne, une longueur, un critère avancé
|     sont confrontés aux listes blanches ; ce qui n'y est pas est ignoré ;
|   · **le jeu entier** — recherche, filtres et pagination portent sur la base,
|     pas sur la page affichée ;
|   · **le coût** — le nombre de requêtes ne grandit pas avec la page.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create([
        'name' => 'Admin Principal',
        'email' => 'admin@verifall.test',
        'two_factor_confirmed_at' => now(),
        'created_at' => now()->subYear(),
    ]);
    $this->admin->assignRole(UserRole::Admin->value);
});

/** GET sur la source JSON, en session du personnel. */
function donneesUtilisateurs(User $membre, array $parametres = []): TestResponse
{
    return test()->actingAs($membre, 'staff')
        ->getJson(route('staff.users.data', $parametres));
}

// ═══════════════════════════════════════════════════════════════ la porte

it('refuse la source JSON à qui n’a pas user.view — mentor et modérateur compris', function (): void {
    foreach ([UserRole::Mentor, UserRole::Moderateur] as $role) {
        $membre = User::factory()->actif()->create();
        $membre->assignRole($role->value);

        donneesUtilisateurs($membre)->assertForbidden();
    }
});

it('refuse la source JSON sans session du personnel', function (): void {
    $this->getJson(route('staff.users.data'))->assertUnauthorized();
});

it('porte exactement la même garde de route que l’écran', function (): void {
    $garde = static fn (string $nom): array => array_values(array_filter(
        Route::getRoutes()->getByName($nom)->gatherMiddleware(),
        static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'can:'),
    ));

    expect($garde('staff.users.data'))->toBe($garde('staff.users'));
});

it('refuse l’export à qui consulte sans user.export', function (): void {
    /*
     | « Permission d'accès ≠ permission automatique d'export. » Le compte voit
     | la liste, et c'est tout.
     */
    $lecteur = User::factory()->actif()->create();
    $lecteur->givePermissionTo('user.view');

    donneesUtilisateurs($lecteur)->assertOk();
    donneesUtilisateurs($lecteur, ['export' => 1])->assertForbidden();

    expect(AuditLog::query()->where('action', 'user.export')->exists())->toBeFalse();
});

it('journalise l’export, avec ses critères', function (): void {
    User::factory()->count(3)->create();

    donneesUtilisateurs($this->admin, ['export' => 1, 'filtres' => ['statut' => 'actif']])
        ->assertOk()
        ->assertJsonPath('exportTronque', false);

    $trace = AuditLog::query()->where('action', 'user.export')->first();

    expect($trace)->not->toBeNull()
        ->and($trace->actor_id)->toBe($this->admin->id);
});

// ═══════════════════════════════════════════════════════════ ce qui sort

it('ne renvoie que les champs autorisés — ni id, ni secret', function (): void {
    User::factory()->actif()->create([
        'two_factor_secret' => 'SECRET-A-NE-PAS-FUIR',
        'phone' => '+22990000000',
    ]);

    $reponse = donneesUtilisateurs($this->admin, ['draw' => 3])->assertOk();

    $reponse->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data', 'exportTronque'])
        ->assertJsonPath('draw', 3);

    expect((string) $reponse->headers->get('Cache-Control'))->toContain('no-store');

    $attendus = [
        'uuid', 'nom', 'email', 'profession', 'roles', 'personnel', 'statut', 'suspension',
        'deuxFacteurs', 'kyc', 'unites', 'points', 'inscritLe', 'inscritLeTexte',
        'derniereConnexion', 'derniereConnexionTexte', 'actions',
    ];

    foreach ($reponse->json('data') as $ligne) {
        expect(array_keys($ligne))->toEqualCanonicalizing($attendus)
            ->and(array_keys($ligne['actions']))->toEqualCanonicalizing(['modifier', 'suspendre', 'retablir']);
    }

    expect($reponse->getContent())
        ->not->toContain('SECRET-A-NE-PAS-FUIR')
        ->not->toContain('+22990000000')
        ->not->toContain('"id"')
        ->not->toContain('password');
});

it('renvoie `draw` comme un ENTIER, jamais la chaîne reçue', function (): void {
    // La protection XSS que la documentation DataTables exige sur ce champ.
    donneesUtilisateurs($this->admin, ['draw' => '<script>1</script>'])
        ->assertOk()
        ->assertJsonPath('draw', 0);
});

it('propose les actions que la Policy accorde, ligne par ligne', function (): void {
    $citoyen = User::factory()->actif()->create(['name' => 'Citoyen Ordinaire']);
    $suspendu = User::factory()->actif()->create([
        'name' => 'Compte Suspendu',
        'suspended_at' => now(),
        'suspension_reason' => 'Motif de test suffisamment long.',
        'suspended_by' => $this->admin->id,
    ]);

    $lignes = collect(donneesUtilisateurs($this->admin)->json('data'))->keyBy('uuid');

    // Soi-même : modifiable, jamais suspendable.
    expect($lignes[$this->admin->uuid]['actions'])->toBe(['modifier' => true, 'suspendre' => false, 'retablir' => false])
        ->and($lignes[$citoyen->uuid]['actions'])->toBe(['modifier' => true, 'suspendre' => true, 'retablir' => false])
        ->and($lignes[$suspendu->uuid]['actions'])->toBe(['modifier' => true, 'suspendre' => false, 'retablir' => true])
        ->and($lignes[$suspendu->uuid]['statut'])->toBe('suspendu')
        ->and($lignes[$suspendu->uuid]['suspension']['par'])->toBe('Admin Principal');
});

it('ne propose pas de suspendre un membre du personnel à qui n’a pas role.manage', function (): void {
    // La même borne que `UserPolicy::suspend`, lue sur les rôles déjà chargés.
    $moderateur = User::factory()->actif()->create();
    $moderateur->givePermissionTo('user.view', 'user.suspend');

    $citoyen = User::factory()->actif()->create();

    $lignes = collect(donneesUtilisateurs($moderateur)->json('data'))->keyBy('uuid');

    expect($lignes[$this->admin->uuid]['actions']['suspendre'])->toBeFalse()
        ->and($lignes[$citoyen->uuid]['actions']['suspendre'])->toBeTrue()
        ->and($lignes[$citoyen->uuid]['actions']['modifier'])->toBeFalse();
});

// ══════════════════════════════════════════════════ le jeu entier, borné

it('recherche dans TOUT le jeu, sans tenir compte de la casse', function (): void {
    User::factory()->count(30)->create();
    $cible = User::factory()->create(['name' => 'Rhodes Agossou', 'created_at' => now()->subYears(2)]);

    // La cible est la plus ancienne : elle n'est pas sur la première page.
    $reponse = donneesUtilisateurs($this->admin, ['search' => ['value' => 'RHODES'], 'length' => 10]);

    $reponse->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('recordsTotal', 32);
    expect($reponse->json('data.0.uuid'))->toBe($cible->uuid);
});

it('pagine côté SQL et plafonne la longueur à 100', function (): void {
    User::factory()->count(120)->create();

    $page = donneesUtilisateurs($this->admin, ['start' => 100, 'length' => 10])->assertOk();
    expect($page->json('data'))->toHaveCount(10)
        ->and($page->json('recordsTotal'))->toBe(121);

    // `-1` et un million sont ramenés à des longueurs raisonnables.
    expect(donneesUtilisateurs($this->admin, ['length' => 1000000])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_MAXIMUM)
        ->and(donneesUtilisateurs($this->admin, ['length' => -1])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_PAR_DEFAUT);
});

it('trie sur une colonne de la liste blanche, par indice comme par nom', function (): void {
    User::factory()->create(['name' => 'Zoé', 'units' => 5]);
    User::factory()->create(['name' => 'Albert', 'units' => 900]);

    $colonnes = [['data' => 'nom'], ['data' => 'email'], ['data' => 'unites']];

    $parIndice = donneesUtilisateurs($this->admin, [
        'columns' => $colonnes,
        'order' => [['column' => 2, 'dir' => 'desc']],
    ]);
    expect($parIndice->json('data.0.nom'))->toBe('Albert');

    $parNom = donneesUtilisateurs($this->admin, ['order' => [['name' => 'nom', 'dir' => 'desc']]]);
    expect($parNom->json('data.0.nom'))->toBe('Zoé');
});

it('ignore un tri hors liste blanche au lieu de le transmettre à SQL', function (): void {
    User::factory()->count(2)->create();

    foreach (['password', 'id; DROP TABLE users', 'two_factor_secret', 'roles'] as $colonne) {
        donneesUtilisateurs($this->admin, [
            'columns' => [['data' => $colonne]],
            'order' => [['column' => 0, 'dir' => 'asc'], ['name' => $colonne, 'dir' => 'asc']],
        ])->assertOk()->assertJsonPath('recordsTotal', 3);
    }

    // Un sens de tri inventé retombe sur « asc », il n'atteint pas la requête.
    donneesUtilisateurs($this->admin, ['order' => [['name' => 'nom', 'dir' => 'desc; --']]])->assertOk();
});

it('applique les filtres rapides et ignore les valeurs inconnues', function (): void {
    $suspendu = User::factory()->actif()->create(['suspended_at' => now(), 'suspension_reason' => 'Motif.']);
    $inactif = User::factory()->create(['otp_verified_at' => null]);
    $verifie = User::factory()->actif()->create(['kyc_verified_at' => now()]);
    $mentor = User::factory()->actif()->create();
    $mentor->assignRole(UserRole::Mentor->value);

    $uuids = static fn (array $filtres): array => collect(donneesUtilisateurs(test()->admin, ['filtres' => $filtres])->json('data'))
        ->pluck('uuid')->all();

    expect($uuids(['statut' => 'suspendu']))->toBe([$suspendu->uuid])
        ->and($uuids(['statut' => 'non_active']))->toBe([$inactif->uuid])
        ->and($uuids(['kyc' => 'verifiee']))->toBe([$verifie->uuid])
        ->and($uuids(['role' => UserRole::Mentor->value]))->toBe([$mentor->uuid])
        ->and($uuids(['type' => 'personnel']))->toEqualCanonicalizing([$mentor->uuid, $this->admin->uuid])
        // Inconnu : ignoré, la liste reste entière — pas une erreur 500.
        ->and($uuids(['statut' => 'n-importe-quoi', 'role' => 'SuperAdmin']))->toHaveCount(5);
});

it('filtre les dossiers KYC en attente et la période d’inscription', function (): void {
    $enAttente = User::factory()->actif()->create(['created_at' => '2026-03-10 12:00:00']);
    User::factory()->actif()->create(['created_at' => '2026-05-01 12:00:00']);

    KycSubmission::query()->forceCreate([
        'uuid' => (string) Str::uuid(),
        'user_id' => $enAttente->id,
        'document_type' => 'cni',
        'status' => 'pending',
    ]);

    $kyc = donneesUtilisateurs($this->admin, ['filtres' => ['kyc' => 'en_attente']]);
    expect($kyc->json('data.*.uuid'))->toBe([$enAttente->uuid])
        ->and($kyc->json('data.0.kyc'))->toBe('en_attente');

    $periode = donneesUtilisateurs($this->admin, ['filtres' => ['inscrit_du' => '2026-03-01', 'inscrit_au' => '2026-03-31']]);
    expect($periode->json('data.*.uuid'))->toBe([$enAttente->uuid]);

    // Une date mal formée est ignorée, pas interprétée.
    expect(donneesUtilisateurs($this->admin, ['filtres' => ['inscrit_du' => '2026-02-31']])->json('recordsFiltered'))->toBe(3);
});

it('traduit SearchBuilder à travers la liste blanche, groupes compris', function (): void {
    User::factory()->create(['email' => 'agent@gouv.bj', 'units' => 50]);
    User::factory()->create(['email' => 'autre@gouv.bj', 'units' => 500]);
    User::factory()->create(['email' => 'perso@exemple.org', 'units' => 700]);

    $criteres = [
        'logic' => 'AND',
        'criteria' => [
            ['origData' => 'email', 'condition' => 'ends', 'value' => ['@gouv.bj'], 'type' => 'string'],
            ['logic' => 'OR', 'criteria' => [
                ['origData' => 'unites', 'condition' => '>', 'value' => ['100'], 'type' => 'num'],
                ['origData' => 'unites', 'condition' => 'between', 'value' => ['40', '60'], 'type' => 'num'],
            ]],
        ],
    ];

    expect(donneesUtilisateurs($this->admin, ['searchBuilder' => $criteres])->json('recordsFiltered'))->toBe(2);

    // Colonne hors liste blanche : le critère est ignoré, rien n'est filtré sur `password`.
    $intrus = ['logic' => 'AND', 'criteria' => [['origData' => 'password', 'condition' => '!null', 'value' => []]]];
    expect(donneesUtilisateurs($this->admin, ['searchBuilder' => $intrus])->json('recordsFiltered'))->toBe(4);
});

it('déclare au dépôt exactement les colonnes que SearchBuilder peut interroger', function (): void {
    // La liste publique du contrat et la table SQL de l'implémentation ne
    // doivent pas diverger : une clé sans colonne serait ignorée en silence.
    $reflet = new ReflectionClassConstant(DepotUtilisateursEloquent::class, 'TYPES_SEARCHBUILDER');

    expect(array_keys($reflet->getValue()))->toEqualCanonicalizing(DepotUtilisateurs::COLONNES_SEARCHBUILDER);
});

// ════════════════════════════════════════════════════════════════ le coût

it('ne multiplie pas les requêtes avec la taille de la page', function (): void {
    /*
     | Chaque ligne demande deux décisions de Policy et affiche rôles, auteur de
     | suspension et état KYC. Sans chargement groupé, cent lignes coûteraient
     | plusieurs centaines de requêtes.
     */
    $compter = function (int $longueur): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        donneesUtilisateurs($this->admin, ['length' => $longueur])->assertOk();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    };

    User::factory()->count(40)->actif()->create()->each(function (User $u, int $i): void {
        $u->assignRole($i % 2 === 0 ? UserRole::Mentor->value : UserRole::Citoyen->value);
    });

    $compter(5); // amorce les caches de permissions

    expect($compter(40))->toBe($compter(5));
});

// ══════════════════════════════════════════════════════════ le pont Livewire

it('demande à la table de se recharger après chaque écriture Livewire', function (): void {
    $citoyen = User::factory()->actif()->create();

    Livewire::actingAs($this->admin, 'staff')
        ->test(Utilisateurs::class)
        ->call('ouvrirLaSuspension', $citoyen->uuid)
        ->set('motifDeSuspension', 'Publication répétée de contenus haineux.')
        ->call('suspendre')
        ->assertDispatched(Utilisateurs::EVENEMENT_TABLE_A_RECHARGER)
        ->call('ouvrirLeRetablissement', $citoyen->uuid)
        ->call('retablir')
        ->assertDispatched(Utilisateurs::EVENEMENT_TABLE_A_RECHARGER);
});

it('sert l’écran avec la zone DataTables et ses assets, et seulement là', function (): void {
    $this->actingAs($this->admin, 'staff')
        ->get(route('staff.users'))
        ->assertOk()
        ->assertSee('data-vdt="utilisateurs"', escape: false)
        ->assertSee('wire:ignore', escape: false)
        ->assertSee('id="users-q"', escape: false)
        ->assertSee(route('staff.users.data'), escape: false)
        ->assertSee('assets/vendor/datatables/js/dataTables-2.3.8.min.js', escape: false)
        ->assertSee('data-vdt-export="1"', escape: false);

    // Le site public ne charge rien de DataTables.
    $this->get(route('public.home'))->assertDontSee('assets/vendor/datatables', escape: false);
});
