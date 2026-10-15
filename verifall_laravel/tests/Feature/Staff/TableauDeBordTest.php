<?php

declare(strict_types=1);

use App\Core\Application\Services\TableauDeBordPersonnel;
use App\Enums\StatutMessageContact;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| LA PREMIÈRE PAGE APRÈS CONNEXION
|--------------------------------------------------------------------------
|
| Elle servait `stub.staff` : « SPRINT 1A — le contenu de cette page arrive à
| son sprint ». Tout le reste du back-office était construit ; seule la page
| d'accueil, celle qu'on ne peut pas éviter, annonçait un chantier.
|
| Le défaut ne produisait aucune erreur : la page se rendait, les tests
| passaient, la navigation fonctionnait. Il fallait la REGARDER — ce qu'a fait
| l'utilisateur, en production.
|
| Le premier test ci-dessous est donc un test de non-retour : il refuse le
| gabarit d'attente sur cette route, quelle qu'en soit la raison.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->membre = User::factory()->create(['otp_verified_at' => now()]);
    $this->membre->assignRole(UserRole::Admin->value);
});

/** Session staff prête à l'emploi. */
function personnelConnecte(User $membre)
{
    return test()->actingAs($membre, 'staff')
        ->withSession(['staff.last_activity' => now()->timestamp]);
}

describe("l'écran d'accueil du personnel", function (): void {
    it("n'affiche PLUS un écran d'attente", function (): void {
        $html = (string) personnelConnecte($this->membre)
            ->get(route('staff.dashboard'))->assertOk()->getContent();

        expect($html)->not->toContain('SPRINT 1A')
            ->and($html)->not->toContain('arrive à son sprint');
    });

    it('accueille par le nom, puis par ce qui attend', function (): void {
        $reponse = personnelConnecte($this->membre)->get(route('staff.dashboard'));

        $reponse->assertOk()
            ->assertSee('Bon retour')
            ->assertSee('À traiter maintenant', escape: false);
    });

    it('exige une session du personnel', function (): void {
        test()->get(route('staff.dashboard'))->assertRedirect(route('staff.login'));
    });
});

describe('ce qui attend une décision', function (): void {
    it('compte les arbitrages, les signalements et les messages', function (): void {
        /*
         | « En arbitrage » est la file la plus importante : les moteurs se
         | contredisent, un humain doit trancher, et un rapport déjà payé
         | attend. Elle ne doit jamais se confondre avec une analyse échouée.
         */
        Verification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->membre->id,
            'type' => TypeContenu::Texte->value,
            'status' => StatutVerification::RevueHumaine->value,
            'claim' => 'Une affirmation en litige',
            'cost_units' => 1,
        ]);

        ContactMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'kind' => 'contact',
            'name' => 'Une personne',
            'email' => 'personne@exemple.org',
            'message' => 'Une question.',
            'status' => StatutMessageContact::Nouveau->value,
        ]);

        $html = (string) personnelConnecte($this->membre)
            ->get(route('staff.dashboard'))->getContent();

        expect($html)->toContain('En arbitrage')
            ->and($html)->toContain('Messages');
    });

    it('compte AUSSI les messages pris en charge mais non terminés', function (): void {
        /*
         | Ne compter que les « nouveaux » ferait disparaître de l'écran ce
         | qu'on a commencé sans finir — précisément ce qu'il ne faut pas
         | oublier.
         */
        foreach ([StatutMessageContact::Nouveau, StatutMessageContact::EnCours] as $statut) {
            ContactMessage::query()->create([
                'uuid' => (string) Str::uuid(),
                'kind' => 'contact',
                'name' => 'Une personne',
                'email' => 'p'.$statut->value.'@exemple.org',
                'message' => 'Une question.',
                'status' => $statut->value,
            ]);
        }

        // Un message traité ne compte plus.
        ContactMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'kind' => 'contact',
            'name' => 'Une autre',
            'email' => 'traite@exemple.org',
            'message' => 'Réglée.',
            'status' => StatutMessageContact::Traite->value,
        ]);

        $chiffres = app(TableauDeBordPersonnel::class)->aTraiter();

        expect($chiffres['messages'])->toBe(2);
    });
});

describe('les avertissements de configuration', function (): void {
    it('signale que les moteurs tournent en mode SIMULÉ', function (): void {
        /*
         | Un back-office qui n'affiche pas l'état de sa propre installation
         | laisse découvrir les pannes par les visiteurs. Ce cas précis s'est
         | produit en mise en ligne : les analyses échouaient en boucle et rien
         | ne le disait à l'équipe.
         */
        config()->set('factcheck.fake_adapters', true);

        $html = (string) personnelConnecte($this->membre)
            ->get(route('staff.dashboard'))->getContent();

        expect($html)->toContain('mode SIMUL');
    });

    it('se tait quand tout est en ordre', function (): void {
        // Un bandeau permanent cesse d'être lu. Il ne doit apparaître que
        // lorsqu'il y a quelque chose à corriger.
        config()->set('factcheck.fake_adapters', false);
        config()->set('app.debug', false);
        config()->set('app.url', 'https://verifall.com');

        $alertes = app(TableauDeBordPersonnel::class)->avertissements();

        expect($alertes)->toBe([]);
    });

    it("prévient qu'APP_URL manque, parce que le courriel en dépend", function (): void {
        // `MAIL_EHLO_DOMAIN` se déduit d'`APP_URL` : restée sur localhost, le
        // serveur s'annonce sous ce nom en SMTP et ses messages sont rejetés.
        config()->set('factcheck.fake_adapters', false);
        config()->set('app.url', 'http://localhost');

        $alertes = app(TableauDeBordPersonnel::class)->avertissements();

        expect($alertes)->toHaveCount(1)
            ->and($alertes[0]['texte'])->toContain('APP_URL');
    });
});

describe('les volumes', function (): void {
    it('ne compte QUE les abonnés confirmés et non désabonnés', function (): void {
        /*
         | Compter les adresses non confirmées gonflerait un chiffre que le
         | premier envoi démentirait publiquement — et sur une plateforme de
         | fact-checking, un chiffre faux affiché à l'équipe est le pire endroit
         | où commencer.
         */
        NewsletterSubscriber::query()->create([
            'uuid' => (string) Str::uuid(), 'email' => 'confirme@exemple.org',
            'confirmation_token' => Str::random(40), 'unsubscribe_token' => Str::random(40),
            'confirmed_at' => now(),
        ]);

        NewsletterSubscriber::query()->create([
            'uuid' => (string) Str::uuid(), 'email' => 'jamais-confirme@exemple.org',
            'confirmation_token' => Str::random(40), 'unsubscribe_token' => Str::random(40),
        ]);

        NewsletterSubscriber::query()->create([
            'uuid' => (string) Str::uuid(), 'email' => 'parti@exemple.org',
            'confirmation_token' => Str::random(40), 'unsubscribe_token' => Str::random(40),
            'confirmed_at' => now()->subMonth(), 'unsubscribed_at' => now(),
        ]);

        cache()->forget('staff.tableau-de-bord.volumes');

        $volumes = app(TableauDeBordPersonnel::class)->volumes();

        expect($volumes['abonnes'])->toBe(1);
    });
});

describe('la barre latérale', function (): void {
    it('ne contient AUCUN lien mort', function (): void {
        /*
         | Trente-sept entrées portaient `href="#"`. Au clic, la page sautait en
         | haut et rien ne se passait — indistinguable d'une panne, et signalé
         | comme telle en production.
         |
         | Dix d'entre elles avaient pourtant une destination qui EXISTAIT
         | (alertes, journal d'audit, signalements, paramètres, jobs vérifiés) :
         | elles n'avaient simplement jamais été reliées.
         |
         | Les autres désignent des écrans qui ne sont pas construits. On ne les
         | retire pas — la barre montre le périmètre prévu — mais un lien sans
         | destination n'est pas un lien : il perd son `href` et le dit.
         */
        $html = (string) personnelConnecte($this->membre)
            ->get(route('staff.dashboard'))->assertOk()->getContent();

        expect(substr_count($html, 'href="#"'))->toBe(0,
            'Une entrée de menu pointe vers « # » : au clic, rien ne se passe.');
    });

    it('relie bien les écrans qui EXISTENT', function (): void {
        $html = (string) personnelConnecte($this->membre)
            ->get(route('staff.dashboard'))->getContent();

        foreach ([
            'staff.alerts',
            'staff.audit',
            'staff.content-reports',
            'staff.settings',
            'staff.hub-jobs',
        ] as $route) {
            /*
             | Le CHEMIN et non l'URL absolue : `route()` appelé ici, hors
             | requête, se fonde sur `APP_URL`, tandis que la vue a été rendue
             | avec l'hôte de la requête. Comparer les deux ferait échouer le
             | test sur le poste de quiconque a renseigné `APP_URL` — sans que
             | rien n'ait changé dans le code.
             */
            /*
             | `str_contains` et non `toContain` : ce dernier accepte PLUSIEURS
             | aiguilles, si bien qu'un message passé en second argument est
             | cherché dans le HTML au lieu d'être affiché. Le test échouait en
             | annonçant qu'il ne trouvait pas son propre message d'erreur.
             */
            expect(str_contains($html, route($route, absolute: false)))->toBeTrue(
                "La barre latérale ne mène pas à {$route}, qui existe pourtant.");
        }
    });

    it('ne porte plus AUCUNE entrée « à venir »', function (): void {
        /*
         | ## Ce test disait l'inverse, et il avait raison à l'époque
         |
         | Il exigeait la PRÉSENCE de `is-a-venir` : trente-deux entrées du menu
         | annonçaient un écran non construit, et le principe posé alors tient
         | toujours — « un écran manquant est un fait ; un lien qui fait semblant
         | est un mensonge ».
         |
         | Les trente-deux écrans ont été livrés (lots 5 à 10). La prémisse a donc
         | expiré, pas l'intention : elle se retourne. Plus rien n'est « à
         | venir », donc plus aucun lien ne doit être mort.
         |
         | Le test qui précède vérifie déjà que chaque route liée existe. Celui-ci
         | ferme l'autre moitié : aucune entrée désactivée ne subsiste, et aucune
         | entrée du menu latéral n'est dépourvue de destination.
         */
        $gabarit = (string) file_get_contents(
            resource_path('views/components/layouts/staff.blade.php')
        );

        expect($gabarit)->not->toContain('is-a-venir')
            ->and($gabarit)->not->toContain('data-todo=');

        /*
         | Et pas un seul `sidebar-link` sans `href`. Le test lit le FICHIER et
         | non une page rendue, pour attraper aussi les entrées cachées derrière
         | un `@can` — même précaution qu'au test anti-`citizen.` du lot 2.
         */
        preg_match_all('/<a\b[^>]*class="sidebar-link[^"]*"[^>]*>/', $gabarit, $liens);

        $sansDestination = array_values(array_filter(
            $liens[0],
            static fn (string $lien): bool => ! str_contains($lien, 'href='),
        ));

        expect($sansDestination)->toBe([],
            "Entrées du menu sans destination :\n  ".implode("\n  ", $sansDestination));
    });
});
