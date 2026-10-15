<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Core\FactChecking\Adapters\AnalyseTechniqueAdapter;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use App\Livewire\Staff\CategoriesDeSources;
use App\Livewire\Staff\SourcesVerifiees;
use App\Models\SourceCategory;
use App\Models\TrustedSource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le référentiel des sources — lot 5
|--------------------------------------------------------------------------
| Ce n'est pas un CRUD de plus. Ce que ces tests doivent tenir, c'est le
| BRANCHEMENT : une fiche référencée change le jugement du moteur, et le cache
| ne doit pas faire mentir l'écran.
|
| Le défaut trouvé en chemin est le plus instructif du lot : la règle
| d'usurpation institutionnelle frappait `cdc.gov`, `usa.gov` et `sante.gouv.fr`
| — trente-cinq points de suspicion infligés aux institutions elles-mêmes,
| parce que la règle cherchait `gov` N'IMPORTE OÙ dans le domaine et
| n'exemptait que `gouv.bj`. Trois tests le tiennent fermé.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

describe('le moteur consulte le référentiel', function (): void {
    it('ne soupçonne plus une institution d\'usurper une institution', function (): void {
        /*
         | LE DÉFAUT, tenu par son cas le plus net. `str_contains($domaine,
         | 'gov')` frappait toutes les institutions du monde sauf une.
         */
        Http::fake(['*' => Http::response(['archived_snapshots' => []], 200)]);

        foreach (['cdc.gov', 'usa.gov', 'sante.gouv.fr', 'www.gov.uk', 'who.int'] as $domaine) {
            $verdict = app(AnalyseTechniqueAdapter::class)
                ->analyse(new SacDePreuves('https://'.$domaine.'/page', TypeContenu::Lien));

            expect($verdict->raisonnement)
                ->not->toContain('évoque une institution sans en être une')
                ->and($verdict->verdict)->not->toBe(VerdictFactCheck::Trompeur);
        }
    });

    it('soupçonne toujours un domaine qui IMITE une institution', function (): void {
        // Le pendant du test précédent : la règle devait être RESSERRÉE, pas
        // supprimée. Sans celui-ci, on aurait « corrigé » en désarmant tout.
        Http::fake(['*' => Http::response(['archived_snapshots' => []], 200)]);

        $verdict = app(AnalyseTechniqueAdapter::class)
            ->analyse(new SacDePreuves('https://actu-gouv-benin.info/article', TypeContenu::Lien));

        expect($verdict->raisonnement)->toContain('évoque une institution sans en être une');
    });

    it('cite une source référencée dans son raisonnement', function (): void {
        Http::fake(['*' => Http::response(['archived_snapshots' => []], 200)]);

        $famille = SourceCategory::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Agences de presse',
            'slug' => 'agences', 'default_credibility' => 90, 'sort_order' => 0,
        ]);

        TrustedSource::create([
            'uuid' => (string) Str::uuid(), 'source_category_id' => $famille->id,
            'name' => 'Reuters', 'domain' => 'reuters.com',
            'credibility' => 92, 'is_active' => true,
        ]);

        $verdict = app(AnalyseTechniqueAdapter::class)
            ->analyse(new SacDePreuves('https://www.reuters.com/world/article', TypeContenu::Lien));

        expect($verdict->raisonnement)
            ->toContain('Reuters')
            ->toContain('Agences de presse')
            ->toContain('92');
    });

    it('ignore une source désactivée', function (): void {
        // Une fiche inactive doit être exactement équivalente à une fiche
        // absente. Sinon « désactiver » devient un geste dont personne ne sait
        // ce qu'il fait vraiment.
        TrustedSource::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Média retiré',
            'domain' => 'exemple-retire.com', 'credibility' => 90, 'is_active' => false,
        ]);

        expect(app(DepotSourcesFiables::class)->estFiable('exemple-retire.com'))->toBeFalse();
    });
});

describe('le domaine est normalisé, sinon la fiche ne sert jamais', function (): void {
    it('ramène une adresse collée du navigateur au domaine nu', function (string $saisie): void {
        expect(TrustedSource::normaliserLeDomaine($saisie))->toBe('lemonde.fr');
    })->with([
        'lemonde.fr',
        'www.lemonde.fr',
        'https://lemonde.fr',
        'https://www.lemonde.fr/',
        'https://www.lemonde.fr/politique/article.html',
        'HTTPS://WWW.LEMONDE.FR',
        '  lemonde.fr  ',
        'lemonde.fr:443',
    ]);

    it('fait hériter les sous-domaines de leur domaine parent', function (): void {
        // Sans cela, référencer une source imposerait d'énumérer ses rubriques,
        // et la fiche paraîtrait inopérante dès la première URL réelle.
        TrustedSource::create([
            'uuid' => (string) Str::uuid(), 'name' => 'CNN',
            'domain' => 'cnn.com', 'credibility' => 80, 'is_active' => true,
        ]);

        $depot = app(DepotSourcesFiables::class);

        expect($depot->estFiable('edition.cnn.com'))->toBeTrue()
            ->and($depot->estFiable('money.edition.cnn.com'))->toBeTrue();
    });

    it('ne remonte JAMAIS jusqu\'à une extension seule', function (): void {
        /*
         | Le garde-fou de la remontée. Si quelqu'un référençait `.com` par
         | erreur — une saisie de deux caractères passe vite inaperçue — la
         | remontée rendrait tout le web « fiable » d'un coup.
         */
        TrustedSource::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Saisie fautive',
            'domain' => 'a.com', 'credibility' => 50, 'is_active' => true,
        ]);

        expect(app(DepotSourcesFiables::class)->estFiable('nimporte-quoi.com'))->toBeFalse();
    });
});

describe('l\'écran des sources', function (): void {
    it('répond et se garde derrière la permission', function (): void {
        $this->actingAs($this->admin, 'staff')
            ->get(route('staff.trusted-sources'))->assertOk();

        $citoyen = User::factory()->create();
        $citoyen->assignRole('Citoyen');

        $this->actingAs($citoyen, 'staff')
            ->get(route('staff.trusted-sources'))->assertForbidden();
    });

    it('normalise le domaine à l\'enregistrement', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(SourcesVerifiees::class)
            ->call('ouvrirCreation')
            ->set('nom', 'Le Monde')
            ->set('domaine', 'https://www.lemonde.fr/politique/')
            ->set('credibilite', '80')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(TrustedSource::query()->value('domain'))->toBe('lemonde.fr');
    });

    it('refuse un doublon même écrit autrement', function (): void {
        TrustedSource::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Le Monde',
            'domain' => 'lemonde.fr', 'credibility' => 80, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(SourcesVerifiees::class)
            ->call('ouvrirCreation')
            ->set('nom', 'Le Monde bis')
            ->set('domaine', 'https://www.lemonde.fr/')
            ->set('credibilite', '80')
            ->call('enregistrer')
            ->assertHasErrors('domaine');

        expect(TrustedSource::query()->count())->toBe(1);
    });

    it('oublie le cache du dépôt à l\'écriture', function (): void {
        /*
         | LA RÉGRESSION LA PLUS FACILE À ÉCRIRE ICI. Le dépôt cache quinze
         | minutes ; sans invalidation, on lit « source ajoutée » et le moteur
         | l'ignore un quart d'heure, sans que rien ne l'explique.
         */
        $depot = app(DepotSourcesFiables::class);

        // On amorce le cache sur un référentiel vide.
        expect($depot->estFiable('nouveau-media.bj'))->toBeFalse();

        Livewire::actingAs($this->admin, 'staff')
            ->test(SourcesVerifiees::class)
            ->call('ouvrirCreation')
            ->set('nom', 'Nouveau Média')
            ->set('domaine', 'nouveau-media.bj')
            ->set('credibilite', '70')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($depot->estFiable('nouveau-media.bj'))->toBeTrue();
    });

    it('borne la crédibilité à 100, que la colonne ne saurait dépasser', function (): void {
        // `tinyInteger` non signé : 256 y entrerait comme 0, et la source la
        // plus fiable deviendrait la moins fiable sans erreur.
        Livewire::actingAs($this->admin, 'staff')
            ->test(SourcesVerifiees::class)
            ->call('ouvrirCreation')
            ->set('nom', 'Test')
            ->set('domaine', 'test-borne.com')
            ->set('credibilite', '256')
            ->call('enregistrer')
            ->assertHasErrors('credibilite');
    });

    it('journalise la création', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(SourcesVerifiees::class)
            ->call('ouvrirCreation')
            ->set('nom', 'Reuters')
            ->set('domaine', 'reuters.com')
            ->set('credibilite', '92')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'trusted_source.create']);
    });
});

describe('les familles', function (): void {
    it('ne suppriment PAS les sources qu\'elles contiennent', function (): void {
        /*
         | `nullOnDelete`, et c'est délibéré : une fiche effacée par ricochet se
         | redécouvre à la main, une fiche orpheline se reclasse en un geste.
         | Le test existe parce que le réflexe naturel, en écrivant la
         | migration, est de taper `cascadeOnDelete`.
         */
        $famille = SourceCategory::create([
            'uuid' => (string) Str::uuid(), 'name' => 'À supprimer',
            'slug' => 'a-supprimer', 'default_credibility' => 70, 'sort_order' => 0,
        ]);

        TrustedSource::create([
            'uuid' => (string) Str::uuid(), 'source_category_id' => $famille->id,
            'name' => 'Survivante', 'domain' => 'survivante.com',
            'credibility' => 70, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(CategoriesDeSources::class)
            ->call('demanderLaSuppression', $famille->uuid)
            ->call('supprimer');

        expect(SourceCategory::query()->count())->toBe(0)
            ->and(TrustedSource::query()->count())->toBe(1)
            ->and(TrustedSource::query()->value('source_category_id'))->toBeNull();
    });

    it('proposent leur crédibilité par défaut à la création d\'une source', function (): void {
        $famille = SourceCategory::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Agences',
            'slug' => 'agences', 'default_credibility' => 90, 'sort_order' => 0,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(SourcesVerifiees::class)
            ->call('ouvrirCreation')
            ->set('categorieUuid', $famille->uuid)
            ->assertSet('credibilite', '90');
    });

    it('ne réécrasent PAS une valeur saisie en édition', function (): void {
        // Corriger la famille d'une fiche existante ne doit pas effacer la
        // crédibilité qu'on avait choisie pour elle.
        $famille = SourceCategory::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Agences',
            'slug' => 'agences', 'default_credibility' => 90, 'sort_order' => 0,
        ]);

        $source = TrustedSource::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Cas particulier',
            'domain' => 'particulier.com', 'credibility' => 55, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(SourcesVerifiees::class)
            ->call('ouvrirEdition', $source->uuid)
            ->set('categorieUuid', $famille->uuid)
            ->assertSet('credibilite', '55');
    });
});
