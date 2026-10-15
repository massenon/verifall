<?php

declare(strict_types=1);

use App\Enums\StatutParrainage;
use App\Enums\UserRole;
use App\Livewire\Staff\Parrainages;
use App\Models\Referral;
use App\Models\User;
use App\Support\Rbac\MatricePermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

function compte(string $nom, string $email): User
{
    $u = User::factory()->create(['name' => $nom, 'email' => $email]);

    return $u;
}

function parrainage(User $parrain, User $filleul, StatutParrainage $statut, array $extra = []): Referral
{
    return Referral::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'referrer_id' => $parrain->id,
        'referred_id' => $filleul->id,
        'status' => $statut->value,
        'registered_at' => now()->subDays(3),
        'qualified_at' => $statut === StatutParrainage::Inscrit ? null : now()->subDays(2),
        'rewarded_at' => $statut === StatutParrainage::Recompense ? now()->subDay() : null,
        'referrer_points' => $statut === StatutParrainage::Recompense ? 100 : 0,
        'referred_points' => $statut === StatutParrainage::Recompense ? 50 : 0,
    ], $extra));
}

describe('accès', function (): void {
    it('refuse un visiteur anonyme', function (): void {
        $this->get(route('staff.referrals'))->assertRedirect(route('staff.login'));
    });

    it('refuse un citoyen', function (): void {
        $citoyen = User::factory()->create();
        $citoyen->assignRole(UserRole::Citoyen->value);

        $this->actingAs($citoyen, 'staff')->get(route('staff.referrals'))->assertForbidden();
    });

    it("laisse entrer l'administration", function (): void {
        $this->actingAs($this->admin, 'staff')->get(route('staff.referrals'))->assertOk();
    });
});

describe('les indicateurs', function (): void {
    it('compte chaque étape séparément', function (): void {
        parrainage(compte('Ana', 'ana@x.bj'), compte('Bob', 'bob@x.bj'), StatutParrainage::Inscrit);
        parrainage(compte('Cé', 'ce@x.bj'), compte('Dan', 'dan@x.bj'), StatutParrainage::Eligible);
        parrainage(compte('Eve', 'eve@x.bj'), compte('Fio', 'fio@x.bj'), StatutParrainage::Recompense);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parrainages::class)
            ->assertSee('Parrainages')
            // 150 unités = 100 au parrain + 50 au filleul, sur le seul récompensé.
            ->assertSee('150 unités distribuées');
    });

    it("affiche un tiret plutôt que 0 % quand rien n'a encore tourné", function (): void {
        /*
         | La distinction qui compte. « 0 % » se lit « le programme ne marche
         | pas » et conduit à l'arrêter ; l'absence de données se lit « il n'a
         | pas encore tourné ». Ce ne sont pas les mêmes décisions.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Parrainages::class)
            ->assertSee('Aucun parrainage pour l’instant')
            ->assertDontSee('0 %');
    });

    it('calcule la transformation sur les filleuls devenus vérificateurs', function (): void {
        // 1 inscrit, 1 éligible, 1 récompensé → 2 sur 3 ont vérifié.
        parrainage(compte('Ana', 'ana@x.bj'), compte('Bob', 'bob@x.bj'), StatutParrainage::Inscrit);
        parrainage(compte('Cé', 'ce@x.bj'), compte('Dan', 'dan@x.bj'), StatutParrainage::Eligible);
        parrainage(compte('Eve', 'eve@x.bj'), compte('Fio', 'fio@x.bj'), StatutParrainage::Recompense);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parrainages::class)
            ->assertSee('66.7 %');
    });
});

describe('la recherche et les filtres', function (): void {
    it('trouve par le parrain comme par le filleul', function (): void {
        /*
         | Une réclamation arrive par l'un ou par l'autre : « je n'ai pas reçu
         | mes unités » vient du parrain, « on m'a parrainé » du filleul.
         | Obliger à savoir lequel avant de chercher fait perdre du temps à
         | celui qui répond.
         */
        parrainage(compte('Adjovi', 'adjovi@x.bj'), compte('Kossi', 'kossi@x.bj'), StatutParrainage::Inscrit);
        parrainage(compte('Mensah', 'mensah@x.bj'), compte('Zinsou', 'zinsou@x.bj'), StatutParrainage::Inscrit);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parrainages::class)
            ->set('recherche', 'Adjovi')
            ->assertSee('Kossi')
            ->assertDontSee('Zinsou');

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parrainages::class)
            ->set('recherche', 'zinsou@x.bj')
            ->assertSee('Mensah')
            ->assertDontSee('Adjovi');
    });

    it('filtre par statut', function (): void {
        parrainage(compte('Ana', 'ana@x.bj'), compte('Bob', 'bob@x.bj'), StatutParrainage::Inscrit);
        parrainage(compte('Cé', 'ce@x.bj'), compte('Dan', 'dan@x.bj'), StatutParrainage::Recompense);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parrainages::class)
            ->set('filtreStatut', StatutParrainage::Recompense->value)
            ->assertSee('Dan')
            ->assertDontSee('Bob');
    });

    it('filtre par période', function (): void {
        parrainage(compte('Vieux', 'vieux@x.bj'), compte('Ancien', 'ancien@x.bj'),
            StatutParrainage::Inscrit, ['registered_at' => now()->subDays(60)]);
        parrainage(compte('Neuf', 'neuf@x.bj'), compte('Recent', 'recent@x.bj'),
            StatutParrainage::Inscrit, ['registered_at' => now()->subDay()]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(Parrainages::class)
            ->set('filtrePeriode', '7')
            ->assertSee('Recent')
            ->assertDontSee('Ancien');
    });

    it('propose de réinitialiser quand un filtre ne rend rien', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(Parrainages::class)
            ->set('recherche', 'personne-de-ce-nom')
            ->assertSee('Aucun parrainage')
            ->assertSee('Réinitialiser les filtres')
            ->call('reinitialiser')
            ->assertSet('recherche', '');
    });
});

describe('ce que l’écran n’expose PAS', function (): void {
    it("n'offre aucune action d'écriture", function (): void {
        /*
         | Ni création, ni suppression, ni « récompenser maintenant ». La
         | récompense part de `Parrainage::honorer()` au moment où la condition
         | est remplie ; un second chemin vers le même effet ne serait pas
         | couvert par les mêmes tests, et permettrait de distribuer des unités
         | sans contrepartie.
         */
        parrainage(compte('Ana', 'ana@x.bj'), compte('Bob', 'bob@x.bj'), StatutParrainage::Eligible);

        $composant = Livewire::actingAs($this->admin, 'staff')->test(Parrainages::class);

        $composant->assertDontSee('Récompenser')
            ->assertDontSee('Supprimer')
            ->assertDontSee('Nouveau parrainage');

        foreach (['supprimer', 'recompenser', 'creer', 'enregistrer'] as $methode) {
            expect(method_exists(Parrainages::class, $methode))->toBeFalse();
        }
    });

    it('ne déclare que la lecture dans la matrice de permissions', function (): void {
        $matrice = MatricePermissions::class;

        // Déclarer `referral.delete` laisserait croire qu'une suppression
        // existe quelque part — elle n'existe nulle part, et ne doit pas.
        expect(collect((new ReflectionClass($matrice))->getConstants())
            ->flatten()->toArray())->not->toContain('referral.delete');
    });
});
