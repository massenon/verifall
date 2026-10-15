<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotFaitsVerifies;
use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Enums\MotifMouvementUnite;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Infrastructure\Persistence\DepotFaitsVerifiesEloquent;
use App\Infrastructure\Persistence\EloquentUnitLogRepository;
use App\Livewire\Staff\FaitsVerifies;
use App\Livewire\Staff\JournalDesUnites;
use App\Livewire\Staff\SourcesVerifiees;
use App\Models\SourceCategory;
use App\Models\TrustedSource;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\VerifiedFact;
use App\Support\DataTables\RequeteDataTables;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Les trois tables DataTables du Fact-checking (14 septembre 2026)
|--------------------------------------------------------------------------
|
| « Fact checks », « Sources vérifiées », « Historiques des Unités IA »,
| migrés sur le contrat du pilote « Utilisateurs » (docs/DATATABLES_CONTRAT.md).
| Mêmes promesses, dans le même ordre :
|
|   · la porte — chaque source JSON est gardée comme son écran, le Mentor n'y
|     entre pas, et aucun export n'est ouvert faute de permission dédiée ;
|   · ce qui sort — une liste fermée de champs, ni `id` ni note interne ;
|   · ce qui entre — tri, filtres, SearchBuilder par liste blanche ;
|   · le jeu entier — recherche et filtres portent sur la base ;
|   · le coût — le nombre de requêtes ne dépend pas de la longueur de page ;
|   · le pont Livewire — chaque écriture fait recharger la table.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create([
        'name' => 'Admin Principal',
        'two_factor_confirmed_at' => now(),
    ]);
    $this->admin->assignRole(UserRole::Admin->value);
});

/** GET sur une source JSON du Fact-checking, en session du personnel. */
function donneesDuFactChecking(User $membre, string $route, array $parametres = []): TestResponse
{
    return test()->actingAs($membre, 'staff')->getJson(route($route, $parametres));
}

function faitDeTable(array $attributs = [], int $reutilisations = 0): VerifiedFact
{
    $fait = VerifiedFact::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'title' => 'Une rumeur quelconque '.Str::random(6),
        'summary' => '<p>Analyse.</p>',
        'verdict' => VerdictFactCheck::Faux->value,
        'keywords' => 'rumeur quelconque',
        'sources' => [['url' => 'https://gouv.bj/communique', 'name' => 'Gouvernement']],
        'is_published' => true,
    ], $attributs));

    if ($reutilisations > 0) {
        $fait->forceFill(['hits' => $reutilisations])->save();
    }

    return $fait;
}

function sourceDeTable(array $attributs = []): TrustedSource
{
    return TrustedSource::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Source '.Str::random(6),
        'domain' => Str::lower(Str::random(10)).'.bj',
        'credibility' => 70,
        'is_active' => true,
    ], $attributs));
}

function familleDeTable(string $nom): SourceCategory
{
    return SourceCategory::create([
        'uuid' => (string) Str::uuid(),
        'name' => $nom,
        'slug' => Str::slug($nom).'-'.Str::random(4),
        'default_credibility' => 70,
        'sort_order' => 0,
    ]);
}

function mouvementDeTable(User $titulaire, int $delta, MotifMouvementUnite $motif = MotifMouvementUnite::Verification, ?string $libelle = null): UnitLog
{
    return UnitLog::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $titulaire->id,
        'delta' => $delta,
        'balance_after' => max(0, $delta),
        'reason' => $motif->value,
        'label' => $libelle ?? $motif->libelleCanonique(),
    ]);
}

/**
 * Les trois couples écran → source JSON.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function ecransDuFactChecking(): array
{
    return [
        'fact checks' => ['staff.knowledge', 'staff.knowledge.data'],
        'sources vérifiées' => ['staff.trusted-sources', 'staff.trusted-sources.data'],
        'journal des unités' => ['staff.unit-journal', 'staff.unit-journal.data'],
    ];
}

// ═══════════════════════════════════════════════════════════════ la porte

it('porte exactement la même garde de route que son écran', function (string $ecran, string $donnees): void {
    $garde = static fn (string $nom): array => array_values(array_filter(
        Route::getRoutes()->getByName($nom)->gatherMiddleware(),
        static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'can:'),
    ));

    expect($garde($ecran))->not->toBe([])
        ->and($garde($donnees))->toBe($garde($ecran));
})->with(ecransDuFactChecking());

it('refuse la source JSON sans session du personnel', function (string $ecran, string $donnees): void {
    $this->getJson(route($donnees))->assertUnauthorized();
})->with(ecransDuFactChecking());

it('refuse l’écran ET sa source JSON au Mentor', function (string $ecran, string $donnees): void {
    // Aucune des trois permissions (knowledge_base.view, trusted_source.view,
    // ai_unit.view / security.unit_logs) n'est dans le rôle Mentor.
    $mentor = User::factory()->actif()->create();
    $mentor->assignRole(UserRole::Mentor->value);

    $this->actingAs($mentor, 'staff')->get(route($ecran))->assertForbidden();
    donneesDuFactChecking($mentor, $donnees)->assertForbidden();
})->with(ecransDuFactChecking());

it('refuse le journal des unités au modérateur, qui voit pourtant les deux autres', function (): void {
    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);

    donneesDuFactChecking($moderateur, 'staff.knowledge.data')->assertOk();
    donneesDuFactChecking($moderateur, 'staff.trusted-sources.data')->assertOk();
    donneesDuFactChecking($moderateur, 'staff.unit-journal.data')->assertForbidden();
});

it('ouvre le journal à security.unit_logs, l’autre moitié de la Gate', function (): void {
    $securite = User::factory()->actif()->create();
    $securite->givePermissionTo('security.unit_logs');

    donneesDuFactChecking($securite, 'staff.unit-journal.data')->assertOk();
});

it('n’ouvre AUCUN export, faute de permission dédiée — même à l’administrateur', function (string $ecran, string $donnees): void {
    // Contrat §8 : seule `user.export` existe. Pas d'export adossé à la
    // simple consultation sans décision du porteur.
    donneesDuFactChecking($this->admin, $donnees, ['export' => 1])->assertForbidden();

    $this->actingAs($this->admin, 'staff')->get(route($ecran))
        ->assertOk()
        ->assertDontSee('data-vdt-export', escape: false);
})->with(ecransDuFactChecking());

it('renvoie `draw` en entier et interdit tout cache', function (string $ecran, string $donnees): void {
    $reponse = donneesDuFactChecking($this->admin, $donnees, ['draw' => '<script>1</script>'])
        ->assertOk()
        ->assertJsonPath('draw', 0)
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data', 'exportTronque']);

    expect((string) $reponse->headers->get('Cache-Control'))->toContain('no-store');
})->with(ecransDuFactChecking());

// ═══════════════════════════════════════════════════════════ ce qui sort

it('fact checks : ne renvoie que les champs autorisés', function (): void {
    faitDeTable(['summary' => '<p>ANALYSE-COMPLETE-NON-LISTEE</p>', 'created_by' => $this->admin->id]);

    $reponse = donneesDuFactChecking($this->admin, 'staff.knowledge.data')->assertOk();

    foreach ($reponse->json('data') as $ligne) {
        expect(array_keys($ligne))->toEqualCanonicalizing([
            'uuid', 'affirmation', 'verdict', 'verdictLibelle', 'sources', 'reutilisations',
            'misAJour', 'misAJourTexte', 'statut', 'motsCles', 'actions',
        ])->and(array_keys($ligne['actions']))->toEqualCanonicalizing([
            'apercu', 'modifier', 'dupliquer', 'publier', 'depublier', 'supprimer',
        ]);
    }

    expect($reponse->getContent())
        ->not->toContain('"id"')
        ->not->toContain('ANALYSE-COMPLETE-NON-LISTEE')
        ->not->toContain('created_by');
});

it('sources : ne renvoie jamais la note éditoriale, même quand la recherche la trouve', function (): void {
    sourceDeTable(['name' => 'Média surveillé', 'notes' => 'NOTE-INTERNE-REDACTION', 'url' => 'https://secret.example']);

    $reponse = donneesDuFactChecking($this->admin, 'staff.trusted-sources.data', ['search' => ['value' => 'note-interne']])
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1);

    expect(array_keys($reponse->json('data.0')))->toEqualCanonicalizing([
        'uuid', 'nom', 'domaine', 'famille', 'credibilite', 'effet', 'actions',
    ])->and($reponse->getContent())
        ->not->toContain('NOTE-INTERNE-REDACTION')
        ->not->toContain('secret.example')
        ->not->toContain('"id"');
});

it('journal : ne renvoie ni identifiant de ligne ni identifiant de compte', function (): void {
    $titulaire = User::factory()->create(['name' => 'Alice Dossou', 'phone' => '+22990000000']);
    $mouvement = mouvementDeTable($titulaire, 25, MotifMouvementUnite::Recharge);

    $reponse = donneesDuFactChecking($this->admin, 'staff.unit-journal.data')->assertOk();

    expect(array_keys($reponse->json('data.0')))->toEqualCanonicalizing([
        'uuid', 'date', 'dateTexte', 'compte', 'motif', 'motifLibelle', 'libelle', 'mouvement', 'soldeApres',
    ])->and($reponse->json('data.0.compte'))->toBe(['nom' => 'Alice Dossou', 'email' => $titulaire->email])
        ->and($reponse->getContent())
        ->not->toContain('"id"')
        ->not->toContain('user_id')
        ->not->toContain($mouvement->reference())
        ->not->toContain('+22990000000');
});

it('fact checks : propose les actions que la Policy accorde', function (): void {
    $brouillon = faitDeTable(['is_published' => false]);

    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);

    expect(donneesDuFactChecking($this->admin, 'staff.knowledge.data')->json('data.0.actions'))
        ->toBe(['apercu' => true, 'modifier' => true, 'dupliquer' => true, 'publier' => true, 'depublier' => false, 'supprimer' => true])
        // Le modérateur rédige, il n'engage pas la plateforme.
        ->and(donneesDuFactChecking($moderateur, 'staff.knowledge.data')->json('data.0.actions'))
        ->toBe(['apercu' => true, 'modifier' => true, 'dupliquer' => true, 'publier' => false, 'depublier' => false, 'supprimer' => false])
        ->and(donneesDuFactChecking($this->admin, 'staff.knowledge.data')->json('data.0.uuid'))->toBe($brouillon->uuid);
});

it('sources : propose désactiver ou réactiver selon l’état, et rien au lecteur seul', function (): void {
    $active = sourceDeTable(['is_active' => true]);
    $inactive = sourceDeTable(['is_active' => false]);

    $lignes = collect(donneesDuFactChecking($this->admin, 'staff.trusted-sources.data')->json('data'))->keyBy('uuid');

    expect($lignes[$active->uuid]['actions'])->toBe(['modifier' => true, 'desactiver' => true, 'reactiver' => false, 'supprimer' => true])
        ->and($lignes[$inactive->uuid]['actions'])->toBe(['modifier' => true, 'desactiver' => false, 'reactiver' => true, 'supprimer' => true]);

    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);

    expect(donneesDuFactChecking($moderateur, 'staff.trusted-sources.data')->json('data.0.actions'))
        ->toBe(['modifier' => false, 'desactiver' => false, 'reactiver' => false, 'supprimer' => false]);
});

// ══════════════════════════════════════════════════ le jeu entier, borné

it('fact checks : cherche dans TOUT le jeu, sans tenir compte de la casse', function (): void {
    $cible = faitDeTable(['title' => 'Taxe WhatsApp au Bénin']);

    foreach (range(1, 15) as $i) {
        faitDeTable();
    }

    // La cible est la plus ancienne : pas sur la première page de dix.
    $reponse = donneesDuFactChecking($this->admin, 'staff.knowledge.data', ['search' => ['value' => 'WHATSAPP'], 'length' => 10]);

    $reponse->assertOk()->assertJsonPath('recordsTotal', 16)->assertJsonPath('recordsFiltered', 1);
    expect($reponse->json('data.0.uuid'))->toBe($cible->uuid);

    // Les mots-clés comptent aussi, comme dans l'ancienne liste.
    faitDeTable(['title' => 'Autre formulation', 'keywords' => 'concours police reporte']);
    expect(donneesDuFactChecking($this->admin, 'staff.knowledge.data', ['search' => ['value' => 'Police']])->json('recordsFiltered'))->toBe(1);
});

it('sources : cherche dans TOUT le référentiel, actives et inactives', function (): void {
    $cible = sourceDeTable(['name' => 'Agence Bénin Presse', 'domain' => 'abp.bj', 'is_active' => false]);

    foreach (range(1, 12) as $i) {
        sourceDeTable();
    }

    $reponse = donneesDuFactChecking($this->admin, 'staff.trusted-sources.data', ['search' => ['value' => 'ABP.BJ'], 'length' => 5]);

    $reponse->assertJsonPath('recordsTotal', 13)->assertJsonPath('recordsFiltered', 1);
    expect($reponse->json('data.0.uuid'))->toBe($cible->uuid);
});

it('journal : cherche aussi sur le titulaire du compte, dans tout le journal', function (): void {
    $alice = User::factory()->create(['name' => 'Alice Dossou']);
    $bob = User::factory()->create(['name' => 'Bob Aholou']);

    $cible = mouvementDeTable($alice, 10, MotifMouvementUnite::Bienvenue);

    foreach (range(1, 12) as $i) {
        mouvementDeTable($bob, -1);
    }

    $reponse = donneesDuFactChecking($this->admin, 'staff.unit-journal.data', ['search' => ['value' => 'alice'], 'length' => 5]);

    $reponse->assertJsonPath('recordsTotal', 13)->assertJsonPath('recordsFiltered', 1);
    expect($reponse->json('data.0.uuid'))->toBe($cible->uuid);
});

it('plafonne la longueur à 100 et ramène `-1` à 25', function (string $ecran, string $donnees): void {
    $titulaire = User::factory()->create();

    foreach (range(1, 101) as $i) {
        match ($donnees) {
            'staff.knowledge.data' => faitDeTable(),
            'staff.trusted-sources.data' => sourceDeTable(),
            default => mouvementDeTable($titulaire, 1),
        };
    }

    expect(donneesDuFactChecking($this->admin, $donnees, ['length' => 1000000])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_MAXIMUM)
        ->and(donneesDuFactChecking($this->admin, $donnees, ['length' => -1])->json('data'))->toHaveCount(RequeteDataTables::LONGUEUR_PAR_DEFAUT)
        ->and(donneesDuFactChecking($this->admin, $donnees, ['start' => 100, 'length' => 10])->json('data'))->toHaveCount(1);
})->with(ecransDuFactChecking());

it('ignore un tri hors liste blanche au lieu de le transmettre à SQL', function (string $ecran, string $donnees): void {
    foreach (['id', 'notes', 'summary', 'user_id; DROP TABLE users', 'password'] as $colonne) {
        donneesDuFactChecking($this->admin, $donnees, [
            'columns' => [['data' => $colonne]],
            'order' => [['column' => 0, 'dir' => 'asc'], ['name' => $colonne, 'dir' => 'desc; --']],
        ])->assertOk();
    }
})->with(ecransDuFactChecking());

it('trie sur les colonnes de la liste blanche, y compris par sous-requête', function (): void {
    faitDeTable(['title' => 'Peu reprise'], reutilisations: 2);
    faitDeTable(['title' => 'Très reprise'], reutilisations: 900);

    expect(donneesDuFactChecking($this->admin, 'staff.knowledge.data', ['order' => [['name' => 'reutilisations', 'dir' => 'desc']]])
        ->json('data.0.affirmation'))->toBe('Très reprise');

    // Par le NOM de la famille, pas par sa clé étrangère.
    $zeta = familleDeTable('Zeta');
    $alpha = familleDeTable('Alpha');
    sourceDeTable(['name' => 'Chez Zeta', 'source_category_id' => $zeta->id]);
    sourceDeTable(['name' => 'Chez Alpha', 'source_category_id' => $alpha->id]);

    expect(donneesDuFactChecking($this->admin, 'staff.trusted-sources.data', ['order' => [['name' => 'famille', 'dir' => 'asc']]])
        ->json('data.0.nom'))->toBe('Chez Alpha');

    // Par le NOM du titulaire, pas par `user_id`.
    mouvementDeTable(User::factory()->create(['name' => 'Zoé Zinsou']), 5);
    mouvementDeTable(User::factory()->create(['name' => 'Albert Adjovi']), 5);

    expect(donneesDuFactChecking($this->admin, 'staff.unit-journal.data', ['order' => [['name' => 'compte', 'dir' => 'asc']]])
        ->json('data.0.compte.nom'))->toBe('Albert Adjovi');
});

it('fact checks : applique verdict, statut et période, et ignore l’inconnu', function (): void {
    $vrai = faitDeTable(['verdict' => VerdictFactCheck::Vrai->value]);
    $brouillon = faitDeTable(['is_published' => false]);
    $ancien = faitDeTable();
    VerifiedFact::query()->whereKey($ancien->id)->update(['updated_at' => '2026-03-10 12:00:00']);

    $uuids = static fn (array $filtres): array => collect(donneesDuFactChecking(test()->admin, 'staff.knowledge.data', ['filtres' => $filtres])->json('data'))
        ->pluck('uuid')->all();

    expect($uuids(['verdict' => 'true']))->toBe([$vrai->uuid])
        ->and($uuids(['statut' => 'brouillon']))->toBe([$brouillon->uuid])
        ->and($uuids(['maj_du' => '2026-03-01', 'maj_au' => '2026-03-31']))->toBe([$ancien->uuid])
        // Inconnu : ignoré, la liste reste entière.
        ->and($uuids(['verdict' => 'peut-etre', 'statut' => 'archive', 'categorie' => 'x', 'maj_du' => '2026-02-31']))->toHaveCount(3);
});

it('sources : applique famille, état et crédibilité, orphelines comprises', function (): void {
    $famille = familleDeTable('Agences de presse');
    $agence = sourceDeTable(['source_category_id' => $famille->id, 'credibility' => 92]);
    $orpheline = sourceDeTable(['credibility' => 55]);
    $retiree = sourceDeTable(['is_active' => false, 'credibility' => 20]);

    $uuids = static fn (array $filtres): array => collect(donneesDuFactChecking(test()->admin, 'staff.trusted-sources.data', ['filtres' => $filtres])->json('data'))
        ->pluck('uuid')->all();

    expect($uuids(['famille' => $famille->uuid]))->toBe([$agence->uuid])
        ->and($uuids(['famille' => 'sans', 'etat' => 'actives']))->toBe([$orpheline->uuid])
        ->and($uuids(['etat' => 'inactives']))->toBe([$retiree->uuid])
        ->and($uuids(['confiance' => 'elevee']))->toBe([$agence->uuid])
        ->and($uuids(['confiance' => 'moyenne']))->toBe([$orpheline->uuid])
        ->and($uuids(['confiance' => 'faible']))->toBe([$retiree->uuid])
        // Une famille qui n'existe pas n'est pas en liste blanche : ignorée.
        ->and($uuids(['famille' => (string) Str::uuid(), 'type' => 'presse']))->toHaveCount(3);
});

it('journal : applique sens, motif et période', function (): void {
    $titulaire = User::factory()->create();
    $credit = mouvementDeTable($titulaire, 50, MotifMouvementUnite::Recharge);
    $debit = mouvementDeTable($titulaire, -3, MotifMouvementUnite::Verification);
    $ancien = mouvementDeTable($titulaire, 10, MotifMouvementUnite::Parrainage);
    UnitLog::query()->whereKey($ancien->id)->update(['created_at' => '2026-03-10 12:00:00']);

    $uuids = static fn (array $filtres): array => collect(donneesDuFactChecking(test()->admin, 'staff.unit-journal.data', ['filtres' => $filtres])->json('data'))
        ->pluck('uuid')->all();

    expect($uuids(['sens' => 'debit']))->toBe([$debit->uuid])
        ->and($uuids(['motif' => 'recharge']))->toBe([$credit->uuid])
        ->and($uuids(['periode_du' => '2026-03-01', 'periode_au' => '2026-03-31']))->toBe([$ancien->uuid])
        ->and($uuids(['sens' => 'tous', 'motif' => 'inconnu', 'outil' => 'x', 'statut' => 'ok']))->toHaveCount(3);
});

it('traduit SearchBuilder à travers la liste blanche', function (): void {
    faitDeTable(['keywords' => 'vaccin rougeole'], reutilisations: 40);
    faitDeTable(['keywords' => 'vaccin polio'], reutilisations: 5);
    faitDeTable(['keywords' => 'concours'], reutilisations: 70);

    $criteres = ['logic' => 'AND', 'criteria' => [
        ['origData' => 'motsCles', 'condition' => 'starts', 'value' => ['vaccin']],
        ['origData' => 'reutilisations', 'condition' => '>', 'value' => ['10']],
    ]];

    expect(donneesDuFactChecking($this->admin, 'staff.knowledge.data', ['searchBuilder' => $criteres])->json('recordsFiltered'))->toBe(1);

    $titulaire = User::factory()->create();
    mouvementDeTable($titulaire, 5, MotifMouvementUnite::AjustementAdmin, 'Paiement abouti sans crédit, ticket 4412.');
    mouvementDeTable($titulaire, -2);

    $journal = ['logic' => 'AND', 'criteria' => [['origData' => 'libelle', 'condition' => 'contains', 'value' => ['ticket']]]];
    expect(donneesDuFactChecking($this->admin, 'staff.unit-journal.data', ['searchBuilder' => $journal])->json('recordsFiltered'))->toBe(1);

    // Hors liste blanche : ignoré.
    $intrus = ['logic' => 'AND', 'criteria' => [['origData' => 'summary', 'condition' => '!null', 'value' => []]]];
    expect(donneesDuFactChecking($this->admin, 'staff.knowledge.data', ['searchBuilder' => $intrus])->json('recordsFiltered'))->toBe(3);
});

it('déclare au dépôt exactement les colonnes que SearchBuilder peut interroger', function (): void {
    $types = static fn (string $classe): array => array_keys((new ReflectionClassConstant($classe, 'TYPES_SEARCHBUILDER'))->getValue());

    expect($types(DepotFaitsVerifiesEloquent::class))->toEqualCanonicalizing(DepotFaitsVerifies::COLONNES_SEARCHBUILDER)
        ->and($types(EloquentUnitLogRepository::class))->toEqualCanonicalizing(UnitLogRepositoryInterface::COLONNES_SEARCHBUILDER);
});

// ════════════════════════════════════════════════════════════════ le coût

it('ne multiplie pas les requêtes avec la taille de la page', function (string $ecran, string $donnees): void {
    $famille = familleDeTable('Institutions');

    foreach (range(1, 40) as $i) {
        $titulaire = User::factory()->create();

        match ($donnees) {
            'staff.knowledge.data' => faitDeTable(['is_published' => $i % 2 === 0]),
            'staff.trusted-sources.data' => sourceDeTable(['source_category_id' => $i % 2 === 0 ? $famille->id : null]),
            default => mouvementDeTable($titulaire, $i % 2 === 0 ? 5 : -1),
        };
    }

    $compter = function (int $longueur) use ($donnees): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        donneesDuFactChecking($this->admin, $donnees, ['length' => $longueur])->assertOk();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    };

    $compter(5); // amorce les caches de permissions

    expect($compter(40))->toBe($compter(5));
})->with(ecransDuFactChecking());

// ══════════════════════════════════════════════════════════ le pont Livewire

it('fact checks : demande à la table de se recharger après chaque écriture', function (): void {
    $fait = faitDeTable(['is_published' => false]);

    Livewire::actingAs($this->admin, 'staff')
        ->test(FaitsVerifies::class)
        ->call('basculerLaPublication', $fait->uuid)
        ->assertDispatched(FaitsVerifies::EVENEMENT_TABLE_A_RECHARGER);

    Livewire::actingAs($this->admin, 'staff')
        ->test(FaitsVerifies::class)
        ->call('ouvrirEdition', $fait->uuid)
        ->set('affirmation', 'Une affirmation reformulée pour le test')
        ->call('enregistrer')
        ->assertHasNoErrors()
        ->assertDispatched(FaitsVerifies::EVENEMENT_TABLE_A_RECHARGER);

    Livewire::actingAs($this->admin, 'staff')
        ->test(FaitsVerifies::class)
        ->call('confirmerSuppression', $fait->uuid)
        ->call('supprimer')
        ->assertDispatched(FaitsVerifies::EVENEMENT_TABLE_A_RECHARGER);
});

it('fact checks : ne demande rien quand la publication est refusée', function (): void {
    $sansSource = faitDeTable(['is_published' => false, 'sources' => []]);

    Livewire::actingAs($this->admin, 'staff')
        ->test(FaitsVerifies::class)
        ->call('basculerLaPublication', $sansSource->uuid)
        ->assertNotDispatched(FaitsVerifies::EVENEMENT_TABLE_A_RECHARGER);
});

it('sources : demande à la table de se recharger après chaque écriture', function (): void {
    $source = sourceDeTable();

    Livewire::actingAs($this->admin, 'staff')
        ->test(SourcesVerifiees::class)
        ->call('basculerLActivation', $source->uuid)
        ->assertDispatched(SourcesVerifiees::EVENEMENT_TABLE_A_RECHARGER);

    Livewire::actingAs($this->admin, 'staff')
        ->test(SourcesVerifiees::class)
        ->call('ouvrirEdition', $source->uuid)
        ->call('enregistrer')
        ->assertHasNoErrors()
        ->assertDispatched(SourcesVerifiees::EVENEMENT_TABLE_A_RECHARGER);

    Livewire::actingAs($this->admin, 'staff')
        ->test(SourcesVerifiees::class)
        ->call('demanderLaSuppression', $source->uuid)
        ->call('supprimer')
        ->assertDispatched(SourcesVerifiees::EVENEMENT_TABLE_A_RECHARGER);
});

it('journal : demande à la table de se recharger après un ajustement', function (): void {
    User::factory()->create(['email' => 'cible@verifall.test', 'units' => 0]);

    Livewire::actingAs($this->admin, 'staff')
        ->test(JournalDesUnites::class)
        ->call('ouvrirAjustement')
        ->set('compte', 'cible@verifall.test')
        ->set('unites', '10')
        ->set('justification', 'Paiement abouti sans crédit, ticket 4412.')
        ->call('ajuster')
        ->assertHasNoErrors()
        ->assertDispatched(JournalDesUnites::EVENEMENT_TABLE_A_RECHARGER);
});

it('sert chaque écran avec sa zone DataTables, ses assets et ses indicateurs au-dessus', function (string $ecran, string $donnees): void {
    $slug = [
        'staff.knowledge' => 'faits-verifies',
        'staff.trusted-sources' => 'sources-verifiees',
        'staff.unit-journal' => 'journal-des-unites',
    ][$ecran];

    $html = (string) $this->actingAs($this->admin, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp])
        ->get(route($ecran))
        ->assertOk()
        ->assertSee('data-vdt="'.$slug.'"', escape: false)
        ->assertSee(route($donnees), escape: false)
        ->assertSee('assets/vendor/datatables/js/dataTables-2.3.8.min.js', escape: false)
        ->assertSee('assets/js/pages/staff-'.$slug.'.js', escape: false)
        ->getContent();

    // Les indicateurs restent AU-DESSUS de la table.
    expect(strpos($html, 'st-kpi'))->toBeLessThan(strpos($html, 'data-vdt="'.$slug.'"'));
})->with(ecransDuFactChecking());

it('écrit tiroirs et modales DANS la racine du composant, que Livewire suit', function (string $vue): void {
    /*
     | Le piège du pilote (contrat §6.1) : tout ce qui suit la `</div>` racine
     | n'est jamais rendu par Livewire, et les tests Livewire passent quand
     | même — ils lisent l'état, pas le DOM. On lit donc la SOURCE : la racine
     | se ferme après le dernier tiroir et la dernière modale.
     */
    $source = (string) file_get_contents(resource_path('views/livewire/staff/'.$vue));
    // Commentaires Blade d'abord (ils ne rendent rien), puis les piles
    // `@push`, qui vivent légitimement hors de la racine.
    $horsPiles = (string) preg_replace(['/\{\{--.*?--\}\}/s', '/@push\(.*?@endpush/s'], '', $source);
    $finDeRacine = strrpos($horsPiles, "\n</div>");

    expect($finDeRacine)->not->toBeFalse()
        ->and(trim(substr($horsPiles, $finDeRacine + 7)))->toBe('');

    foreach (['<x-offcanvas-form', '<x-delete-modal', 'data-vdt='] as $bloc) {
        $position = strrpos($horsPiles, $bloc);

        if ($position !== false) {
            expect($position)->toBeLessThan($finDeRacine);
        }
    }
})->with(['faits-verifies.blade.php', 'sources-verifiees.blade.php', 'journal-des-unites.blade.php']);
