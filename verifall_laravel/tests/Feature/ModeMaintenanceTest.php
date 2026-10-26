<?php

declare(strict_types=1);

use App\Core\Application\Services\ModeMaintenance;
use App\Enums\UserRole;
use App\Livewire\Staff\Maintenance;
use App\Mail\CourrielDeGabarit;
use App\Models\MaintenanceWatcher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\GabaritsDEmailSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le mode maintenance
|--------------------------------------------------------------------------
|
| Il vit dans l'application et NON dans `artisan down`, qui couperait aussi le
| back-office : on ne pourrait alors plus rouvrir sans accès SSH, que l'équipe
| éditoriale n'a pas.
|
| **La propriété qui domine tout : on ne peut pas s'enfermer dehors.** Le reste
| — le décompte, le formulaire, le courriel — n'a de valeur que si celle-là
| tient.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(GabaritsDEmailSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);

    $this->maintenance = app(ModeMaintenance::class);
});

// ═══════════════════════════════════════════ on ne s'enferme pas dehors

it('laisse le PERSONNEL entrer alors que le site est fermé', function () {
    /*
     | LA propriété. Sans elle, fermer le site rendrait impossible de le rouvrir
     | depuis l'administration — c'est exactement le défaut de `artisan down`.
     */
    $this->maintenance->activer($this->admin);

    $this->actingAs($this->admin, 'staff')
        ->get(route('staff.maintenance-mode'))
        ->assertOk();
});

it('laisse la CONNEXION du personnel joignable', function () {
    // Corollaire : un membre non encore connecté doit pouvoir entrer, sinon
    // seule une session déjà ouverte permettrait de rouvrir.
    $this->maintenance->activer($this->admin);

    $this->get(route('staff.login'))->assertOk();
});

it('détourne le PUBLIC vers la page de maintenance, en 503', function () {
    /*
     | 503 et non 200 : un moteur d'indexation qui reçoit un 200 conclut que le
     | site a été remplacé par ce texte, et il désindexe.
     */
    $this->maintenance->activer($this->admin);

    $this->get(route('public.home'))
        ->assertStatus(503)
        ->assertSee('La boussole se recalibre');
});

it('laisse la page de maintenance et son formulaire répondre', function () {
    // Sans cette exemption, la redirection tournerait en boucle et le
    // formulaire « prévenez-moi » ne pourrait jamais être posté.
    $this->maintenance->activer($this->admin);

    $this->get(route('maintenance'))->assertOk();

    $this->post(route('maintenance.watch'), ['email' => 'quelquun@example.test'])
        ->assertRedirect(route('maintenance'));
});

it('ne détourne RIEN quand la maintenance est fermée', function () {
    $this->get(route('public.home'))->assertOk();
});

// ═══════════════════════════════════════════════ la fin annoncée

it('annonce un Retry-After quand une heure est donnée', function () {
    // C'est ce que lisent les robots et les sondes de disponibilité. Sans lui,
    // une supervision alerte en boucle.
    $this->maintenance->activer($this->admin, CarbonImmutable::now()->addHour());

    $this->get(route('public.home'))
        ->assertStatus(503)
        ->assertHeader('Retry-After');
});

it('n’en annonce PAS quand aucune heure n’est donnée', function () {
    // Une maintenance sans heure est légitime : on ne sait pas toujours combien
    // de temps ça prendra, et inventer une heure serait pire que se taire.
    $this->maintenance->activer($this->admin);

    $response = $this->get(route('public.home'))->assertStatus(503);

    expect($response->headers->has('Retry-After'))->toBeFalse();
});

it('refuse une heure de fin déjà passée', function () {
    // Elle afficherait « en retard » dès la première seconde : le message
    // serait faux avant d'être lu.
    expect(fn () => $this->maintenance->activer($this->admin, CarbonImmutable::now()->subHour()))
        ->toThrow(RuntimeException::class);
});

it('DIT le retard plutôt que d’afficher un compteur à zéro', function () {
    /*
     | Un compteur figé sur « 00:00 » pendant vingt minutes fait rafraîchir en
     | boucle et donne l'impression d'un site cassé, pas d'une opération en
     | cours.
     */
    $this->maintenance->activer($this->admin, CarbonImmutable::now()->addMinutes(5));
    $this->travel(10)->minutes();

    $this->get(route('public.home'))
        ->assertStatus(503)
        ->assertSee('dure plus longtemps que prévu');
});

it('ne rouvre RIEN toute seule quand l’heure est dépassée', function () {
    /*
     | Une maintenance qui se rouvrirait au chronomètre rouvrirait un site à
     | moitié migré si l'opération a pris dix minutes de plus — précisément le
     | moment où il ne faut pas laisser entrer.
     */
    $this->maintenance->activer($this->admin, CarbonImmutable::now()->addMinutes(5));
    $this->travel(2)->hours();

    expect($this->maintenance->estActif())->toBeTrue();
    $this->get(route('public.home'))->assertStatus(503);
});

// ═══════════════════════════════════════════ le formulaire « prévenez-moi »

it('enregistre une adresse, sans la verser à l’infolettre', function () {
    /*
     | Quelqu'un qui laisse son adresse ici demande UN message sur un sujet
     | précis. L'inscrire à l'infolettre hebdomadaire détournerait son
     | consentement — exactement ce qu'on reproche aux plateformes dont ce
     | produit démonte les rumeurs.
     */
    $this->maintenance->activer($this->admin);

    $this->post(route('maintenance.watch'), ['email' => 'attente@example.test']);

    expect(MaintenanceWatcher::where('email', 'attente@example.test')->exists())->toBeTrue()
        ->and(DB::table('newsletter_subscribers')->where('email', 'attente@example.test')->exists())
        ->toBeFalse();
});

it('ne s’inscrit pas deux fois si l’on resoumet', function () {
    // Rafraîchir et resoumettre est le geste le plus naturel quand on attend.
    $this->maintenance->activer($this->admin);

    $this->post(route('maintenance.watch'), ['email' => 'attente@example.test']);
    $this->post(route('maintenance.watch'), ['email' => 'attente@example.test']);

    expect(MaintenanceWatcher::count())->toBe(1);
});

// ══════════════════════════════════════════════════ la réouverture

it('prévient à la RÉOUVERTURE, pas à l’heure annoncée', function () {
    /*
     | Prévenir à l'heure annoncée enverrait les gens sur un site encore fermé,
     | et le courriel qui promet le retour deviendrait le deuxième mensonge de
     | la journée.
     */
    Mail::fake();

    $this->maintenance->activer($this->admin, CarbonImmutable::now()->addMinutes(5));
    $this->post(route('maintenance.watch'), ['email' => 'attente@example.test']);

    $this->travel(10)->minutes();
    Mail::assertNothingQueued();

    Livewire::actingAs($this->admin, 'staff')
        ->test(Maintenance::class)
        ->call('fermerLaMaintenance');

    Mail::assertQueued(
        CourrielDeGabarit::class,
        fn (CourrielDeGabarit $m): bool => $m->cle === 'maintenance_terminee',
    );
});

it('marque les prévenus, et ne les prévient pas deux fois', function () {
    Mail::fake();

    $this->maintenance->activer($this->admin);
    $this->post(route('maintenance.watch'), ['email' => 'attente@example.test']);

    $ecran = Livewire::actingAs($this->admin, 'staff')->test(Maintenance::class);
    $ecran->call('fermerLaMaintenance');

    expect(MaintenanceWatcher::whereNotNull('notified_at')->count())->toBe(1);

    // Une seconde maintenance ne réécrit pas à quelqu'un déjà prévenu pour la
    // précédente.
    Mail::fake();
    $this->maintenance->activer($this->admin);
    Livewire::actingAs($this->admin, 'staff')->test(Maintenance::class)->call('fermerLaMaintenance');

    Mail::assertNothingQueued();
});

it('n’écrit PAS à quelqu’un inscrit lors d’une maintenance précédente', function () {
    /*
     | Il recevrait « c'est reparti » pour une interruption qu'il a oubliée
     | depuis longtemps. Le filtre porte sur la date d'ouverture de la
     | maintenance en cours.
     */
    Mail::fake();

    MaintenanceWatcher::create([
        'email' => 'vieux@example.test',
        'waiting_since' => now()->subMonths(2),
    ]);

    $this->maintenance->activer($this->admin);
    Livewire::actingAs($this->admin, 'staff')->test(Maintenance::class)->call('fermerLaMaintenance');

    Mail::assertNothingQueued();
});

// ══════════════════════════════════════════════════════ les gardes

it('refuse l’écran à qui n’a pas maintenance.mode', function () {
    /*
     | `maintenance.mode` est la première permission créée depuis quatorze lots.
     | Elle ne se confond avec aucune autre : `backup` sauvegarde la base,
     | `cron` regarde des tâches, `mode` FERME LE SITE.
     */
    $sauvegardeur = User::factory()->actif()->create();
    $sauvegardeur->givePermissionTo('maintenance.backup');

    expect($sauvegardeur->can('maintenance.mode'))->toBeFalse();

    Livewire::actingAs($sauvegardeur, 'staff')
        ->test(Maintenance::class)
        ->assertForbidden();
});

it('journalise l’ouverture ET la fermeture', function () {
    // Fermer un site est le geste le plus visible qu'on puisse faire : il doit
    // pouvoir être attribué.
    $this->maintenance->activer($this->admin);
    $this->maintenance->desactiver($this->admin);

    $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.enable']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.disable']);
});
