<?php

declare(strict_types=1);

use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Livewire\Citizen\AnalyseEnCours;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| B-03 — savoir qu'une analyse tourne, même après avoir quitté l'écran
|--------------------------------------------------------------------------
| Le suivi existait déjà, et il était honnête : l'écran dédié interroge le
| serveur toutes les trois secondes et montre les étapes réelles du pipeline.
| Mais il n'existait QUE là. Partir lire le forum pendant une attente de
| plusieurs dizaines de secondes — ce qu'on fait naturellement — ne laissait
| plus aucun signe. Une analyse se paie en unités : ne plus savoir si elle
| tourne, c'est ne plus savoir si l'on a payé pour rien.
|
| Ce fichier éprouve surtout ce que le témoin ne fait PAS. Un composant monté
| par le gabarit s'exécute sur les vingt-cinq écrans citoyens ; s'il
| interrogeait la base à chaque affichage, il coûterait une requête par page
| pour n'afficher presque jamais rien. Les tests du milieu tiennent ce contrat.
*/

/** Une vérification appartenant à quelqu'un, dans l'état voulu. */
function verificationDe(User $utilisateur, StatutVerification $statut): Verification
{
    return Verification::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $utilisateur->id,
        'type' => TypeContenu::Texte->value,
        'claim' => 'Une affirmation à vérifier.',
        'status' => $statut->value,
        'cost_units' => 1,
    ]);
}

describe('quand une analyse tourne', function (): void {
    it('affiche le témoin et le lien vers le suivi', function (): void {
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        $verification = verificationDe($citoyen, StatutVerification::EnCours);

        session([AnalyseEnCours::CLE_SESSION => true]);

        Livewire::actingAs($citoyen)
            ->test(AnalyseEnCours::class)
            ->assertSee('Analyse en cours')
            ->assertSee(route('citizen.verification', $verification), escape: false);
    });

    it('compte une reprise programmée comme « en cours »', function (): void {
        /*
         | Du point de vue de la personne, sa demande n'a pas disparu : elle
         | attend. L'exclure éteindrait le témoin au pire moment — celui où
         | l'analyse a justement besoin de plus de temps.
         */
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        verificationDe($citoyen, StatutVerification::EnAttenteDeReprise);

        session([AnalyseEnCours::CLE_SESSION => true]);

        Livewire::actingAs($citoyen)
            ->test(AnalyseEnCours::class)
            ->assertSee('Analyse en cours');
    });
});

describe('le coût sur les pages ordinaires', function (): void {
    it('ne rend rien sans drapeau de session', function (): void {
        /*
         | Le cas de loin le plus fréquent : une page consultée sans analyse en
         | cours. Le témoin ne doit ni s'afficher, ni sonder.
         */
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        verificationDe($citoyen, StatutVerification::EnCours);

        Livewire::actingAs($citoyen)
            ->test(AnalyseEnCours::class)
            ->assertDontSee('Analyse en cours')
            ->assertDontSee('wire:poll', escape: false);
    });

    it('n\'interroge pas la base sans drapeau', function (): void {
        /*
         | Le contrat de performance, éprouvé et non supposé : le drapeau passe
         | AVANT la requête. Sans lui, ce composant coûterait une requête par
         | affichage sur les vingt-cinq écrans citoyens.
         */
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        verificationDe($citoyen, StatutVerification::EnCours);

        $requetes = 0;
        DB::listen(function () use (&$requetes): void {
            $requetes++;
        });

        Livewire::actingAs($citoyen)->test(AnalyseEnCours::class);

        expect($requetes)->toBe(0);
    });
});

describe('l\'extinction du témoin', function (): void {
    it('efface le drapeau quand plus rien ne tourne', function (): void {
        /*
         | Le drapeau n'est pas la vérité — il est posé au lancement et survit à
         | l'analyse. C'est la base qui tranche. Sans cet effacement, chaque
         | page referait la requête jusqu'à la fin de la session, c'est-à-dire
         | exactement le coût que le drapeau existe pour éviter.
         */
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        verificationDe($citoyen, StatutVerification::Terminee);

        session([AnalyseEnCours::CLE_SESSION => true]);

        Livewire::actingAs($citoyen)
            ->test(AnalyseEnCours::class)
            ->assertDontSee('Analyse en cours');

        expect(session()->has(AnalyseEnCours::CLE_SESSION))->toBeFalse();
    });
});

describe('le cloisonnement', function (): void {
    it('ne montre jamais l\'analyse de quelqu\'un d\'autre', function (): void {
        $citoyen = User::factory()->create(['otp_verified_at' => now()]);
        $autre = User::factory()->create(['otp_verified_at' => now()]);

        verificationDe($autre, StatutVerification::EnCours);

        session([AnalyseEnCours::CLE_SESSION => true]);

        Livewire::actingAs($citoyen)
            ->test(AnalyseEnCours::class)
            ->assertDontSee('Analyse en cours');
    });
});
