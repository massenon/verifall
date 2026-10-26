<?php

declare(strict_types=1);

use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Core\Marketing\Emails\RenduDeGabarit;
use App\Enums\UserRole;
use App\Livewire\Staff\GabaritsEmails;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\GabaritsDEmailSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le catalogue des courriels et son écran
|--------------------------------------------------------------------------
|
| Trois invariants, et ils comptent chacun pour une raison différente :
|
|   1. **Catalogue ↔ semis** — une clé déclarée sans texte donnerait un courriel
|      vide ; un texte sans clé serait du travail que rien n'expédie.
|   2. **Catalogue ↔ code** — un envoi absent du catalogue est le seul dont
|      personne ne peut ajuster le texte, sans qu'aucun écran ne l'explique.
|   3. **Les marqueurs** — `{{ prenom }}` qu'aucun envoi ne remplit part tel
|      quel dans toutes les boîtes.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
});

describe('le catalogue et le semis se répondent', function () {
    it('donne un texte à CHAQUE événement déclaré', function () {
        // Cet invariant remplace un `continue` silencieux qui aurait laissé un
        // courriel sans texte sans que personne ne s'en aperçoive.
        $sansTexte = array_values(array_filter(
            CatalogueDesCourriels::cles(),
            static fn (string $cle): bool => GabaritsDEmailSeeder::texteParDefaut($cle) === null,
        ));

        // La liste plutôt qu'une boucle d'assertions : en cas d'écart, on veut
        // voir TOUTES les clés manquantes d'un coup, pas la première.
        expect($sansTexte)->toBe([]);
    });

    it('n’écrit AUCUN texte pour une clé absente du catalogue', function () {
        // Le sens inverse : du travail rédigé que rien n'expédierait.
        $this->seed(GabaritsDEmailSeeder::class);

        $semees = EmailTemplate::query()->pluck('key')->all();

        expect(array_diff($semees, CatalogueDesCourriels::cles()))->toBe([]);
    });

    it('n’emploie que des marqueurs qu’il déclare lui-même', function () {
        /*
         | Le texte par défaut est écrit à la main : rien n'empêche d'y taper
         | `{{ prenom }}` sur un envoi qui ne le fournit pas. Ce serait
         | invisible jusqu'au premier destinataire.
         */
        $this->seed(GabaritsDEmailSeeder::class);

        $fautifs = [];

        foreach (CatalogueDesCourriels::cles() as $cle) {
            $textes = GabaritsDEmailSeeder::texteParDefaut($cle);
            $tout = implode(' ', [$textes['sujet'], $textes['titre'], $textes['corps'], $textes['bouton'] ?? '']);

            preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/u', $tout, $trouves);

            foreach ($trouves[1] as $marqueur) {
                if (! in_array($marqueur, CatalogueDesCourriels::variables($cle), true)) {
                    $fautifs[] = $cle.' → {{ '.$marqueur.' }}';
                }
            }
        }

        expect($fautifs)->toBe([]);
    });
});

describe('le rendu', function () {
    beforeEach(fn () => $this->seed(GabaritsDEmailSeeder::class));

    it('remplace les marqueurs fournis', function () {
        $corps = app(RenduDeGabarit::class)->corps('otp', [
            'prenom' => 'Adderly', 'code' => '482913', 'minutes' => 10,
        ]);

        expect($corps)->toContain('Adderly')
            ->and($corps)->toContain('482913')
            ->and($corps)->not->toContain('{{');
    });

    it('RETIRE un marqueur que l’envoi ne fournit pas', function () {
        // « Bonjour {{ prenom }}, » est pire que « Bonjour, » — et il part tel
        // quel dans toutes les boîtes.
        $corps = app(RenduDeGabarit::class)->corps('otp', ['code' => '482913']);

        expect($corps)->not->toContain('{{')
            ->and($corps)->not->toContain('prenom');
    });

    it('refuse d’éteindre un envoi indispensable', function () {
        /*
         | Un code à usage unique qu'un réglage peut couper est un compte qu'on
         | ne peut plus créer ; une suspension dont on n'informe personne est une
         | panne, du point de vue de la personne.
         */
        EmailTemplate::query()->where('key', 'otp')->update(['is_active' => false]);
        app(RenduDeGabarit::class)->oublier();

        expect(app(RenduDeGabarit::class)->estAllume('otp'))->toBeTrue();
    });

    it('respecte l’extinction d’un envoi qui n’est pas indispensable', function () {
        expect(app(RenduDeGabarit::class)->estAllume('badge_debloque'))->toBeFalse();
    });

    it('fonctionne TABLE VIDE, en suivant ce que le catalogue déclare', function () {
        // Un semis qui n'a pas encore tourné ne doit pas éteindre la plateforme.
        EmailTemplate::query()->delete();
        app(RenduDeGabarit::class)->oublier();

        expect(app(RenduDeGabarit::class)->estAllume('otp'))->toBeTrue()
            ->and(app(RenduDeGabarit::class)->estAllume('mention_forum'))->toBeTrue()
            ->and(app(RenduDeGabarit::class)->corps('otp'))->toBeNull();
    });
});

describe('l’écran', function () {
    beforeEach(fn () => $this->seed(GabaritsDEmailSeeder::class));

    it('liste les 24 événements, avec leur déclencheur', function () {
        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->assertSee('Code à usage unique')
            ->assertSee('Inscription, réinitialisation du mot de passe')
            ->assertSee('Variables');
    });

    it('n’affiche AUCUN « pas encore câblé », les vingt-quatre étant expédiés', function () {
        /*
         | Ce test attendait l'inverse, et il avait raison de l'attendre : huit
         | gabarits étaient alors rédigeables sans que rien ne les expédie, et le
         | taire sur l'écran qui prétend tout lister aurait recréé le piège que
         | ce chantier a corrigé six fois.
         |
         | Depuis le 2 septembre au soir, les vingt-quatre partent. Le badge
         | reste dans le gabarit, prêt pour un vingt-cinquième événement ; c'est
         | `CourrielsCablesTest` qui garantit qu'aucun ne s'installe durablement
         | dans cet état.
         */
        // Les vingt-quatre d'un coup : la liste est paginée, on demande la plus grande page.
        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->set('parPage', 50)
            ->assertDontSee('Pas encore câblé');
    });

    it('refuse un marqueur que l’envoi ne fournit pas', function () {
        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->call('ouvrirEdition', 'otp')
            ->set('corps', 'Bonjour {{ prenom_complet }}, voici votre code : {{ code }}.')
            ->call('enregistrer')
            ->assertHasErrors('corps');

        // Rien n'a été écrit : la garde s'applique AVANT l'enregistrement.
        expect(EmailTemplate::query()->where('key', 'otp')->value('body'))
            ->not->toContain('prenom_complet');
    });

    it('enregistre corps et titre, pas seulement l’objet', function () {
        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->call('ouvrirEdition', 'kyc_approuve')
            ->set('sujet', 'Votre identité est confirmée')
            ->set('titre', 'C’est validé')
            ->set('corps', 'Bonjour {{ prenom }}, votre dossier a été approuvé sans réserve.')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $gabarit = EmailTemplate::query()->where('key', 'kyc_approuve')->firstOrFail();

        expect($gabarit->subject)->toBe('Votre identité est confirmée')
            ->and($gabarit->heading)->toBe('C’est validé')
            ->and($gabarit->body)->toContain('sans réserve')
            // Qui, et pas seulement quand : sur un texte qui part au public.
            ->and($gabarit->updated_by)->toBe($this->admin->id);
    });

    it('refuse d’éteindre un envoi indispensable depuis la liste', function () {
        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->call('basculer', 'otp');

        expect(EmailTemplate::query()->where('key', 'otp')->first()?->is_active)->toBeTrue();
    });

    it('éteint un envoi qui peut l’être, et le journalise', function () {
        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->call('basculer', 'mention_forum');

        expect(EmailTemplate::query()->where('key', 'mention_forum')->first()?->is_active)->toBeFalse();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'email_template.disable',
            'actor_id' => $this->admin->id,
        ]);
    });

    it('refuse une clé hors catalogue', function () {
        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->call('ouvrirEdition', 'clef_inventee')
            ->assertNotFound();
    });
});
