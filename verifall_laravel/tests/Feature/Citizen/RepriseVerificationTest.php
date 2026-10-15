<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Jobs\AnalyserVerification;
use App\Livewire\Citizen\MesVerifications;
use App\Livewire\Citizen\VerificationEnCours;
use App\Livewire\Public\VerifierInvite;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Reprise — « les moteurs sont occupés, revenez tout à l'heure »
|--------------------------------------------------------------------------
| Un rapport creux est la pire réponse qu'une plateforme de fact-checking
| puisse rendre : la personne repart sans rien, persuadée que le produit ne
| marche pas, alors que le problème est passager et que ses unités sont
| toujours prélevées.
|
| Ce fichier vérifie l'inverse : l'attente est ANNONCÉE, datée, reprise toute
| seule — et, si l'on finit par renoncer, les unités reviennent.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 34])->save();

    // Aucun moteur ne peut conclure : c'est exactement le cas à couvrir.
    config()->set('factcheck.decision.moteurs_minimum', 99);
});

/** Une analyse citoyenne poussée jusqu'à la mise en attente. */
function analyseEnAttente(User $citoyen): Verification
{
    $verification = app(SoumissionVerificationService::class)->soumettre(
        $citoyen, TypeContenu::Texte, 'Une rumeur que personne ne parvient à recouper.',
    );

    return app(OrchestrateurVerification::class)->executer($verification);
}

describe('relance manuelle', function (): void {
    it('refuse tant que le rendez-vous n\'est pas arrivé', function (): void {
        Queue::fake();

        $verification = analyseEnAttente($this->citoyen);

        // Proposer « relancer » à la seconde où l'on vient d'annoncer douze
        // minutes reviendrait à relancer les mêmes moteurs saturés.
        expect($verification->reprisePossible())->toBeFalse()
            ->and(app(OrchestrateurVerification::class)->reprendreMaintenant($verification))->toBeFalse()
            ->and($verification->fresh()->status)->toBe(StatutVerification::EnAttenteDeReprise);
    });

    it('relance une fois le délai écoulé', function (): void {
        Queue::fake();

        $verification = analyseEnAttente($this->citoyen);
        $verification->forceFill(['retry_after' => now()->subMinute()])->save();

        expect(app(OrchestrateurVerification::class)->reprendreMaintenant($verification->fresh()))->toBeTrue();

        $verification->refresh();

        expect($verification->status)->toBe(StatutVerification::EnFile)
            ->and($verification->retry_after)->toBeNull();

        Queue::assertPushed(AnalyserVerification::class);
    });

    it('ne gaspille pas de tentative sur un job périmé', function (): void {
        Queue::fake();

        $verification = analyseEnAttente($this->citoyen);
        $tentatives = $verification->attempts;

        /*
         | Le job programmé pour tout à l'heure ne doit pas repasser MAINTENANT :
         | les moteurs n'ont pas eu le temps de se remettre, et la tentative
         | serait brûlée pour rien.
         */
        expect($verification->repriseProgrammeePlusTard())->toBeTrue();

        (new AnalyserVerification($verification->id))->handle(app(OrchestrateurVerification::class));

        expect($verification->fresh()->attempts)->toBe($tentatives);
    });
});

describe('deux passages à la même seconde', function (): void {
    /*
     | La course n'est pas un cas tordu, c'est le scénario NOMINAL de la
     | reprise : le job différé devient exigible à la minute même où le bouton
     | « Relancer maintenant » apparaît. Les deux passages franchissaient les
     | gardes du job, interrogeaient les moteurs pendant une minute, puis
     | écrivaient le rapport — et le second heurtait la contrainte d'unicité
     | sur `verification_id`. Une 500 en production, après avoir payé les
     | moteurs deux fois.
     */
    it('le second renonce sans appeler un seul moteur', function (): void {
        Queue::fake();

        // Les moteurs peuvent conclure ici : sans cela le second passage
        // partirait en reprise et ne produirait de toute façon pas de rapport,
        // ce qui ferait passer le test pour de mauvaises raisons.
        config()->set('factcheck.decision.moteurs_minimum', 1);

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une rumeur reprise deux fois à la même seconde.',
        );

        // Le second passage a chargé la ligne quand elle était encore EN FILE :
        // c'est exactement ce que voit un worker concurrent.
        $second = Verification::findOrFail($verification->id);

        // Le premier passage la prend en main une fraction de seconde plus tôt.
        Verification::query()
            ->whereKey($verification->id)
            ->update(['status' => StatutVerification::EnCours->value]);

        app(OrchestrateurVerification::class)->executer($second);

        $second->refresh();

        expect($second->report)->toBeNull()
            ->and($second->engineRuns()->count())->toBe(0)
            // La ligne appartient au premier passage : le second n'a rien touché.
            ->and($second->status)->toBe(StatutVerification::EnCours);
    });

    it('une analyse déjà close ne repart pas', function (): void {
        Queue::fake();

        config()->set('factcheck.decision.moteurs_minimum', 1);

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une affirmation déjà tranchée ailleurs.',
        );

        $verification->forceFill(['status' => StatutVerification::Terminee->value])->save();

        app(OrchestrateurVerification::class)->executer($verification);

        expect($verification->fresh()->report)->toBeNull()
            ->and($verification->fresh()->status)->toBe(StatutVerification::Terminee);
    });
});

describe('échec technique', function (): void {
    it('reprogramme au lieu d\'afficher « échec »', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une affirmation qui fait planter le pipeline.',
        );

        (new AnalyserVerification($verification->id))->failed(new RuntimeException('moteur hors service'));

        $verification->refresh();

        expect($verification->status)->toBe(StatutVerification::EnAttenteDeReprise)
            ->and($verification->attempts)->toBe(1)
            ->and($verification->refunded_at)->toBeNull();
    });

    it('rembourse quand il ne reste plus de tentative', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une rumeur qui fait planter le pipeline.',
        );

        $soldeApresDebit = $this->citoyen->fresh()->units;

        $verification->forceFill([
            'attempts' => (int) config('factcheck.runtime.reprise.tentatives_max', 3),
        ])->save();

        (new AnalyserVerification($verification->id))->failed(new RuntimeException('moteur hors service'));

        $verification->refresh();

        /*
         | Un écran « analyse interrompue » sur un compte débité est la pire
         | issue : la personne a payé et repart les mains vides.
         */
        expect($verification->status)->toBe(StatutVerification::Echouee)
            ->and($verification->refunded_at)->not->toBeNull()
            ->and($this->citoyen->fresh()->units)->toBe($soldeApresDebit + $verification->cost_units)
            ->and(UnitLog::where('source_id', $verification->id)->where('delta', $verification->cost_units)->exists())->toBeTrue();
    });

    it('ne touche pas à une analyse déjà close', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une affirmation déjà tranchée.',
        );

        $verification->forceFill(['status' => StatutVerification::Terminee->value])->save();

        (new AnalyserVerification($verification->id))->failed(new RuntimeException('rappel tardif'));

        expect($verification->fresh()->status)->toBe(StatutVerification::Terminee)
            ->and($verification->fresh()->refunded_at)->toBeNull();
    });
});

describe('ce que voit la personne', function (): void {
    it('annonce l\'attente et son délai, jamais un échec', function (): void {
        Queue::fake();

        $verification = analyseEnAttente($this->citoyen);

        Livewire::actingAs($this->citoyen)
            ->test(VerificationEnCours::class, ['verification' => $verification->uuid])
            ->assertSee('Analyse mise en attente')
            ->assertSee('la reprise est automatique')
            // Une seule unite : l'accord suit, sinon on lit « vos 1 unite ».
            ->assertSee('vous sera rendue')
            ->assertDontSee('indisponible')
            ->assertDontSee('Échec')
            // Le bouton n'apparaît qu'une fois le rendez-vous passé.
            ->assertDontSee('Relancer maintenant');
    });

    it('propose la relance une fois le délai écoulé', function (): void {
        Queue::fake();

        $verification = analyseEnAttente($this->citoyen);
        $verification->forceFill(['retry_after' => now()->subMinute()])->save();

        Livewire::actingAs($this->citoyen)
            ->test(VerificationEnCours::class, ['verification' => $verification->uuid])
            ->assertSee('Relancer maintenant')
            ->call('relancer')
            ->assertHasNoErrors();

        expect($verification->fresh()->status)->toBe(StatutVerification::EnFile);
    });
});

describe('visiteur sans compte', function (): void {
    it('retrouve son analyse en revenant sur la page', function (): void {
        Queue::fake();

        $composant = Livewire::test(VerifierInvite::class)
            ->set('onglet', 'texte')
            ->set('texte', 'Un message reçu sur WhatsApp ce matin, impossible à recouper.')
            ->call('lancer');

        $uuid = $composant->get('uuid');

        expect($uuid)->not->toBeNull();

        app(OrchestrateurVerification::class)->executer(
            Verification::where('uuid', $uuid)->firstOrFail(),
        );

        /*
         | « Revenez dans dix minutes » est une promesse creuse si le visiteur
         | retombe sur un formulaire vide. La demande est retenue en session,
         | et non sur l'empreinte de l'adresse : derrière un même NAT, deux
         | visiteurs ne doivent pas voir la demande l'un de l'autre.
         */
        Livewire::test(VerifierInvite::class)
            ->assertSet('uuid', $uuid)
            ->assertSee('Analyse mise en attente')
            ->assertSee('Revenez sur cette page');
    });

    it('repart d\'un formulaire propre quand l\'analyse a disparu', function (): void {
        session()->put('verif-invite.analyse', 'un-uuid-qui-ne-correspond-a-rien');

        Livewire::test(VerifierInvite::class)->assertSet('uuid', null);

        expect(session()->has('verif-invite.analyse'))->toBeFalse();
    });
});

describe("l'etat de reprise ne casse rien ailleurs", function (): void {
    /*
     | Un `match` sans branche par defaut est un piege : ajouter un cas a l'enum
     | fait planter toutes les pages qui l'affichent, en silence jusqu'a
     | l'execution. C'est exactement ce qui est arrive a « Mes verifications ».
     */
    it('donne un libelle et une pastille a chaque etat', function (StatutVerification $statut): void {
        expect($statut->libelle())->not->toBeEmpty()
            ->and($statut->badge())->toStartWith('vbadge');
    })->with(StatutVerification::cases());

    it('compte la reprise parmi les analyses en cours', function (): void {
        // Une demande en attente de reprise n'a pas disparu : l'exclure du
        // compte « en cours » la ferait passer pour perdue.
        expect(StatutVerification::valeursEnCours())
            ->toContain(StatutVerification::EnAttenteDeReprise->value)
            ->toContain(StatutVerification::EnFile->value)
            ->toContain(StatutVerification::EnCours->value)
            ->not->toContain(StatutVerification::Terminee->value);
    });

    it("affiche l'attente dans l'historique", function (): void {
        Queue::fake();

        analyseEnAttente($this->citoyen);

        Livewire::actingAs($this->citoyen)
            ->test(MesVerifications::class)
            ->assertOk()
            ->assertSee('Reprise programmée');
    });
});
