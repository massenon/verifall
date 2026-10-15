<?php

declare(strict_types=1);

use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\SignalementDeContenu;
use App\Enums\StatutSignalementContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Citizen\Sujet;
use App\Models\ContentReport;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Forum — signalement et modération
|--------------------------------------------------------------------------
| Deux dérives à empêcher : qu'un groupe organisé fasse disparaître un contenu
| en le signalant en masse, et qu'un citoyen ordinaire puisse apposer le verdict
| officiel de la plateforme. Le premier serait un bouton de censure, le second
| une usurpation.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);

    $this->moderateur = User::factory()->actif()->create();
    $this->moderateur->assignRole(UserRole::Moderateur->value);

    $this->sujet = app(AnimationDuForum::class)->publier(
        $this->citoyen,
        'Une rumeur circule depuis ce matin sur WhatsApp',
        'Elle annonce une mesure gouvernementale que rien ne confirme pour l\'instant.',
    );
});

describe('signalement par un citoyen', function (): void {
    it('enregistre un signalement en attente', function (): void {
        Livewire::actingAs($this->citoyen)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->call('ouvrirSignalement')
            ->set('motifSignalement', 'desinformation')
            ->set('detailSignalement', 'Aucun communiqué officiel ne le confirme.')
            ->call('signaler')
            ->assertHasNoErrors();

        $signalement = ContentReport::first();

        expect($signalement)->not->toBeNull()
            ->and($signalement->status)->toBe(StatutSignalementContenu::EnAttente)
            ->and($signalement->reason)->toBe('desinformation');
    });

    it('exige un motif', function (): void {
        Livewire::actingAs($this->citoyen)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->call('ouvrirSignalement')
            ->call('signaler')
            ->assertHasErrors('motifSignalement');

        expect(ContentReport::count())->toBe(0);
    });

    it('ne compte qu\'un signalement par personne et par contenu', function (): void {
        $service = app(SignalementDeContenu::class);

        $service->signaler($this->citoyen, $this->sujet, 'spam');
        $service->signaler($this->citoyen, $this->sujet, 'haine');

        // Empiler dix lignes du même signaleur fausserait la file de
        // modération.
        expect(ContentReport::count())->toBe(1)
            ->and(ContentReport::first()->reason)->toBe('spam');
    });

    it('ramène un motif inconnu sur « autre »', function (): void {
        // Le motif vient d'un formulaire : rien n'empêche d'y coller
        // n'importe quoi.
        app(SignalementDeContenu::class)->signaler($this->citoyen, $this->sujet, 'motif-forge');

        expect(ContentReport::first()->reason)->toBe('autre');
    });

    it('ne retire jamais le contenu automatiquement', function (): void {
        foreach (range(1, 8) as $ignore) {
            $autre = User::factory()->actif()->create();
            app(SignalementDeContenu::class)->signaler($autre, $this->sujet->fresh(), 'haine');
        }

        // Masquer au-delà d'un seuil offrirait à n'importe quel groupe organisé
        // un bouton de censure.
        expect(Thread::whereKey($this->sujet->id)->exists())->toBeTrue()
            ->and($this->sujet->fresh()->is_locked)->toBeFalse()
            ->and(ContentReport::where('status', StatutSignalementContenu::EnAttente->value)->count())->toBe(8);
    });

    it('signale aussi une réponse, pas seulement le sujet', function (): void {
        $reponse = app(AnimationDuForum::class)->repondre($this->citoyen, $this->sujet, 'Un message déplacé.');

        Livewire::actingAs($this->citoyen)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->call('ouvrirSignalement', $reponse->uuid)
            ->set('motifSignalement', 'haine')
            ->call('signaler');

        expect(ContentReport::first()->reportable_type)->toBe((new Reply)->getMorphClass());
    });
});

describe('modération', function (): void {
    it('cache les actions à qui n\'a pas le droit', function (): void {
        Livewire::actingAs($this->citoyen)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->assertSet('slug', $this->sujet->slug)
            ->assertDontSee('Transmettre au laboratoire')
            ->assertDontSee('Publier le verdict');
    });

    it('refuse l\'action même appelée directement', function (): void {
        // L'absence de bouton n'est pas une protection : la méthode est
        // joignable depuis la console du navigateur.
        Livewire::actingAs($this->citoyen)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->call('basculerVerrou')
            ->assertForbidden();

        expect($this->sujet->fresh()->is_locked)->toBeFalse();
    });

    it('laisse un modérateur clore et rouvrir un fil', function (): void {
        $composant = Livewire::actingAs($this->moderateur)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->assertSee('Clore le fil');

        $composant->call('basculerVerrou');
        expect($this->sujet->fresh()->is_locked)->toBeTrue();

        $composant->call('basculerVerrou');
        expect($this->sujet->fresh()->is_locked)->toBeFalse();
    });

    it('transmet au laboratoire depuis le fil', function (): void {
        Queue::fake();

        Livewire::actingAs($this->moderateur)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->call('transmettreAuLabo');

        expect($this->sujet->fresh()->verification_id)->not->toBeNull();
    });

    it('appose un verdict officiel sans toucher au sondage', function (): void {
        $composant = Livewire::actingAs($this->moderateur)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->set('verdictChoisi', VerdictFactCheck::Faux->value)
            ->call('apposerLeVerdict');

        $sujet = $this->sujet->fresh();

        expect($sujet->official_verdict)->toBe(VerdictFactCheck::Faux)
            ->and($sujet->verified_by)->toBe($this->moderateur->id)
            // L'avis de la communauté reste ce qu'il était : le verdict ne s'en
            // déduit pas et ne l'écrase pas.
            ->and($sujet->totalDesVotes())->toBe(0);

        $composant->assertSee('CONFIRME');
    });

    it('ne publie rien tant qu\'aucun verdict n\'est choisi', function (): void {
        Livewire::actingAs($this->moderateur)
            ->test(Sujet::class, ['thread' => $this->sujet->slug])
            ->set('verdictChoisi', '')
            ->call('apposerLeVerdict');

        expect($this->sujet->fresh()->official_verdict)->toBeNull();
    });
});
