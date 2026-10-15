<?php

declare(strict_types=1);

use App\Core\FactChecking\Notifications\ContreExpertiseDemandee;
use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\RetoursSurLeRapport;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\StatutContreExpertise;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Livewire\Citizen\Rapport;
use App\Models\CounterExpertise;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerificationFeedback;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Les boutons de l'aside qui n'écrivaient nulle part
|--------------------------------------------------------------------------
|
| Le prototype (`compte-user/rapport.html`) dessine six cartes à droite du
| rapport. Trois portaient des boutons reliés à RIEN :
|
|   · « Demander un expert » ouvrait une fenêtre dont le formulaire n'allait
|     nulle part ;
|   · « 👍 / 👎 » n'avait aucun stockage — `API_SPECIFICATION.md` §11 déclarait
|     d'ailleurs `POST /verifications/{uuid}/feedback` absent pour cette raison ;
|   · « WhatsApp / Partager le lien » n'avait pas d'URL.
|
| Un bouton qui ne fait rien est pire qu'un bouton absent : il consomme la
| bonne volonté de quelqu'un qui voulait aider.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 20])->save();

    $this->verification = app(SoumissionVerificationService::class)->soumettre(
        $this->citoyen,
        TypeContenu::Texte,
        'La taxe sur les transferts double lundi prochain.',
    );

    app(OrchestrateurVerification::class)->executer($this->verification);
    $this->verification = $this->verification->fresh();
});

/** Un motif assez long pour passer la validation de l'écran. */
function unMotif(): string
{
    return 'Le journal officiel du 12 août dit exactement le contraire, page 4.';
}

it('enregistre l’avis d’utilité, et le CHANGE au lieu de l’additionner', function () {
    /*
     | Sans l'unicité sur le couple, un pouce cliqué douze fois compterait douze
     | avis, et l'indicateur mesurerait l'agacement plutôt que la satisfaction.
     */
    $composant = Livewire::actingAs($this->citoyen)
        ->test(Rapport::class, ['verification' => $this->verification->uuid]);

    $composant->call('noter', true);
    $composant->call('noter', true);
    $composant->call('noter', false);

    expect(VerificationFeedback::query()->count())->toBe(1)
        ->and(VerificationFeedback::query()->first()->useful)->toBeFalse();
});

it('garde le pouce choisi visible au rechargement', function () {
    // Un vote qui disparaît donne l'impression de ne pas avoir été pris,
    // et on reclique.
    app(RetoursSurLeRapport::class)->noter($this->verification, $this->citoyen, true);

    Livewire::actingAs($this->citoyen)
        ->test(Rapport::class, ['verification' => $this->verification->uuid])
        ->assertSeeHtml('aria-pressed="true"');
});

it('ouvre une contre-expertise et prévient les arbitres', function () {
    Notification::fake();

    $arbitre = User::factory()->create();
    $arbitre->assignRole(UserRole::Moderateur->value);

    Livewire::actingAs($this->citoyen)
        ->test(Rapport::class, ['verification' => $this->verification->uuid])
        ->call('ouvrirLaContestation')
        ->assertSet('tiroirContestation', true)
        ->set('motifContestation', unMotif())
        ->call('demanderUnExpert')
        ->assertSet('tiroirContestation', false)
        ->assertHasNoErrors();

    $demande = CounterExpertise::query()->first();

    expect($demande)->not->toBeNull()
        ->and($demande->status)->toBe(StatutContreExpertise::EnAttente)
        ->and($demande->verification_id)->toBe($this->verification->id);

    Notification::assertSentTo($arbitre, ContreExpertiseDemandee::class);
});

it('REFUSE une contestation sans motif exploitable', function () {
    Notification::fake();

    Livewire::actingAs($this->citoyen)
        ->test(Rapport::class, ['verification' => $this->verification->uuid])
        ->set('motifContestation', 'faux')
        ->call('demanderUnExpert')
        ->assertHasErrors(['motifContestation']);

    expect(CounterExpertise::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('n’ouvre pas DEUX contre-expertises sur le même rapport', function () {
    /*
     | Un double-clic suffirait, et deux journalistes reprendraient le même
     | dossier sans le savoir.
     */
    Notification::fake();

    $retours = app(RetoursSurLeRapport::class);
    $retours->demanderUneContreExpertise($this->verification, $this->citoyen, unMotif());

    expect(fn () => $retours->demanderUneContreExpertise($this->verification, $this->citoyen, unMotif()))
        ->toThrow(RuntimeException::class);

    expect(CounterExpertise::query()->count())->toBe(1);
});

it('rouvre une contestation une fois la première tranchée', function () {
    // Un rapport corrigé peut l'être mal : la voie doit rester ouverte.
    Notification::fake();

    $arbitre = User::factory()->create();
    $arbitre->assignRole(UserRole::Moderateur->value);

    $retours = app(RetoursSurLeRapport::class);
    $premiere = $retours->demanderUneContreExpertise($this->verification, $this->citoyen, unMotif());
    $retours->trancher($premiere, StatutContreExpertise::VerdictMaintenu, $arbitre, 'Les trois sources concordent.');

    $seconde = $retours->demanderUneContreExpertise($this->verification, $this->citoyen, unMotif());

    expect($seconde->id)->not->toBe($premiere->id)
        ->and(CounterExpertise::query()->count())->toBe(2);
});

it('affiche l’issue au demandeur, y compris quand elle nous donne tort', function () {
    Notification::fake();

    $arbitre = User::factory()->create();
    $arbitre->assignRole(UserRole::Moderateur->value);

    $retours = app(RetoursSurLeRapport::class);
    $demande = $retours->demanderUneContreExpertise($this->verification, $this->citoyen, unMotif());
    $retours->trancher($demande, StatutContreExpertise::VerdictCorrige, $arbitre, 'Le rapport a été repris.');

    Livewire::actingAs($this->citoyen)
        ->test(Rapport::class, ['verification' => $this->verification->uuid])
        ->assertSee('Vous aviez raison')
        ->assertSee('Le rapport a été repris.');
});

it('refuse de trancher deux fois la même demande', function () {
    Notification::fake();

    $arbitre = User::factory()->create();
    $arbitre->assignRole(UserRole::Moderateur->value);

    $retours = app(RetoursSurLeRapport::class);
    $demande = $retours->demanderUneContreExpertise($this->verification, $this->citoyen, unMotif());
    $retours->trancher($demande, StatutContreExpertise::VerdictMaintenu, $arbitre);

    expect(fn () => $retours->trancher($demande->fresh(), StatutContreExpertise::VerdictCorrige, $arbitre))
        ->toThrow(RuntimeException::class);
});

it('porte un lien de partage WhatsApp qui contient l’adresse PUBLIQUE du rapport', function () {
    /*
     | Un démenti ne sert à rien s'il ne repart pas par où la rumeur est
     | arrivée — et il n'y repart que si le destinataire peut l'ouvrir.
     |
     | Ce test cherchait `citizen.report`. C'était l'adresse de l'ESPACE
     | CITOYEN : collée dans une conversation WhatsApp, elle rendait un écran
     | de connexion à tous ceux qui n'ont pas de compte, c'est-à-dire à tous
     | ceux à qui le démenti était destiné. Corrigé au lot 3, avec la page
     | publique `/rapport/{uuid}`.
     */
    $public = route('public.report', ['verification' => $this->verification->uuid]);
    $prive = route('citizen.report', ['verification' => $this->verification->uuid]);

    $html = Livewire::actingAs($this->citoyen)
        ->test(Rapport::class, ['verification' => $this->verification->uuid])
        ->html();

    expect($html)->toContain('api.whatsapp.com/send?text=');
    expect($html)->toContain(rawurlencode($public));
    expect($html)->not->toContain(rawurlencode($prive));
});

it('ne fuit pas le rapport d’autrui, par la page comme par la notation', function () {
    $autre = User::factory()->actif()->create();
    $autre->assignRole(UserRole::Citoyen->value);

    /*
     | Deux surfaces, deux vérifications distinctes.
     |
     | Par la ROUTE, la garde du composant rend un 404 — et un 404, pas un 403 :
     | un uuid deviné ne doit pas même révéler que la vérification existe.
     */
    $this->actingAs($autre)
        ->get(route('citizen.report', ['verification' => $this->verification->uuid]))
        ->assertNotFound();

    /*
     | Par un APPEL LIVEWIRE forgé, la même garde lève avant tout traitement.
     | En test unitaire l'exception remonte telle quelle : c'est le
     | gestionnaire d'exceptions du framework qui la transforme en 404 sur une
     | vraie requête, comme l'assertion ci-dessus le prouve.
     */
    expect(fn () => Livewire::actingAs($autre)
        ->test(Rapport::class, ['verification' => $this->verification->uuid])
        ->call('noter', true))
        ->toThrow(ModelNotFoundException::class);

    expect(VerificationFeedback::query()->count())->toBe(0);
});

it('n’écrit un avis que sur une vérification qui a un rapport', function () {
    // Garde-fou de cohérence : le composant redirige avant d'afficher quoi que
    // ce soit quand le rapport n'existe pas encore.
    $enCours = app(SoumissionVerificationService::class)
        ->soumettre($this->citoyen, TypeContenu::Texte, 'Rumeur encore en file d’attente.');

    expect(Verification::query()->find($enCours->id)->report)->toBeNull();

    Livewire::actingAs($this->citoyen)
        ->test(Rapport::class, ['verification' => $enCours->uuid])
        ->assertRedirect(route('citizen.verification', ['verification' => $enCours->uuid]));
});
