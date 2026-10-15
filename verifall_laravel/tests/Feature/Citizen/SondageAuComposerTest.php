<?php

declare(strict_types=1);

use App\Core\Community\Services\Sondages;
use App\Livewire\Citizen\Forum;
use App\Models\Poll;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le sondage, posé dès la création du sujet
|--------------------------------------------------------------------------
| Il pouvait déjà s'ajouter depuis la page d'un sujet publié. En pratique,
| presque personne n'y revenait : la question qu'on veut poser est présente à
| l'esprit au moment où l'on écrit, pas dix minutes plus tard sur un autre
| écran.
*/

beforeEach(function (): void {
    // L'écran du forum lit les rôles : sans ce jeu, la fabrique de comptes
    // échoue sur un rôle inexistant.
    test()->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->create(['otp_verified_at' => now()]);
});

function composerAvecSondage(array $options = ['Oui', 'Non']): Testable
{
    return Livewire::actingAs(test()->citoyen)
        ->test(Forum::class)
        ->set('titre', 'Avez-vous reçu ce message sur WhatsApp ?')
        ->set('corps', 'Un message circule au sujet d’une coupure d’électricité annoncée pour lundi.')
        ->set('avecSondage', true)
        ->set('questionSondage', 'Avez-vous déjà reçu ce message ?')
        ->set('optionsSondage', $options);
}

it('crée le sondage EN MÊME TEMPS que le sujet', function (): void {
    composerAvecSondage()->call('publier');

    $sujet = Thread::query()->first();
    $sondage = Poll::query()->first();

    expect($sujet)->not->toBeNull()
        ->and($sondage)->not->toBeNull()
        ->and($sondage->thread_id)->toBe($sujet->id)
        ->and($sondage->question)->toBe('Avez-vous déjà reçu ce message ?')
        ->and($sondage->options()->count())->toBe(2);
});

it('publie SANS sondage quand la case est décochée', function (): void {
    Livewire::actingAs($this->citoyen)
        ->test(Forum::class)
        ->set('titre', 'Une question sur une vidéo qui circule')
        ->set('corps', 'Une vidéo circule sur WhatsApp et je voudrais la faire vérifier.')
        ->call('publier');

    expect(Thread::query()->count())->toBe(1)
        ->and(Poll::query()->count())->toBe(0);
});

it('GARDE le sujet publié quand le sondage est mal formé', function (): void {
    /*
     | LA décision qui compte ici.
     |
     | Annuler la publication parce que le sondage est mal formé ferait perdre
     | un texte déjà écrit pour un accessoire. Le sujet reste, on dit ce qui
     | n'a pas marché, et la personne ajoute son sondage depuis la page du
     | sujet.
     */
    composerAvecSondage(['Oui', 'Oui'])->call('publier');

    expect(Thread::query()->count())->toBe(1)
        ->and(Poll::query()->count())->toBe(0);
});

it('refuse une question trop courte avant même de publier', function (): void {
    Livewire::actingAs($this->citoyen)
        ->test(Forum::class)
        ->set('titre', 'Une question sur une vidéo qui circule')
        ->set('corps', 'Une vidéo circule sur WhatsApp et je voudrais la faire vérifier.')
        ->set('avecSondage', true)
        ->set('questionSondage', 'Hein')
        ->call('publier')
        ->assertHasErrors('questionSondage');

    expect(Thread::query()->count())->toBe(0);
});

describe('les réponses proposées', function (): void {
    it("s'ajoutent jusqu'au maximum, pas au-delà", function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(Forum::class);

        for ($i = 0; $i < Sondages::OPTIONS_MAXIMUM + 3; $i++) {
            $composant->call('ajouterUneOption');
        }

        expect($composant->get('optionsSondage'))->toHaveCount(Sondages::OPTIONS_MAXIMUM);
    });

    it('ne descendent JAMAIS sous le minimum', function (): void {
        /*
         | Laisser retirer la dernière case produirait un formulaire sans
         | réponse possible, et un bouton « ajouter » comme seule issue.
         */
        $composant = Livewire::actingAs($this->citoyen)->test(Forum::class);

        $composant->call('retirerUneOption', 0);
        $composant->call('retirerUneOption', 0);

        expect($composant->get('optionsSondage'))->toHaveCount(Sondages::OPTIONS_MINIMUM);
    });

    it('restent indexées sans trou après un retrait', function (): void {
        /*
         | Livewire sérialise un tableau à trous en objet, et les champs
         | cessent alors de se lier : la personne tape dans une case et rien ne
         | se passe. Le défaut est invisible côté serveur.
         */
        $composant = Livewire::actingAs($this->citoyen)->test(Forum::class);

        $composant->call('ajouterUneOption')->call('ajouterUneOption');
        $composant->call('retirerUneOption', 1);

        expect(array_keys($composant->get('optionsSondage')))->toBe([0, 1, 2]);
    });
});

it('remet le composeur à blanc, sondage compris', function (): void {
    // Sans cela, la personne qui enchaîne un second sujet retrouverait ses
    // réponses précédentes.
    $composant = composerAvecSondage();
    $composant->call('publier');

    expect($composant->get('avecSondage'))->toBeFalse()
        ->and($composant->get('questionSondage'))->toBe('')
        ->and($composant->get('optionsSondage'))->toBe(['', '']);
});
