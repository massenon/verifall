<?php

declare(strict_types=1);

use App\Core\Marketing\Services\Parrainage;
use App\Enums\StatutParrainage;
use App\Livewire\Citizen\MonParrainage;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le parrainage vu par le FILLEUL
|--------------------------------------------------------------------------
| Ce versant manquait entièrement. L'écran « Mon Parrainage » ne montrait que
| le côté parrain : quelqu'un arrivé par le lien d'un ami n'y trouvait aucune
| trace de ce qui lui avait été promis, ni de ce qui lui restait à faire pour
| l'obtenir.
|
| C'est pourtant le côté qui compte le plus au départ, parce que c'est celui
| qui a encore un geste à faire. Le parrain, lui, n'a plus qu'à attendre.
*/

beforeEach(function (): void {
    $this->parrain = User::factory()->create(['name' => 'Awa Sossou', 'otp_verified_at' => now()]);
    $this->filleul = User::factory()->create(['otp_verified_at' => now()]);
});

function lienDeParrainage(User $parrain, User $filleul, array $extra = []): Referral
{
    return Referral::query()->create(array_merge([
        // La table exige un uuid : c'est le trait maison, pas `HasUuids`, qui
        // le pose ailleurs dans le code.
        'uuid' => (string) Str::uuid(),
        'referrer_id' => $parrain->id,
        'referred_id' => $filleul->id,
        'status' => StatutParrainage::Inscrit->value,
        'registered_at' => now(),
    ], $extra));
}

it("ne montre RIEN à qui n'a pas été parrainé", function (): void {
    /*
     | Un encart vide se lirait comme une panne. Ne rien afficher est
     | l'information exacte : cette personne n'a pas de parrain.
     */
    Livewire::actingAs($this->filleul)
        ->test(MonParrainage::class)
        ->assertDontSee('Vous avez été parrainé par');
});

it('nomme le parrain, et dit ce qui reste à faire', function (): void {
    lienDeParrainage($this->parrain, $this->filleul);

    Livewire::actingAs($this->filleul)
        ->test(MonParrainage::class)
        ->assertSee('Vous avez été parrainé par')
        ->assertSee('Awa Sossou')
        // La phrase s'adresse au FILLEUL, pas au parrain : c'est lui qui a
        // encore une action à mener.
        ->assertSee('Lancez votre première vérification');
});

it("propose le geste qui débloque, tant qu'il n'est pas fait", function (): void {
    lienDeParrainage($this->parrain, $this->filleul);

    Livewire::actingAs($this->filleul)
        ->test(MonParrainage::class)
        ->assertSee(route('citizen.verify'), escape: false);
});

it('annonce les unités VERSÉES une fois la récompense honorée', function (): void {
    /*
     | Le montant est lu sur le parrainage, pas sur la constante du service :
     | si le barème change un jour, cet écran doit continuer de dire ce qui a
     | réellement été crédité, et non ce qui le serait aujourd'hui.
     */
    lienDeParrainage($this->parrain, $this->filleul, [
        'status' => StatutParrainage::Recompense->value,
        'qualified_at' => now(),
        'rewarded_at' => now(),
        // Valeur arbitraire : ce test porte sur ce que voit le FILLEUL.
        'referrer_points' => 123,
        'referred_points' => 77,
    ]);

    Livewire::actingAs($this->filleul)
        ->test(MonParrainage::class)
        ->assertSee('77 unités reçues')
        // Le bouton disparaît : il n'y a plus rien à débloquer.
        ->assertDontSee('Lancer ma première vérification');
});

it('tient quand le parrain a fermé son compte', function (): void {
    /*
     | Le service prévoit ce cas : le filleul garde son avantage, il n'y est
     | pour rien. L'écran ne doit pas se casser pour autant, ni afficher un
     | nom vide.
     */
    $lien = lienDeParrainage($this->parrain, $this->filleul);
    $this->parrain->delete();

    expect($lien->refresh()->referrer)->toBeNull();

    Livewire::actingAs($this->filleul)
        ->test(MonParrainage::class)
        ->assertOk()
        ->assertSee('un membre');
});

it('dit au PARRAIN autre chose qu’au filleul, pour le même état', function (): void {
    /*
     | Les deux phrases décrivent le même état et ne disent pas la même chose :
     | le parrain attend, le filleul agit. Une seule phrase pour les deux aurait
     | laissé l'un devant un texte qui ne le concerne pas.
     */
    $inscrit = StatutParrainage::Inscrit;

    expect($inscrit->explication())->toContain('Votre filleul')
        ->and($inscrit->explicationFilleul())->toContain('Lancez votre première vérification')
        ->and($inscrit->explication())->not->toBe($inscrit->explicationFilleul());
});
