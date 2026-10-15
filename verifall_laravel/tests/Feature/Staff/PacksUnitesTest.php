<?php

declare(strict_types=1);

use App\Core\Economy\Services\PaymentService;
use App\Enums\UserRole;
use App\Livewire\Staff\PacksUnites;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\UnitPack;
use App\Models\User;
use Database\Seeders\PacksUnitesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Packs d'unités — la grille tarifaire
|--------------------------------------------------------------------------
| Cet écran fixe des prix. Ce qui est testé ici n'est donc pas « le formulaire
| enregistre » mais : personne d'autre qu'un administrateur n'y touche, rien ne
| s'écrit sans trace, et un pack déjà acheté ne disparaît pas de l'histoire.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PacksUnitesSeeder::class);

    $this->admin = User::factory()->create(['otp_verified_at' => now()]);
    $this->admin->assignRole(UserRole::Admin->value);
});

/** Session staff prête à l'emploi — le back-office n'accepte qu'elle. */
function enTantQuAdmin(User $user)
{
    return Livewire::actingAs($user, 'staff');
}

/** Un pack neuf, sans achat. */
function packDeTest(array $attributs = []): UnitPack
{
    return UnitPack::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'code' => 'p'.random_int(100, 999),
        'label' => 'Pack de test',
        'units' => 40,
        'price_fcfa' => 1500,
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 9,
    ], $attributs));
}

describe('accès', function (): void {
    it('refuse un citoyen', function (): void {
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        $citoyen->assignRole(UserRole::Citoyen->value);

        // Le guard `staff` est le seul qui ouvre le back-office : une session
        // citoyenne, même celle d'un administrateur, n'y donne rien.
        $this->actingAs($citoyen)->get(route('staff.unit-packs'))->assertRedirect();
    });

    it('refuse un mentor', function (): void {
        $mentor = User::factory()->create(['otp_verified_at' => now()]);
        $mentor->assignRole(UserRole::Mentor->value);

        // Un mentor produit des cours ; il ne fixe pas les prix.
        test()->actingAs($mentor, 'staff')
            ->withSession(['staff.last_activity' => now()->timestamp])
            ->get(route('staff.unit-packs'))
            ->assertForbidden();
    });

    it('laisse entrer un administrateur', function (): void {
        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->assertOk()
            ->assertSee('Pack Citoyen');
    });
});

describe('création et modification', function (): void {
    it('crée un pack et le journalise', function (): void {
        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('ouvrirCreation')
            ->set('code', 'p120')
            ->set('label', 'Pack Rédaction')
            ->set('units', 120)
            ->set('prix', 3500)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $pack = UnitPack::where('code', 'p120')->first();

        expect($pack)->not->toBeNull()
            ->and($pack->units)->toBe(120)
            ->and($pack->price_fcfa)->toBe(3500);

        // Un changement de prix engage de l'argent : on doit pouvoir dire qui
        // l'a décidé, et quand.
        expect(AuditLog::where('module', 'unit_pack')->where('action', 'create')->count())->toBe(1);
    });

    it('refuse un code déjà pris', function (): void {
        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('ouvrirCreation')
            ->set('code', 'p25')
            ->set('label', 'Doublon')
            ->set('units', 10)
            ->set('prix', 500)
            ->call('enregistrer')
            ->assertHasErrors(['code']);
    });

    it('refuse un code contenant autre chose que minuscules, chiffres et tirets', function (): void {
        // Un code accentué ou espacé s'encode différemment selon le client, et
        // un pack introuvable est un achat perdu.
        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('ouvrirCreation')
            ->set('code', 'Pack Été')
            ->set('label', 'Été')
            ->set('units', 10)
            ->set('prix', 500)
            ->call('enregistrer')
            ->assertHasErrors(['code']);
    });

    it('refuse un prix ou une quantité nuls', function (): void {
        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('ouvrirCreation')
            ->set('code', 'p0')
            ->set('label', 'Gratuit')
            ->set('units', 0)
            ->set('prix', 0)
            ->call('enregistrer')
            ->assertHasErrors(['units', 'prix']);
    });

    it('ne garde qu\'un seul pack mis en avant', function (): void {
        // Deux « recommandés » côte à côte ne recommandent rien.
        expect(UnitPack::where('is_featured', true)->count())->toBe(1);

        $autre = packDeTest(['code' => 'p40']);

        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('ouvrirEdition', $autre->uuid)
            ->set('enAvant', true)
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(UnitPack::where('is_featured', true)->count())->toBe(1)
            ->and(UnitPack::where('is_featured', true)->first()->code)->toBe('p40');
    });

    it('journalise seulement ce qui a changé', function (): void {
        $pack = packDeTest(['code' => 'p40', 'price_fcfa' => 1500]);

        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('ouvrirEdition', $pack->uuid)
            ->set('prix', 1800)
            ->call('enregistrer');

        $entree = AuditLog::where('module', 'unit_pack')->latest()->first();

        // Journaliser l'objet entier noierait le changement réel dans le bruit.
        expect(array_keys($entree->after))->toBe(['price_fcfa'])
            ->and($entree->after['price_fcfa'])->toBe(1800);
    });
});

describe('retrait et suppression', function (): void {
    it('retire de la vente sans rien effacer', function (): void {
        $pack = packDeTest(['code' => 'p40']);

        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('basculerActivite', $pack->uuid);

        expect($pack->fresh()->is_active)->toBeFalse()
            ->and(UnitPack::where('code', 'p40')->exists())->toBeTrue();
    });

    it('refuse de supprimer un pack déjà acheté', function (): void {
        $pack = packDeTest(['code' => 'p40']);

        Payment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'provider' => 'mtn',
            'pack' => 'p40',
            'amount_fcfa' => 1500,
            'units' => 40,
            'status' => 'succeeded',
            'phone_encrypted' => '97000000',
        ]);

        // Effacer sa ligne rendrait illisible le paiement qui le référence :
        // on saurait qu'une somme a été encaissée, plus ce qu'elle achetait.
        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('demanderSuppression', $pack->uuid)
            ->call('supprimer');

        expect(UnitPack::where('code', 'p40')->exists())->toBeTrue();
    });

    it('supprime un pack jamais acheté, et le journalise', function (): void {
        $pack = packDeTest(['code' => 'p40']);

        enTantQuAdmin($this->admin)->test(PacksUnites::class)
            ->call('demanderSuppression', $pack->uuid)
            ->call('supprimer');

        expect(UnitPack::where('code', 'p40')->exists())->toBeFalse()
            ->and(AuditLog::where('module', 'unit_pack')->where('action', 'delete')->count())->toBe(1);
    });
});

describe('la grille sert vraiment l\'achat', function (): void {
    it('un pack retiré de la vente disparaît de l\'écran citoyen', function (): void {
        UnitPack::where('code', 'p60')->update(['is_active' => false]);

        expect(UnitPack::enVente()->pluck('code')->all())->not->toContain('p60');
    });

    it('le prix vient de la base, jamais de la requête', function (): void {
        $pack = UnitPack::where('code', 'p25')->first();
        $pack->forceFill(['price_fcfa' => 1234, 'units' => 99])->save();

        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        $citoyen->assignRole(UserRole::Citoyen->value);

        $paiement = app(PaymentService::class)
            ->initier($citoyen, 'p25', '97000000');

        // Le client n'a envoyé qu'un code : le montant et la quantité sont
        // ceux de la grille, pas ceux qu'il aurait pu glisser dans un
        // formulaire.
        expect($paiement->amount_fcfa)->toBe(1234)
            ->and($paiement->units)->toBe(99);
    });

    it('refuse un pack retiré de la vente', function (): void {
        UnitPack::where('code', 'p25')->update(['is_active' => false]);

        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        $citoyen->assignRole(UserRole::Citoyen->value);

        expect(fn () => app(PaymentService::class)
            ->initier($citoyen, 'p25', '97000000'))
            ->toThrow(RuntimeException::class);
    });
});
