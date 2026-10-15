<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| NEWINSTRUCTION29 — le tableau de bord ne doit plus planter sur un rapport
| INSUFFISANT
|--------------------------------------------------------------------------
| Observé en production, à répétition (4 fois en 12 minutes pour le même
| compte) : « Call to a member function badge() on null ». La garde ne
| testait que `estMixte()` avant de déréférencer `verdict` — sans voir que
| `composition_status = Insuffisante` porte, lui aussi, un `verdict` NULL
| (contrat V-8). Voir aussi MesVerificationsTest.php, même correctif.
*/

it('affiche le tableau de bord sans planter quand la dernière vérification est un rapport INSUFFISANT', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $citoyen = User::factory()->actif()->create();
    $citoyen->assignRole(UserRole::Citoyen->value);
    $citoyen->forceFill(['units' => 20])->save();
    Queue::fake();

    $verification = app(SoumissionVerificationService::class)
        ->soumettre($citoyen, TypeContenu::Texte, 'À mon avis, cette politique est une honte.');

    app(OrchestrateurVerification::class)->executer($verification);

    expect($verification->fresh()->report->verdict)->toBeNull();

    // La vue affiche ce libellé en MAJUSCULES (`mb_strtoupper()`).
    $this->actingAs($citoyen)
        ->get(route('citizen.dashboard'))
        ->assertOk()
        ->assertSee(mb_strtoupper($verification->fresh()->report->composition_status->libelle()));
});
