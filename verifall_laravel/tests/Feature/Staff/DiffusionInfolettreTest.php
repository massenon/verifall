<?php

declare(strict_types=1);

use App\Core\Marketing\Enums\StatutCampagne;
use App\Enums\UserRole;
use App\Jobs\DiffuserInfolettre;
use App\Livewire\Staff\Infolettre;
use App\Mail\DiffusionInfolettre;
use App\Models\Campaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Diffuser une infolettre — arbitrage 156 renversé
|--------------------------------------------------------------------------
|
| L'écran n'envoyait pas, et le disait : « un bouton envoyer maintenant derrière
| un simple clic expédierait plusieurs milliers de courriels sans retour
| possible ». L'argument condamnait le SIMPLE CLIC, pas la fonctionnalité.
|
| Ces tests tiennent les quatre gardes qui rendent l'ouverture défendable : la
| permission distincte, la confirmation qui annonce le nombre, la mise en file,
| et la trace d'audit. Plus la propriété qui compte vraiment : **personne ne
| reçoit deux fois le même message.**
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

/** Un abonné confirmé, donc destinataire. */
function abonneConfirme(string $email): NewsletterSubscriber
{
    return NewsletterSubscriber::create([
        'uuid' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Abonné',
        // Les deux jetons sont NOT NULL : la confirmation se prouve par
        // `confirmed_at`, pas par l'absence de jeton.
        'confirmation_token' => bin2hex(random_bytes(32)),
        'unsubscribe_token' => bin2hex(random_bytes(32)),
        'confirmed_at' => now(),
    ]);
}

/** Une campagne manuelle, montée comme le dépôt la monte. */
function campagneManuelle(string $cleEdition, array $attributs): Campaign
{
    // Pas de `tap()` ici : sur un modèle, il est transmis au Builder de
    // requête, et la fermeture reçoit un Builder au lieu du modèle.
    $campagne = (new Campaign)->forceFill(array_merge([
        'uuid' => (string) Str::uuid(),
        'edition_key' => $cleEdition,
        'audience' => 'subscribers',
        'status' => StatutCampagne::Brouillon->value,
    ], $attributs));

    $campagne->save();

    return $campagne;
}

it('cache l’onglet à qui n’a pas la permission d’envoyer', function () {
    /*
     | Consulter la liste et écrire à trois mille personnes ne sont pas le même
     | geste. La matrice séparait déjà `newsletter.view` de `newsletter.send` ;
     | l'écran, lui, ne le reflétait pas — il n'avait rien à refléter.
     */
    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);

    expect($moderateur->can('newsletter.send'))->toBeFalse();

    Livewire::actingAs($moderateur)
        ->test(Infolettre::class)
        ->assertDontSee('Diffuser une Newsletter');
});

it('refuse la diffusion à qui peut seulement CONSULTER la liste', function () {
    /*
     | L'onglet caché n'est pas une garde : c'est de l'affichage. Le cas qui
     | compte est celui de quelqu'un qui entre légitimement sur l'écran — donc
     | qui détient `newsletter.view` — et appelle la méthode sans avoir
     | `newsletter.send`. Un rôle sans aucune des deux ne franchirait même pas
     | `mount()`, et ne prouverait rien de cette garde-ci.
     */
    $lecteur = User::factory()->actif()->create();
    $lecteur->givePermissionTo('newsletter.view');

    abonneConfirme('lectrice@example.test');

    Livewire::actingAs($lecteur)
        ->test(Infolettre::class)
        ->set('objet', 'Un objet valable')
        ->set('corps', '<p>Un contenu assez long pour passer la validation.</p>')
        ->call('diffuser')
        ->assertForbidden();

    expect(Campaign::count())->toBe(0);
});

it('annonce le nombre de destinataires avant de rien envoyer', function () {
    abonneConfirme('une@example.test');
    abonneConfirme('deux@example.test');

    Livewire::actingAs($this->admin)
        ->test(Infolettre::class)
        ->call('changerOnglet', 'diffuser')
        // C'est ce chiffre qui fait hésiter au bon moment, pas un avertissement.
        ->assertSee('Votre message sera envoyé à')
        ->assertSee('2');
});

describe('l’éditeur de « Diffuser une Newsletter » — régression du 14 septembre 2026', function () {
    /*
     | L'éditeur était INVISIBLE. Deux causes, vérifiées au navigateur :
     |
     |  1. l'onglet « Diffuser » tombait dans le `@else` de l'historique : la vue
     |     affichait « Aucune édition envoyée » — faux, `$envois` n'étant pas
     |     chargé hors de l'historique — et repoussait le formulaire sous la
     |     ligne de flottaison ;
     |  2. le pont JavaScript ne montait Quill qu'au chargement. Arrivé sur
     |     « Abonnés » puis passé sur « Diffuser », l'hôte restait vide, 0 px
     |     de haut, sans erreur.
     |
     | La première cause se prouve en PHP. La seconde est du JavaScript : ce
     | test en garde la structure (conteneur, `wire:ignore`, actif Quill, et le
     | remontage après rendu) ; son comportement a été vérifié au navigateur.
     */
    it('rend le conteneur, son wire:ignore et l’actif Quill à l’arrivée directe sur ?vue=diffuser', function () {
        abonneConfirme('une@example.test');

        $html = test()->actingAs($this->admin, 'staff')
            ->get(route('staff.newsletter', ['vue' => 'diffuser']))
            ->assertOk()
            ->assertSee('Diffuser une Newsletter')
            ->assertSee('assets/js/vendor/quill.js', false)
            ->getContent();

        // L'hôte est DANS un conteneur `wire:ignore` : sans lui, le premier
        // rendu Livewire effacerait le DOM que Quill a construit.
        expect($html)->toMatch('/<div class="[^"]*editeur-riche[^"]*" wire:ignore>\s*<div data-quill-diffusion><\/div>/');
    });

    it('n’affiche plus l’état vide de l’historique sur l’onglet Diffuser', function () {
        abonneConfirme('une@example.test');

        Livewire::actingAs($this->admin)
            ->test(Infolettre::class)
            ->call('changerOnglet', 'diffuser')
            ->assertSee('data-quill-diffusion', false)
            ->assertDontSee('Aucune édition envoyée');
    });

    it('remonte l’éditeur après chaque rendu, pas seulement au chargement', function () {
        // Garde de structure du pont : sans ce crochet, la bascule d'onglet
        // sans rechargement retrouve un hôte vide.
        test()->actingAs($this->admin, 'staff')
            ->get(route('staff.newsletter', ['vue' => 'diffuser']))
            ->assertSee("Livewire.hook('morphed'", false)
            ->assertSee('data-quill-monte', false);
    });

    it('purifie le HTML soumis par l’éditeur, attributs dangereux compris', function () {
        Queue::fake();
        abonneConfirme('une@example.test');

        Livewire::actingAs($this->admin)
            ->test(Infolettre::class)
            ->call('changerOnglet', 'diffuser')
            ->set('objet', 'Objet purifié')
            ->set('corps', '<h2>Titre</h2><p onclick="alert(1)">Bonjour <strong>à tous</strong>'
                .'<a href="javascript:alert(1)">piège</a></p><iframe src="https://exemple.test"></iframe>')
            ->call('diffuser')
            ->assertHasNoErrors();

        $corps = Campaign::where('subject', 'Objet purifié')->firstOrFail()->body;

        expect($corps)->not->toContain('onclick')
            ->and($corps)->not->toContain('javascript:')
            ->and($corps)->not->toContain('<iframe')
            ->and($corps)->toContain('<strong>à tous</strong>');
    });
});

it('valide AVANT d’ouvrir la confirmation', function () {
    /*
     | Découvrir que l'objet est vide dans une boîte qui annonce « 3 000
     | destinataires » est le moment le plus mal choisi pour lire une erreur.
     */
    abonneConfirme('une@example.test');

    Livewire::actingAs($this->admin)
        ->test(Infolettre::class)
        ->set('objet', '')
        ->set('corps', '')
        ->call('confirmerLaDiffusion')
        ->assertHasErrors(['objet', 'corps'])
        ->assertSet('confirmation', false);
});

it('met la diffusion en file plutôt que de l’envoyer dans la requête', function () {
    Queue::fake();
    abonneConfirme('une@example.test');

    Livewire::actingAs($this->admin)
        ->test(Infolettre::class)
        ->set('objet', 'Les intox de la semaine')
        ->set('corps', '<p>Un contenu assez long pour passer la validation.</p>')
        ->call('diffuser')
        ->assertHasNoErrors();

    // Le départ ne dépend pas d'un onglet resté ouvert.
    Queue::assertPushed(DiffuserInfolettre::class);

    expect(Campaign::where('subject', 'Les intox de la semaine')->exists())->toBeTrue();
});

it('purifie le corps AVANT de l’écrire, pas à l’affichage', function () {
    Queue::fake();
    abonneConfirme('une@example.test');

    Livewire::actingAs($this->admin)
        ->test(Infolettre::class)
        ->set('objet', 'Un objet valable')
        ->set('corps', '<p>Bonjour</p><script>alert(1)</script><p>et bonne lecture à tous.</p>')
        ->call('diffuser');

    $campagne = Campaign::where('subject', 'Un objet valable')->firstOrFail();

    /*
     | Un courriel part HORS de l'application : aucun filtre d'affichage ne le
     | rattrapera. La garde doit donc être à l'écriture.
     */
    expect($campagne->body)->not->toContain('<script>')
        ->and($campagne->body)->toContain('Bonjour');
});

it('n’écrit aucune campagne quand la liste est vide', function () {
    Queue::fake();

    // Une campagne sans destinataire polluerait l'historique d'une ligne qui
    // ressemble à un échec.
    Livewire::actingAs($this->admin)
        ->test(Infolettre::class)
        ->set('objet', 'Un objet valable')
        ->set('corps', '<p>Un contenu assez long pour passer la validation.</p>')
        ->call('diffuser');

    expect(Campaign::count())->toBe(0);
    Queue::assertNotPushed(DiffuserInfolettre::class);
});

it('laisse une trace d’audit nominative', function () {
    Queue::fake();
    abonneConfirme('une@example.test');

    Livewire::actingAs($this->admin)
        ->test(Infolettre::class)
        ->set('objet', 'Les intox de la semaine')
        ->set('corps', '<p>Un contenu assez long pour passer la validation.</p>')
        ->call('diffuser');

    // Un envoi de masse est exactement le geste qu'on doit pouvoir attribuer.
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'newsletter.broadcast',
        'actor_id' => $this->admin->id,
        'target_label' => 'Les intox de la semaine',
    ]);
});

describe('le job de diffusion', function () {
    it('envoie à chaque abonné confirmé, et marque la campagne envoyée', function () {
        Mail::fake();

        abonneConfirme('une@example.test');
        abonneConfirme('deux@example.test');

        // `edition_key` est hors `$fillable` (arbitrage 159) : le dépôt lui-même
        // passe par `forceFill`. Le test emprunte le même chemin.
        $campagne = campagneManuelle('manuelle-20260902120000', [
            'name' => 'Essai',
            'subject' => 'Essai de diffusion',
            'body' => '<p>Bonjour à tous.</p>',
            'recipients_count' => 2,
        ]);

        app()->call([new DiffuserInfolettre($campagne->id), 'handle']);

        Mail::assertSent(DiffusionInfolettre::class, 2);

        expect($campagne->fresh()->status)->toBe(StatutCampagne::Envoyee->value)
            ->and((int) $campagne->fresh()->sent_count)->toBe(2);
    });

    it('ne sert JAMAIS deux fois la même adresse, même rejoué', function () {
        /*
         | LA propriété. `reserverEnvoi()` insère la ligne de livraison AVANT
         | l'envoi ; l'unicité en base fait qu'une seconde exécution ne peut pas
         | réserver ce qui l'est déjà. C'est ce qui permet de relancer sans
         | réfléchir après un incident.
         */
        Mail::fake();
        abonneConfirme('une@example.test');

        $campagne = campagneManuelle('manuelle-20260902130000', [
            'name' => 'Essai',
            'subject' => 'Essai de diffusion',
            'body' => '<p>Bonjour.</p>',
            'recipients_count' => 1,
        ]);

        app()->call([new DiffuserInfolettre($campagne->id), 'handle']);

        // Rejoué à la main, statut remis à « en cours » comme après un incident.
        $campagne->forceFill(['status' => StatutCampagne::EnCours->value])->save();
        app()->call([new DiffuserInfolettre($campagne->id), 'handle']);

        Mail::assertSent(DiffusionInfolettre::class, 1);
    });

    it('porte le lien de désabonnement dans le corps RENDU', function () {
        // Un envoi de masse sans lien de retrait est une infraction — et
        // vérifier l'objet plutôt que le rendu laisserait passer un gabarit qui
        // ne l'affiche pas.
        $abonne = abonneConfirme('une@example.test');

        $courriel = new DiffusionInfolettre(
            objet: 'Essai',
            corps: '<p>Bonjour.</p>',
            lienDeDesabonnement: 'https://verifall.test/infolettre/desabonnement/'.$abonne->uuid,
            nomDuSite: "Vérif'All",
        );

        expect($courriel->render())->toContain($abonne->uuid);
    });
});
