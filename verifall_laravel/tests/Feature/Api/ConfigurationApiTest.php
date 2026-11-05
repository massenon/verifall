<?php

declare(strict_types=1);

use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| API v1 — GET /configuration/modules
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->jeton = $this->citoyen->createToken('téléphone')->plainTextToken;
});

it('retourne un objet modules pour tous les types de contenu', function (): void {
    $reponse = $this->withToken($this->jeton)
        ->getJson('/api/v1/configuration/modules');

    $reponse->assertOk();

    $data = $reponse->json('data.modules');
    expect($data)->toBeArray();

    // Chaque TypeContenu doit avoir une entrée booléenne
    foreach (TypeContenu::cases() as $type) {
        expect($data)->toHaveKey($type->value);
        expect($data[$type->value])->toBeBool();
    }
});

it('texte et lien sont toujours actifs', function (): void {
    $reponse = $this->withToken($this->jeton)
        ->getJson('/api/v1/configuration/modules');

    $reponse->assertOk();

    $data = $reponse->json('data.modules');
    expect($data[TypeContenu::Texte->value])->toBeTrue();
    expect($data[TypeContenu::Lien->value])->toBeTrue();
});

it('exige un jeton valide', function (): void {
    $this->getJson('/api/v1/configuration/modules')
        ->assertUnauthorized();
});
