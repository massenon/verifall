<?php

declare(strict_types=1);

use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Core\Economy\Services\EconomyService;
use App\Enums\MotifMouvementUnite;
use App\Livewire\Staff\JournalDesUnites;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le journal des unités et l'ajustement manuel — lot 5
|--------------------------------------------------------------------------
| `MotifMouvementUnite::AjustementAdmin` existait depuis l'origine et RIEN ne
| l'émettait. Le produit déclarait un geste qu'aucune interface ne permettait
| de poser : corriger le solde d'un compte demandait `tinker` en production —
| alors que les CGU §6 s'y engagent par écrit.
|
| Deux choses à tenir ici, et la seconde est la plus facile à casser :
| l'écran écrit par `EconomyService` (jamais en direct), et la lecture globale
| ne doit pas ressembler à la lecture citoyenne — celle-ci est bornée au
| propriétaire, et l'oubli de cette borne serait une fuite.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

describe('la lecture globale', function (): void {
    it('voit les mouvements de TOUS les comptes', function (): void {
        $economie = app(EconomyService::class);

        $alice = User::factory()->create(['name' => 'Alice Dossou']);
        $bob = User::factory()->create(['name' => 'Bob Aholou']);

        $economie->crediter($alice, 10, MotifMouvementUnite::Bienvenue);
        $economie->crediter($bob, 5, MotifMouvementUnite::Parrainage);

        // Depuis la migration DataTables (14 septembre 2026), le journal est
        // servi par `staff.unit-journal.data`, et non plus rendu par le composant.
        $noms = collect($this->actingAs($this->admin, 'staff')
            ->getJson(route('staff.unit-journal.data'))->assertOk()->json('data'))
            ->pluck('compte.nom')->all();

        expect($noms)->toEqualCanonicalizing(['Alice Dossou', 'Bob Aholou']);
    });

    it('cherche aussi sur le titulaire du compte', function (): void {
        // La question réelle de cet écran — « qu'est-il arrivé aux unités de
        // cette personne ? » — et la seule qu'on ne puisse pas poser côté
        // citoyen, qui ne connaît qu'un compte.
        $economie = app(EconomyService::class);

        $alice = User::factory()->create(['name' => 'Alice Dossou']);
        $bob = User::factory()->create(['name' => 'Bob Aholou']);

        $economie->crediter($alice, 10, MotifMouvementUnite::Bienvenue);
        $economie->crediter($bob, 5, MotifMouvementUnite::Parrainage);

        $noms = collect($this->actingAs($this->admin, 'staff')
            ->getJson(route('staff.unit-journal.data', ['search' => ['value' => 'Alice']]))->assertOk()->json('data'))
            ->pluck('compte.nom')->all();

        expect($noms)->toBe(['Alice Dossou']);
    });

    it('rend les totaux, débits comptés positivement', function (): void {
        $economie = app(EconomyService::class);

        $alice = User::factory()->create();
        $economie->crediter($alice, 100, MotifMouvementUnite::Recharge);
        $economie->debiter($alice, 30, MotifMouvementUnite::Verification);

        $totaux = app(UnitLogRepositoryInterface::class)->totauxGlobaux();

        expect($totaux)->toMatchArray([
            'mouvements' => 2,
            'credits' => 100,
            // Positif : « -30 » se relit deux fois pour savoir si le signe est
            // celui du total ou celui de l'affichage.
            'debits' => 30,
            'comptes' => 1,
        ]);
    });

    it('reste borné au propriétaire sur la lecture CITOYENNE', function (): void {
        /*
         | Le garde-fou qui justifie deux méthodes plutôt qu'un paramètre
         | facultatif : si `historique()` cessait un jour de filtrer, un citoyen
         | lirait le journal de toute la plateforme, et rien ne le signalerait.
         */
        $economie = app(EconomyService::class);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $economie->crediter($alice, 10, MotifMouvementUnite::Bienvenue);
        $economie->crediter($bob, 5, MotifMouvementUnite::Parrainage);

        $sien = app(UnitLogRepositoryInterface::class)->historique($alice);

        expect($sien->total())->toBe(1)
            ->and($sien->first()?->user_id)->toBe($alice->id);
    });
});

describe('l\'ajustement manuel', function (): void {
    it('émet un mouvement au motif AjustementAdmin', function (): void {
        $cible = User::factory()->create(['email' => 'cible@verifall.com', 'units' => 0]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(JournalDesUnites::class)
            ->call('ouvrirAjustement')
            ->set('compte', 'cible@verifall.com')
            ->set('sensAjustement', 'credit')
            ->set('unites', '25')
            ->set('justification', 'Paiement abouti sans crédit, ticket 4412.')
            ->call('ajuster')
            ->assertHasNoErrors();

        expect($cible->refresh()->units)->toBe(25);

        $this->assertDatabaseHas('unit_logs', [
            'user_id' => $cible->id,
            'delta' => 25,
            'reason' => MotifMouvementUnite::AjustementAdmin->value,
        ]);
    });

    it('exige une justification, et une vraie', function (): void {
        // Un champ facultatif ici finirait vide neuf fois sur dix — et c'est la
        // seule pièce qui expliquera le geste six mois plus tard.
        User::factory()->create(['email' => 'cible@verifall.com']);

        Livewire::actingAs($this->admin, 'staff')
            ->test(JournalDesUnites::class)
            ->call('ouvrirAjustement')
            ->set('compte', 'cible@verifall.com')
            ->set('unites', '25')
            ->set('justification', 'ok')
            ->call('ajuster')
            ->assertHasErrors('justification');
    });

    it('refuse un débit que le solde ne couvre pas, sans planter', function (): void {
        // Un solde insuffisant est une réponse, pas une panne : le message doit
        // donner le solde réel, sinon on retente au hasard.
        $cible = User::factory()->create(['email' => 'cible@verifall.com', 'units' => 3]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(JournalDesUnites::class)
            ->call('ouvrirAjustement')
            ->set('compte', 'cible@verifall.com')
            ->set('sensAjustement', 'debit')
            ->set('unites', '50')
            ->set('justification', 'Régularisation après double crédit.')
            ->call('ajuster')
            ->assertHasErrors('unites');

        expect($cible->refresh()->units)->toBe(3);
    });

    it('plafonne à 10 000 unités', function (): void {
        // Sans borne haute, un zéro de trop crédite cent fois ce qu'on voulait.
        User::factory()->create(['email' => 'cible@verifall.com']);

        Livewire::actingAs($this->admin, 'staff')
            ->test(JournalDesUnites::class)
            ->call('ouvrirAjustement')
            ->set('compte', 'cible@verifall.com')
            ->set('unites', '100000')
            ->set('justification', 'Test de la borne haute du formulaire.')
            ->call('ajuster')
            ->assertHasErrors('unites');
    });

    it('journalise l\'ajustement avec sa justification', function (): void {
        $cible = User::factory()->create(['email' => 'cible@verifall.com', 'units' => 0]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(JournalDesUnites::class)
            ->call('ouvrirAjustement')
            ->set('compte', 'cible@verifall.com')
            ->set('unites', '10')
            ->set('justification', 'Geste commercial après incident du 30 août.')
            ->call('ajuster')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'ai_unit.adjust']);
    });

    it('nomme le compte AVANT que le geste soit posé', function (): void {
        // « paul@ » et « paule@ » se ressemblent, et l'erreur ne se voit
        // qu'après. L'écran affiche le titulaire dès la saisie.
        $cible = User::factory()->create(['name' => 'Paule Ahouandjinou', 'email' => 'paule@verifall.com']);

        Livewire::actingAs($this->admin, 'staff')
            ->test(JournalDesUnites::class)
            ->call('ouvrirAjustement')
            ->set('compte', 'paule@verifall.com')
            ->assertSet('compteTrouve', $cible->uuid)
            ->assertSee('Paule Ahouandjinou');
    });
});

describe('les droits', function (): void {
    it('ouvre la page sur ai_unit.view et le formulaire sur ai_unit.adjust', function (): void {
        /*
         | La matrice sépare les deux verbes ; l'écran s'y tient. Confondre
         | « consulter le journal » et « écrire dedans » donnerait le pouvoir
         | d'ajuster un solde à quiconque peut lire une page.
         |
         | La permission est accordée DIRECTEMENT, sans rôle : aujourd'hui seul
         | `Admin` porte `ai_unit.*`, et il porte les deux verbes. Le test
         | n'attend pas qu'un rôle intermédiaire existe pour vérifier que la
         | séparation tient — c'est le jour où on l'ajoutera qu'elle servira.
         */
        $lecteur = User::factory()->create();
        $lecteur->givePermissionTo('ai_unit.view');

        expect($lecteur->can('ai_unit.view'))->toBeTrue()
            ->and($lecteur->can('ai_unit.adjust'))->toBeFalse();

        $this->actingAs($lecteur, 'staff')->get(route('staff.unit-journal'))->assertOk();

        Livewire::actingAs($lecteur, 'staff')
            ->test(JournalDesUnites::class)
            ->assertSet('peutAjuster', false)
            ->call('ouvrirAjustement')
            ->assertForbidden();
    });

    it('refuse l\'ajustement même appelé directement, sans passer par le bouton', function (): void {
        /*
         | Le bouton disparaît quand le droit manque — mais un composant
         | Livewire s'appelle depuis la console du navigateur. Le garde est
         | DANS la méthode, pas seulement dans la vue.
         */
        $lecteur = User::factory()->create();
        $lecteur->givePermissionTo('ai_unit.view');

        $cible = User::factory()->create(['email' => 'cible@verifall.com', 'units' => 0]);

        Livewire::actingAs($lecteur, 'staff')
            ->test(JournalDesUnites::class)
            ->set('compte', 'cible@verifall.com')
            ->set('unites', '500')
            ->set('justification', 'Tentative de contournement du garde-fou.')
            ->call('ajuster')
            ->assertForbidden();

        expect($cible->refresh()->units)->toBe(0);
    });

    it('refuse la page à un citoyen', function (): void {
        $citoyen = User::factory()->create();
        $citoyen->assignRole('Citoyen');

        $this->actingAs($citoyen, 'staff')
            ->get(route('staff.unit-journal'))->assertForbidden();
    });
});
