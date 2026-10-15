<?php

declare(strict_types=1);

use App\Core\FactChecking\Notifications\ContreExpertiseTranchee;
use App\Core\FactChecking\Notifications\VerificationAssignee;
use App\Core\FactChecking\Notifications\VerificationRetiree;
use App\Core\FactChecking\Services\AssignationDeVerification;
use App\Enums\StatutContreExpertise;
use App\Enums\UserRole;
use App\Livewire\Staff\TableauDeBord;
use App\Models\AuditLog;
use App\Models\CounterExpertise;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Ce que la colonne « Assigné à » doit vraiment faire
|--------------------------------------------------------------------------
|
| Dans le prototype, elle était décorative : aucune colonne ne portait la prise
| en charge d'une analyse. La rendre réelle demande trois garanties, et chacune
| a son test ci-dessous.
|
|   1. On ne confie pas du travail à quelqu'un qui butera sur un 403.
|   2. Une réassignation prévient AUSSI la personne à qui on retire le dossier.
|   3. Le geste est tracé — c'est une décision éditoriale, pas un réglage.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->responsable = User::factory()->create(['otp_verified_at' => now()]);
    $this->responsable->assignRole(UserRole::Admin->value);

    $this->moderateur = User::factory()->create(['name' => 'Awa Koudjo']);
    $this->moderateur->assignRole(UserRole::Moderateur->value);

    $this->verification = Verification::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'text',
        'status' => 'queued',
        'claim' => 'Une taxe WhatsApp sera instaurée le mois prochain',
        'content_fingerprint' => hash('sha256', 'essai-assignation'),
        'cost_units' => 0,
    ]);
});

it('prévient le destinataire par notification ET par courriel', function () {
    /*
     | Les deux canaux, et pas seulement la cloche : une assignation est un
     | travail confié, et la personne n'a aucune raison d'ouvrir le back-office
     | pour l'apprendre. Sans courriel, un dossier dort pendant que la cloche
     | l'annonce à un onglet fermé.
     */
    Notification::fake();

    app(AssignationDeVerification::class)
        ->confier($this->verification, $this->moderateur, $this->responsable);

    Notification::assertSentTo(
        $this->moderateur,
        VerificationAssignee::class,
        fn (VerificationAssignee $notification): bool => $notification->via($this->moderateur) === ['database', 'mail'],
    );

    expect($this->verification->fresh()->assigned_to)->toBe($this->moderateur->id)
        ->and($this->verification->fresh()->assigned_at)->not->toBeNull();
});

it('REFUSE de confier un dossier à qui ne peut pas rendre de verdict', function () {
    /*
     | Un citoyen recevrait la notification, cliquerait, et tomberait sur un
     | refus d'accès. Le dossier paraîtrait pris en charge sans l'être — pire
     | que de ne rien assigner.
     */
    Notification::fake();

    $citoyen = User::factory()->create();
    $citoyen->assignRole(UserRole::Citoyen->value);

    expect(fn () => app(AssignationDeVerification::class)
        ->confier($this->verification, $citoyen, $this->responsable))
        ->toThrow(RuntimeException::class);

    Notification::assertNothingSent();
    expect($this->verification->fresh()->assigned_to)->toBeNull();
});

it('prévient AUSSI la personne à qui l’on retire le dossier', function () {
    /*
     | Reprendre un dossier sans le dire laisse quelqu'un enquêter sur une
     | analyse qui ne lui appartient plus, et le découvrir en publiant.
     */
    Notification::fake();

    $premier = User::factory()->create(['name' => 'Kossi Adjovi']);
    $premier->assignRole(UserRole::Moderateur->value);

    $service = app(AssignationDeVerification::class);
    $service->confier($this->verification, $premier, $this->responsable);
    $service->confier($this->verification, $this->moderateur, $this->responsable);

    Notification::assertSentTo($premier, VerificationRetiree::class);
    Notification::assertSentTo($this->moderateur, VerificationAssignee::class);

    expect($this->verification->fresh()->assigned_to)->toBe($this->moderateur->id);
});

it('ne renotifie pas quand le dossier est déjà chez la même personne', function () {
    $service = app(AssignationDeVerification::class);
    $service->confier($this->verification, $this->moderateur, $this->responsable);

    // La fausse notification est posée APRÈS la première assignation : seul le
    // second appel est observé.
    Notification::fake();

    $service->confier($this->verification->fresh(), $this->moderateur, $this->responsable);

    Notification::assertNothingSent();
});

it('trace l’assignation au journal d’audit', function () {
    Notification::fake();

    app(AssignationDeVerification::class)
        ->confier($this->verification, $this->moderateur, $this->responsable);

    $trace = AuditLog::query()->where('action', 'verification.assign')->latest('id')->first();

    expect($trace)->not->toBeNull()
        ->and($trace->actor_label)->toBe($this->responsable->name)
        ->and($trace->module)->toBe('factchecking');
});

it('n’expose le sélecteur qu’à qui détient signalement.assign', function () {
    $mentor = User::factory()->create(['otp_verified_at' => now()]);
    $mentor->assignRole(UserRole::Mentor->value);

    // Le mentor enseigne ; il n'arbitre pas les signalements.
    expect($mentor->can('signalement.assign'))->toBeFalse();

    Livewire::actingAs($mentor, 'staff')
        ->test(TableauDeBord::class)
        ->assertViewHas('peutAssigner', false);

    Livewire::actingAs($this->responsable, 'staff')
        ->test(TableauDeBord::class)
        ->assertViewHas('peutAssigner', true);
});

it('refuse une assignation forgée par quelqu’un sans le droit', function () {
    /*
     | La vue cache le sélecteur — mais un appel Livewire se forge sans passer
     | par la vue. Le contrôle d'accès ne peut jamais reposer sur l'affichage.
     */
    Notification::fake();

    $mentor = User::factory()->create(['otp_verified_at' => now()]);
    $mentor->assignRole(UserRole::Mentor->value);

    Livewire::actingAs($mentor, 'staff')
        ->test(TableauDeBord::class)
        ->set('destinataire.'.$this->verification->uuid, $this->moderateur->uuid)
        ->call('assigner', $this->verification->uuid);

    Notification::assertNothingSent();
    expect($this->verification->fresh()->assigned_to)->toBeNull();
});

it('assigne depuis le tableau de bord, en un geste', function () {
    Notification::fake();

    Livewire::actingAs($this->responsable, 'staff')
        ->test(TableauDeBord::class)
        ->set('destinataire.'.$this->verification->uuid, $this->moderateur->uuid)
        ->call('assigner', $this->verification->uuid);

    Notification::assertSentTo($this->moderateur, VerificationAssignee::class);
    expect($this->verification->fresh()->assigned_to)->toBe($this->moderateur->id);
});

it('tranche une contestation depuis le tableau de bord, et prévient le demandeur', function () {
    Notification::fake();

    $citoyen = User::factory()->create(['name' => 'Adjo Sossou']);
    $citoyen->assignRole(UserRole::Citoyen->value);

    $demande = CounterExpertise::create([
        'uuid' => (string) Str::uuid(),
        'verification_id' => $this->verification->id,
        'user_id' => $citoyen->id,
        'reason' => 'Le journal officiel du 12 août dit le contraire, page 4.',
        'status' => StatutContreExpertise::EnAttente->value,
    ]);

    Livewire::actingAs($this->responsable, 'staff')
        ->test(TableauDeBord::class)
        ->set('resolution.'.$demande->uuid, 'Vérifié : vous aviez raison, le rapport est repris.')
        ->call('trancherLaContestation', $demande->uuid, StatutContreExpertise::VerdictCorrige->value);

    $tranchee = $demande->fresh();

    expect($tranchee->status)->toBe(StatutContreExpertise::VerdictCorrige)
        ->and($tranchee->handled_by)->toBe($this->responsable->id)
        ->and($tranchee->resolution)->toBe('Vérifié : vous aviez raison, le rapport est repris.');

    // Le demandeur est prévenu de l'issue — surtout quand elle nous donne tort.
    Notification::assertSentTo($citoyen, ContreExpertiseTranchee::class);
});

it('refuse qu’un mentor tranche une contestation', function () {
    Notification::fake();

    $mentor = User::factory()->create(['otp_verified_at' => now()]);
    $mentor->assignRole(UserRole::Mentor->value);

    $demande = CounterExpertise::create([
        'uuid' => (string) Str::uuid(),
        'verification_id' => $this->verification->id,
        'user_id' => null,
        'reason' => 'Motif quelconque, assez long pour être recevable.',
        'status' => StatutContreExpertise::EnAttente->value,
    ]);

    Livewire::actingAs($mentor, 'staff')
        ->test(TableauDeBord::class)
        ->call('trancherLaContestation', $demande->uuid, StatutContreExpertise::Ecartee->value);

    expect($demande->fresh()->status)->toBe(StatutContreExpertise::EnAttente);
    Notification::assertNothingSent();
});
