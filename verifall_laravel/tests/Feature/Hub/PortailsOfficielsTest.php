<?php

declare(strict_types=1);

use App\Core\Application\Reglages\Reglages;
use App\Enums\UserRole;
use App\Livewire\Staff\PortailsOfficiels as EcranAdministration;
use App\Models\AuditLog;
use App\Models\HubPortal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le dernier écran du prototype sans route
|--------------------------------------------------------------------------
|
| `hub_portals` existait depuis les premiers lots, avec cinq lignes semées.
| **Rien ne les lisait** : ni CRUD, ni écran citoyen, ni API. Une table qu'on
| écrit et que personne ne lit — le chantier a déjà refusé cela deux fois.
|
| Le porteur du projet a demandé une chose précise : le CRUD MAINTENANT,
| l'exposition citoyenne PLUS TARD. L'entrée figure dans le menu du compte,
| rendue désactivée, et une case l'ouvre. Ces tests gardent surtout la moitié
| qu'on oublie : **un lien désactivé dont l'adresse répond quand même n'est
| désactivé que pour la forme.**
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['otp_verified_at' => now()]);
    $this->admin->assignRole(UserRole::Admin->value);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);

    $this->portail = HubPortal::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Portail des services publics du Bénin',
        'url' => 'https://service-public.bj',
        'category' => 'Institutions',
        'description' => 'Les démarches administratives en ligne.',
        'is_official' => true,
        'position' => 10,
        'is_published' => true,
    ]);
});

it('rend 404 sur la rubrique citoyenne tant qu’elle est fermée', function () {
    /*
     | Le point le plus important du lot. Un lien désactivé dans le menu ne
     | protège rien : l'adresse se devine, se copie, se retrouve dans un
     | historique. C'est la ROUTE qui doit refuser.
     |
     | 404 et non 403 : une rubrique pas encore ouverte n'existe pas pour le
     | public. 403 dirait « elle existe et vous n'y avez pas droit », ce qui
     | est faux et invite à insister.
     */
    expect(app(Reglages::class)->actif(EcranAdministration::CLE_ACTIVATION))->toBeFalse();

    $this->actingAs($this->citoyen)
        ->get(route('citizen.portals'))
        ->assertNotFound();
});

it('bascule le réglage depuis l’administration, dans les deux sens', function () {
    $ecran = Livewire::actingAs($this->admin, 'staff')->test(EcranAdministration::class);

    $ecran->call('basculerLAccesCitoyen');
    expect(app(Reglages::class)->actif(EcranAdministration::CLE_ACTIVATION))->toBeTrue();

    // Refermable : la rubrique peut se retirer si le contenu se révèle mauvais.
    $ecran->call('basculerLAccesCitoyen');
    expect(app(Reglages::class)->actif(EcranAdministration::CLE_ACTIVATION))->toBeFalse();
});

it('sert la rubrique une fois le réglage ouvert', function () {
    /*
     | Test SÉPARÉ du précédent, et non par commodité : enchaîner une session
     | du personnel puis une session citoyenne dans le même test fait passer la
     | seconde requête par la redirection du guard staff, et le 302 obtenu ne
     | dit alors rien du produit. Deux sessions, deux tests.
     */
    app(Reglages::class)->set(EcranAdministration::CLE_ACTIVATION, '1');

    $this->actingAs($this->citoyen)
        ->get(route('citizen.portals'))
        ->assertOk()
        ->assertSee('Portail des services publics du Bénin')
        ->assertSee('Institutions')
        // Le seul signal qui compte sur cette page.
        ->assertSee('Officiel');
});

it('montre l’entrée DÉSACTIVÉE dans le menu du compte, puis cliquable', function () {
    // Fermée : présente, sans `href`, et marquée comme telle.
    $this->actingAs($this->citoyen)
        ->get(route('citizen.profile'))
        ->assertOk()
        ->assertSee('user-menu-link is-a-venir', escape: false)
        ->assertDontSee(route('citizen.portals'), escape: false);

    app(Reglages::class)->set(EcranAdministration::CLE_ACTIVATION, '1');

    // Ouverte : le lien apparaît.
    $this->actingAs($this->citoyen)
        ->get(route('citizen.profile'))
        ->assertOk()
        ->assertSee(route('citizen.portals'), escape: false);
});

it('ne montre pas un portail dépublié au public', function () {
    app(Reglages::class)->set(EcranAdministration::CLE_ACTIVATION, '1');

    $this->portail->forceFill(['is_published' => false])->save();

    $this->actingAs($this->citoyen)
        ->get(route('citizen.portals'))
        ->assertOk()
        ->assertDontSee('Portail des services publics du Bénin');
});

it('EXIGE https sur l’adresse d’un portail officiel', function () {
    /*
     | Un portail officiel dont l'adresse part en `http://` renverrait les gens
     | vers une page que leur navigateur signale comme non sécurisée — sur une
     | plateforme qui enseigne à se méfier, ce serait le pire des exemples.
     */
    Livewire::actingAs($this->admin, 'staff')
        ->test(EcranAdministration::class)
        ->call('ouvrirCreation')
        ->set('nom', 'Un site')
        ->set('lien', 'http://exemple.bj')
        ->set('categorie', 'Institutions')
        ->set('descriptif', 'Un descriptif suffisant.')
        ->set('ordre', '20')
        ->call('enregistrer')
        ->assertHasErrors(['lien']);

    expect(HubPortal::query()->count())->toBe(1);
});

it('crée, modifie et supprime un portail', function () {
    $composant = Livewire::actingAs($this->admin, 'staff')->test(EcranAdministration::class);

    $composant->call('ouvrirCreation')
        ->set('nom', 'ANIP')
        ->set('lien', 'https://anip.bj')
        ->set('categorie', 'Démarches')
        ->set('descriptif', 'Actes de naissance et identité.')
        ->set('ordre', '20')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $cree = HubPortal::query()->where('name', 'ANIP')->firstOrFail();
    expect($cree->is_published)->toBeTrue();

    $composant->call('ouvrirEdition', $cree->uuid)
        ->set('nom', 'ANIP — état civil')
        ->call('enregistrer');

    expect($cree->fresh()->name)->toBe('ANIP — état civil');

    $composant->call('demanderLaSuppression', $cree->uuid)->call('supprimer');

    expect(HubPortal::query()->where('name', 'ANIP — état civil')->exists())->toBeFalse();
});

it('refuse la gestion à qui ne détient pas hub_portal.manage', function () {
    // Le modérateur, et non plus le mentor : depuis l'arbitrage n° 177, le
    // mentor a le CRUD du Hub. Le modérateur n'a pas même la vue des portails.
    $moderateur = User::factory()->create(['otp_verified_at' => now()]);
    $moderateur->assignRole(UserRole::Moderateur->value);

    expect($moderateur->can('hub_portal.view'))->toBeFalse();

    $this->actingAs($moderateur, 'staff')
        ->get(route('staff.hub-portals'))
        ->assertForbidden();
});

it('ouvre la gestion des portails au mentor (arbitrage n° 177)', function () {
    $mentor = User::factory()->create(['otp_verified_at' => now()]);
    $mentor->assignRole(UserRole::Mentor->value);

    expect($mentor->can('hub_portal.manage'))->toBeTrue();

    $this->actingAs($mentor, 'staff')
        ->get(route('staff.hub-portals'))
        ->assertOk();
});

it('journalise l’ouverture de la rubrique', function () {
    // Ouvrir une rubrique publique est une décision éditoriale, pas un réglage
    // technique : elle laisse une trace.
    Livewire::actingAs($this->admin, 'staff')
        ->test(EcranAdministration::class)
        ->call('basculerLAccesCitoyen');

    expect(AuditLog::query()->where('action', 'hub_portal.acces_ouvert')->exists())->toBeTrue();
});
