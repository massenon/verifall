<?php

declare(strict_types=1);

use App\Core\Identity\Exceptions\CompteSuspendu;
use App\Core\Identity\Exceptions\IdentifiantsInvalides;
use App\Core\Identity\Services\AuthentificationCitoyen;
use App\Core\Identity\Services\SuspensionDeCompte;
use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Enums\UserRole;
use App\Livewire\Staff\Utilisateurs;
use App\Mail\CourrielDeGabarit;
use App\Models\User;
use Database\Seeders\GabaritsDEmailSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| La suspension d'un compte
|--------------------------------------------------------------------------
|
| La colonne manquait ; `user.suspend` était déclaré depuis l'origine, les deux
| courriels depuis le 2 septembre, et `Staff\Utilisateurs` documentait lui-même
| que « la suspension demande une colonne qui n'existe pas ».
|
| **Une suspension qui ne suspend pas n'est pas une suspension.** Ce que ces
| tests tiennent est donc l'EFFET, pas l'enregistrement : les sessions fermées,
| la connexion refusée, et le refus qui se DIT plutôt que de passer pour une
| panne.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(GabaritsDEmailSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);

    $this->citoyen = User::factory()->actif()->create([
        'password' => Hash::make('Un-Mot-De-Passe-2026!'),
    ]);
    $this->citoyen->assignRole(UserRole::Citoyen->value);

    Mail::fake();
});

// ═════════════════════════════════════════════════════════════ le service

it('pose l’état, ferme les sessions, journalise et prévient', function () {
    // Les quatre ensemble. Le geste n'est complet qu'à ce prix.
    DB::table('sessions')->insert([
        'id' => 'session-de-test',
        'user_id' => $this->citoyen->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'vide',
        'last_activity' => now()->timestamp,
    ]);

    app(SuspensionDeCompte::class)->suspendre(
        $this->citoyen,
        $this->admin,
        'Publication répétée de contenus haineux malgré deux avertissements.',
    );

    expect($this->citoyen->fresh()->estSuspendu())->toBeTrue()
        ->and($this->citoyen->fresh()->suspension_reason)->toContain('haineux')
        ->and($this->citoyen->fresh()->suspended_by)->toBe($this->admin->id)
        // Sans cela, la personne continue de publier des heures durant sur un
        // compte officiellement suspendu, et la modération croit avoir agi.
        ->and(DB::table('sessions')->where('user_id', $this->citoyen->id)->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.suspend',
        'actor_id' => $this->admin->id,
    ]);

    Mail::assertQueued(
        CourrielDeGabarit::class,
        fn (CourrielDeGabarit $m): bool => $m->cle === 'compte_suspendu'
            && str_contains((string) $m->variables['motif'], 'haineux'),
    );
});

it('refuse une suspension sans motif', function () {
    // Une sanction sans raison lisible n'est pas contestable ; elle devient un
    // message au support, puis de la défiance.
    expect(fn () => app(SuspensionDeCompte::class)->suspendre($this->citoyen, $this->admin, '   '))
        ->toThrow(RuntimeException::class);

    expect($this->citoyen->fresh()->estSuspendu())->toBeFalse();
});

it('refuse qu’on se suspende soi-même', function () {
    // Ce n'est pas théorique : c'est la ligne du dessus dans une liste triée,
    // et le geste se ferait depuis un compte qu'on vient de fermer.
    expect(fn () => app(SuspensionDeCompte::class)->suspendre($this->admin, $this->admin, 'Un motif valable et long.'))
        ->toThrow(RuntimeException::class);
});

it('ne réécrit PAS la date d’une suspension déjà prononcée', function () {
    // « Depuis quand » est la première question qu'on pose ; la réécrire à
    // chaque clic la rendrait fausse.
    $service = app(SuspensionDeCompte::class);
    $service->suspendre($this->citoyen, $this->admin, 'Un motif valable et suffisamment long.');

    $premiere = $this->citoyen->fresh()->suspended_at;
    $this->travel(2)->hours();

    $service->suspendre($this->citoyen->fresh(), $this->admin, 'Un autre motif, tout aussi long.');

    expect($this->citoyen->fresh()->suspended_at->timestamp)->toBe($premiere->timestamp);
});

it('efface le motif au rétablissement, et prévient', function () {
    $service = app(SuspensionDeCompte::class);
    $service->suspendre($this->citoyen, $this->admin, 'Un motif valable et suffisamment long.');
    Mail::fake();

    $service->retablir($this->citoyen->fresh(), $this->admin);

    // Le garder ferait porter au compte rétabli la trace d'une sanction levée.
    // La décision, elle, reste au journal d'audit.
    expect($this->citoyen->fresh()->estSuspendu())->toBeFalse()
        ->and($this->citoyen->fresh()->suspension_reason)->toBeNull();

    Mail::assertQueued(
        CourrielDeGabarit::class,
        fn (CourrielDeGabarit $m): bool => $m->cle === 'compte_retabli',
    );

    $this->assertDatabaseHas('audit_logs', ['action' => 'user.unsuspend']);
});

// ══════════════════════════════════════════════════════════ l'application

it('refuse la connexion d’un compte suspendu, EN LE DISANT', function () {
    /*
     | Le point le plus important de ce lot. Taire la suspension enverrait la
     | personne réinitialiser un mot de passe qui n'est pas en cause, échouer
     | encore, puis écrire au support en colère : la sanction deviendrait une
     | panne de son point de vue.
     */
    app(SuspensionDeCompte::class)->suspendre(
        $this->citoyen,
        $this->admin,
        'Publication répétée de contenus haineux.',
    );

    expect(fn () => app(AuthentificationCitoyen::class)->authentifier(
        (string) $this->citoyen->email,
        'Un-Mot-De-Passe-2026!',
        '127.0.0.1',
    ))->toThrow(CompteSuspendu::class);
});

it('garde le refus INDISTINCT quand le mot de passe est faux', function () {
    /*
     | La contrepartie, et c'est elle qui empêche l'énumération : le message
     | nommé n'apparaît qu'APRÈS vérification du mot de passe. Un mauvais mot de
     | passe sur un compte suspendu rend la même réponse que sur n'importe quel
     | autre compte.
     */
    app(SuspensionDeCompte::class)->suspendre($this->citoyen, $this->admin, 'Un motif valable et long.');

    expect(fn () => app(AuthentificationCitoyen::class)->authentifier(
        (string) $this->citoyen->email,
        'un-mauvais-mot-de-passe',
        '127.0.0.1',
    ))->toThrow(IdentifiantsInvalides::class);
});

it('éjecte de l’espace citoyen une session déjà ouverte', function () {
    // Le filet du middleware : il rattrape un « se souvenir de moi » rouvert, et
    // une suspension posée autrement que par le service.
    $this->actingAs($this->citoyen);
    $this->citoyen->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Un motif.'])->save();

    $this->get(route('citizen.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('compte_suspendu');
});

it('affiche les DEUX refus sur l’écran de connexion', function () {
    /*
     | `compte_inactif` était posé en session depuis l'origine et n'était affiché
     | nulle part : la personne était renvoyée sans un mot et réessayait le même
     | mot de passe. Sixième occurrence d'une donnée écrite que rien ne lit.
     */
    $this->withSession(['compte_suspendu' => true])
        ->get(route('login'))
        ->assertOk()
        ->assertSee('Ce compte est suspendu');

    $this->withSession(['compte_inactif' => true])
        ->get(route('login'))
        ->assertOk()
        ->assertSee('pas encore activé');
});

// ═══════════════════════════════════════════════════════════════ l'écran

it('permet à un administrateur de suspendre depuis l’écran', function () {
    Livewire::actingAs($this->admin, 'staff')
        ->test(Utilisateurs::class)
        ->call('ouvrirLaSuspension', $this->citoyen->uuid)
        ->set('motifDeSuspension', 'Publication répétée de contenus haineux.')
        ->call('suspendre')
        ->assertHasNoErrors();

    expect($this->citoyen->fresh()->estSuspendu())->toBeTrue();
});

it('refuse un motif trop court', function () {
    Livewire::actingAs($this->admin, 'staff')
        ->test(Utilisateurs::class)
        ->call('ouvrirLaSuspension', $this->citoyen->uuid)
        ->set('motifDeSuspension', 'spam')
        ->call('suspendre')
        ->assertHasErrors('motifDeSuspension');

    expect($this->citoyen->fresh()->estSuspendu())->toBeFalse();
});

it('interdit à un modérateur de suspendre un compte du PERSONNEL', function () {
    /*
     | Modérer les contenus et administrer les accès sont deux métiers. Sans
     | cette borne, quiconque modère pourrait fermer le compte d'un
     | administrateur — et la matrice sépare déjà les deux droits.
     */
    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);
    $moderateur->givePermissionTo('user.suspend', 'user.view');

    expect($moderateur->can('suspend', $this->admin))->toBeFalse()
        ->and($moderateur->can('suspend', $this->citoyen))->toBeTrue();
});

// ═══════════════════════════════════════════════════════════ le catalogue

it('ne laisse PLUS AUCUN courriel non câblé', function () {
    // Vingt-quatre déclarés, vingt-quatre expédiés. La colonne « Pas encore
    // câblé » de l'écran n'a plus de ligne à afficher.
    $aFaire = array_keys(array_filter(
        CatalogueDesCourriels::EVENEMENTS,
        static fn (array $e): bool => ! $e['porte'],
    ));

    expect($aFaire)->toBe([]);
});
