<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\FileDesSignalements;
use App\Enums\StatutVerification;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Staff\Signalements;
use App\Models\AnalysisReport;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedFact;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| La file des vérifications citoyennes
|--------------------------------------------------------------------------
|
| Le dernier écran que la matrice promettait sans le tenir. `signalement.publish`
| et `signalement.delete` étaient déclarées depuis l'origine et employées nulle
| part : aucune page ne listait les vérifications.
|
| Deux propriétés dominent :
|
|   · **verser n'est pas publier** — l'entrée rejoint la base de connaissances en
|     BROUILLON, sinon un verdict automatique deviendrait une référence
|     opposable ;
|   · **retirer est réversible et motivé** — la vérification appartient à
|     quelqu'un et lui a coûté une unité.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);

    $this->citoyen = User::factory()->actif()->create(['name' => 'Aïcha Dossou']);
    $this->citoyen->assignRole(UserRole::Citoyen->value);
});

/** Une vérification soumise, avec ou sans verdict. */
function verificationSoumise(User $auteur, ?VerdictFactCheck $verdict = null, string $affirmation = 'Ce remède soigne-t-il le paludisme ?'): Verification
{
    $verification = Verification::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $auteur->id,
        'type' => 'text',
        'status' => $verdict !== null
            ? StatutVerification::Terminee->value
            : StatutVerification::EnFile->value,
        'claim' => $affirmation,
        'cost_units' => 1,
        'content_fingerprint' => hash('sha256', $affirmation),
    ]);

    if ($verdict !== null) {
        AnalysisReport::create([
            'uuid' => (string) Str::uuid(),
            'verification_id' => $verification->id,
            'verdict' => $verdict->value,
            'confidence' => 88,
            'summary' => 'Le message circule depuis lundi. Aucune source ne le soutient.',
        ]);
    }

    return $verification->fresh();
}

// ════════════════════════════════════════════════════════════════ la file

it('range les plus ANCIENNES en tête', function () {
    /*
     | À l'inverse des autres listes du back-office. Cet écran sert à VIDER une
     | file : trier par date décroissante enterrerait ce qui attend depuis le
     | plus longtemps, c'est-à-dire ce dont quelqu'un attend encore la réponse.
     */
    $ancienne = verificationSoumise($this->citoyen, null, 'La plus ancienne');
    $ancienne->forceFill(['created_at' => now()->subWeek()])->save();
    verificationSoumise($this->citoyen, null, 'La plus récente');

    $file = app(FileDesSignalements::class)->file($this->admin);

    expect($file->first()->claim)->toBe('La plus ancienne');
});

it('compte les NON ASSIGNÉES à part', function () {
    // Le chiffre qui commande : une vérification que personne n'a prise
    // n'avance pas, quel que soit son statut technique.
    verificationSoumise($this->citoyen);
    verificationSoumise($this->citoyen, null, 'Prise en charge')
        ->forceFill(['assigned_to' => $this->admin->id])->save();

    expect(app(FileDesSignalements::class)->indicateurs()['non_assignees'])->toBe(1);
});

// ═══════════════════════════════════════════════════════════════ verser

it('verse une vérification tranchée en BROUILLON', function () {
    /*
     | Verser n'est pas publier. Sauter l'étape de relecture ferait d'un verdict
     | automatique une référence opposable — ce que la plateforme refuse partout
     | ailleurs.
     */
    $verification = verificationSoumise($this->citoyen, VerdictFactCheck::Faux);

    $fait = app(FileDesSignalements::class)->publier($verification, $this->admin);

    expect($fait->is_published)->toBeFalse()
        ->and($fait->title)->toContain('paludisme')
        // L'empreinte est le cœur du versement : c'est elle que les analyses
        // suivantes consultent avant d'appeler un moteur.
        ->and($fait->content_fingerprint)->toBe($verification->content_fingerprint);

    $this->assertDatabaseHas('audit_logs', ['action' => 'verification.publish']);
});

it('refuse de verser une vérification SANS verdict', function () {
    // Il n'y a rien à verser : l'entrée serait un titre sans réponse, et le
    // fonds servirait cette absence aux analyses suivantes.
    $verification = verificationSoumise($this->citoyen);

    expect(fn () => app(FileDesSignalements::class)->publier($verification, $this->admin))
        ->toThrow(RuntimeException::class);

    expect(VerifiedFact::count())->toBe(0);
});

it('refuse de verser DEUX FOIS le même contenu', function () {
    /*
     | Le contrôle porte sur l'EMPREINTE, pas sur l'identifiant : deux personnes
     | soumettant le même message produisent deux vérifications, et le fonds n'a
     | besoin que d'une entrée.
     */
    $service = app(FileDesSignalements::class);
    $service->publier(verificationSoumise($this->citoyen, VerdictFactCheck::Faux), $this->admin);

    $autreCitoyen = User::factory()->actif()->create();
    $doublon = verificationSoumise($autreCitoyen, VerdictFactCheck::Faux);

    expect(fn () => $service->publier($doublon, $this->admin))->toThrow(RuntimeException::class);
    expect(VerifiedFact::count())->toBe(1);
});

// ══════════════════════════════════════════════════════════════ retirer

it('retire en DOUCEUR, et garde la trace', function () {
    /*
     | Une vérification a été demandée par quelqu'un et lui a coûté une unité.
     | L'effacer franchement retirerait une ligne de son historique sans qu'il
     | puisse le constater autrement que par une absence.
     */
    $verification = verificationSoumise($this->citoyen);

    app(FileDesSignalements::class)->supprimer(
        $verification,
        $this->admin,
        'Contenu injurieux, sans affirmation vérifiable.',
    );

    expect(Verification::query()->count())->toBe(0)
        ->and(Verification::withTrashed()->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', ['action' => 'verification.delete']);
});

it('refuse un retrait sans motif', function () {
    // Un retrait sans raison écrite n'est pas contestable.
    $verification = verificationSoumise($this->citoyen);

    expect(fn () => app(FileDesSignalements::class)->supprimer($verification, $this->admin, '  '))
        ->toThrow(RuntimeException::class);

    expect(Verification::query()->count())->toBe(1);
});

// ════════════════════════════════════════════════════════════════ l'écran

it('affiche la file à qui détient signalement.view', function () {
    verificationSoumise($this->citoyen);

    Livewire::actingAs($this->admin, 'staff')
        ->test(Signalements::class)
        ->assertOk()
        ->assertSee('paludisme')
        ->assertSee('Aïcha Dossou');
});

it('la refuse à un mentor', function () {
    // `signalement.view` n'appartient qu'à l'Admin et au Modérateur : la file
    // porte l'affirmation soumise et l'identité de qui l'a soumise.
    $mentor = User::factory()->actif()->create();
    $mentor->assignRole(UserRole::Mentor->value);

    expect($mentor->can('signalement.view'))->toBeFalse();

    Livewire::actingAs($mentor, 'staff')
        ->test(Signalements::class)
        ->assertForbidden();
});

it('exige un motif d’au moins dix caractères pour retirer', function () {
    $verification = verificationSoumise($this->citoyen);

    Livewire::actingAs($this->admin, 'staff')
        ->test(Signalements::class)
        ->call('ouvrirLeRetrait', $verification->uuid)
        ->set('motifDeRetrait', 'spam')
        ->call('retirer')
        ->assertHasErrors('motifDeRetrait');

    expect(Verification::query()->count())->toBe(1);
});

it('verse depuis l’écran, et le dit sans laisser croire à une publication', function () {
    $verification = verificationSoumise($this->citoyen, VerdictFactCheck::Faux);

    Livewire::actingAs($this->admin, 'staff')
        ->test(Signalements::class)
        ->call('publier', $verification->uuid)
        ->assertHasNoErrors();

    expect(VerifiedFact::query()->where('is_published', false)->count())->toBe(1);
});
