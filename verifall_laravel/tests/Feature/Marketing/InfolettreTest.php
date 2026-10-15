<?php

declare(strict_types=1);

use App\Console\Commands\EnvoyerInfolettreHebdomadaire;
use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Marketing\Enums\StatutCampagne;
use App\Core\Marketing\Services\Infolettre as ServiceInfolettre;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Http\Controllers\Public\AbonnementController;
use App\Infrastructure\Persistence\DepotAbonnesEloquent;
use App\Livewire\Staff\Infolettre;
use App\Mail\ConfirmationAbonnement;
use App\Mail\RecapitulatifHebdomadaire;
use App\Models\Campaign;
use App\Models\HubArticle;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| L'infolettre du lundi
|--------------------------------------------------------------------------
| Ce qui est éprouvé ici n'est pas « le courriel part ». C'est :
|
|   · qu'une adresse NON confirmée ne reçoit rien — le double opt-in tient ;
|   · que le formulaire répond la même chose à une adresse connue et à une
|     inconnue — il ne sert pas à savoir qui est abonné ;
|   · qu'on se désabonne sans compte, et qu'un lien retouché est refusé ;
|   · que la commande rejouée le même lundi n'envoie pas deux fois ;
|   · qu'une semaine sans publication n'envoie rien, et le DIT ;
|   · que chaque courriel porte son lien de sortie — vérifié dans le corps
|     rendu, pas dans l'intention du code.
|
| ── Deux échafaudages, et pourquoi ils sont là ────────────────────────────
|
| `routes/web.php` et `app/Providers/AppServiceProvider.php` sont VERROUILLÉS
| pendant ce chantier parallèle : quatre agents qui y écrivent en même temps
| produisent quatre versions incompatibles. Les routes et la liaison du dépôt
| y seront posées par l'intégration ; en attendant, ce fichier les déclare
| lui-même — et seulement si elles manquent, de sorte qu'il continue de
| fonctionner tel quel une fois l'intégration faite.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Liaison du dépôt — une ligne d'AppServiceProvider, cf. compte rendu.
    if (! app()->bound(DepotAbonnes::class)) {
        app()->bind(DepotAbonnes::class, DepotAbonnesEloquent::class);
    }

    // Les trois routes publiques de l'infolettre, telles qu'elles doivent
    // être posées dans routes/web.php.
    if (! Route::has('public.newsletter.subscribe')) {
        Route::post('/infolettre/abonnement', [AbonnementController::class, 'souscrire'])
            ->middleware('throttle:6,1')->name('public.newsletter.subscribe');

        Route::get('/infolettre/confirmation/{jeton}', [AbonnementController::class, 'confirmer'])
            ->middleware('throttle:30,1')->name('public.newsletter.confirm');

        Route::get('/infolettre/desabonnement/{uuid}', [AbonnementController::class, 'desabonner'])
            ->middleware('signed')->name('public.newsletter.unsubscribe');

        /*
         | Indispensable, et le piège n'est pas évident : `->name()` s'applique
         | à la route APRÈS son ajout à la collection. La table des noms n'est
         | donc pas à jour, et `route('public.newsletter.confirm')` lèverait
         | « Route not defined » alors que la route répond parfaitement en HTTP.
         | Le noyau fait ce rafraîchissement une fois les fichiers de routes
         | chargés ; ici, c'est à nous de le faire.
         */
        Route::getRoutes()->refreshNameLookups();
    }
});

/** Un abonné, à l'état voulu. */
function abonne(array $attributs = []): NewsletterSubscriber
{
    return NewsletterSubscriber::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'email' => 'abonne'.random_int(1000, 9999).'@example.com',
        'name' => 'Aïcha',
        'confirmation_token' => bin2hex(random_bytes(32)),
        'unsubscribe_token' => bin2hex(random_bytes(32)),
        'confirmed_at' => now(),
    ], $attributs));
}

/** Une brève du Hub, publiée — la matière du récapitulatif. */
function breve(array $attributs = []): HubArticle
{
    $n = random_int(1000, 9999);

    return HubArticle::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'taxe-whatsapp-'.$n,
        'title' => 'La rumeur « taxe WhatsApp » démentie',
        'excerpt' => "Aucun texte n'instaure de taxe sur WhatsApp au Bénin.",
        'category' => 'Économie',
        'verdict' => VerdictFactCheck::Faux,
        'is_published' => true,
        'published_at' => now()->subDay(),
    ], $attributs));
}

describe('abonnement et double opt-in', function (): void {

    it('crée un abonné NON confirmé et lui envoie une confirmation', function (): void {
        Mail::fake();

        $this->post(route('public.newsletter.subscribe'), ['email' => 'rene@example.com'])
            ->assertRedirect()
            ->assertSessionHas('infolettre', 'verifiez_votre_boite');

        $abonne = NewsletterSubscriber::where('email', 'rene@example.com')->first();

        expect($abonne)->not->toBeNull()
            // Le point entier du double opt-in : la ligne existe, elle ne
            // reçoit rien tant que personne n'a cliqué.
            ->and($abonne->confirmed_at)->toBeNull()
            ->and($abonne->estDestinataire())->toBeFalse();

        Mail::assertQueued(ConfirmationAbonnement::class);
    });

    it('normalise l\'adresse : deux casses ne font pas deux abonnés', function (): void {
        Mail::fake();

        $this->post(route('public.newsletter.subscribe'), ['email' => 'Rene@Example.COM']);
        $this->post(route('public.newsletter.subscribe'), ['email' => '  rene@example.com ']);

        expect(NewsletterSubscriber::count())->toBe(1);
    });

    it('répond EXACTEMENT la même chose pour une adresse nouvelle et une adresse déjà abonnée', function (): void {
        Mail::fake();

        abonne(['email' => 'deja@example.com']);

        $nouvelle = $this->post(route('public.newsletter.subscribe'), ['email' => 'inconnue@example.com']);
        $connue = $this->post(route('public.newsletter.subscribe'), ['email' => 'deja@example.com']);

        /*
         | Statut, destination et message identiques. Sans cette égalité, le
         | formulaire devient un annuaire : on y saisit l'adresse de quelqu'un
         | pour savoir s'il lit Vérif'All — le même défaut qu'un écran de
         | connexion qui répond « ce compte n'existe pas ».
         */
        expect($connue->getStatusCode())->toBe($nouvelle->getStatusCode())
            ->and($connue->headers->get('Location'))->toBe($nouvelle->headers->get('Location'));

        $connue->assertSessionHas('infolettre', 'verifiez_votre_boite');
        $nouvelle->assertSessionHas('infolettre', 'verifiez_votre_boite');
    });

    it('n\'envoie AUCUN courriel de plus à une adresse déjà confirmée', function (): void {
        Mail::fake();

        abonne(['email' => 'deja@example.com']);

        $this->post(route('public.newsletter.subscribe'), ['email' => 'deja@example.com']);

        // Sinon le formulaire public devient une machine à inonder la boîte
        // d'un tiers : il suffirait de le soumettre en boucle.
        Mail::assertNothingOutgoing();
    });

    it('confirme l\'abonnement par le jeton reçu', function (): void {
        $enAttente = abonne(['confirmed_at' => null]);

        $this->get(route('public.newsletter.confirm', ['jeton' => $enAttente->confirmation_token]))
            ->assertRedirect(route('public.home'))
            ->assertSessionHas('infolettre', 'confirmee');

        expect($enAttente->refresh()->estDestinataire())->toBeTrue();
    });

    it('ne casse pas sur un jeton inconnu — il le dit', function (): void {
        $this->get(route('public.newsletter.confirm', ['jeton' => 'jeton-recopie-de-travers']))
            ->assertRedirect(route('public.home'))
            ->assertSessionHas('infolettre', 'lien_invalide');
    });

    it('réarme le double opt-in quand une adresse désabonnée revient', function (): void {
        Mail::fake();

        $parti = abonne(['email' => 'revenu@example.com', 'unsubscribed_at' => now()]);
        $ancienJeton = $parti->confirmation_token;

        $this->post(route('public.newsletter.subscribe'), ['email' => 'revenu@example.com']);

        $parti->refresh();

        expect($parti->confirmation_token)->not->toBe($ancienJeton)
            ->and($parti->confirmed_at)->toBeNull()
            ->and($parti->unsubscribed_at)->toBeNull();

        Mail::assertQueued(ConfirmationAbonnement::class);
    });
});

describe('désabonnement', function (): void {

    it('fonctionne SANS connexion, par lien signé', function (): void {
        $inscrit = abonne();

        $lien = app(ServiceInfolettre::class)->lienDeDesabonnement($inscrit);

        // Aucun `actingAs` : c'est tout l'enjeu. Le compte n'existe peut-être
        // même pas — l'infolettre s'adresse aussi à qui n'est pas inscrit.
        $this->get($lien)
            ->assertRedirect(route('public.home'))
            ->assertSessionHas('infolettre', 'desabonnee');

        expect($inscrit->refresh()->estDestinataire())->toBeFalse()
            // La preuve du consentement passé est CONSERVÉE : c'est elle qui
            // répond à « vous m'avez écrit sans mon accord ».
            ->and($inscrit->confirmed_at)->not->toBeNull();
    });

    it('refuse un lien altéré', function (): void {
        $victime = abonne();
        $autre = abonne();

        $lien = app(ServiceInfolettre::class)->lienDeDesabonnement($autre);

        // L'identifiant remplacé par celui de quelqu'un d'autre, la signature
        // restant celle de l'original.
        $falsifie = str_replace($autre->uuid, $victime->uuid, $lien);

        $this->get($falsifie)->assertForbidden();

        expect($victime->refresh()->estDestinataire())->toBeTrue();
    });

    it('refuse un lien sans signature du tout', function (): void {
        $inscrit = abonne();

        $this->get('/infolettre/desabonnement/'.$inscrit->uuid)->assertForbidden();

        expect($inscrit->refresh()->estDestinataire())->toBeTrue();
    });
});

describe('récapitulatif hebdomadaire', function (): void {

    it('n\'envoie RIEN à une adresse non confirmée', function (): void {
        Mail::fake();
        breve();

        abonne(['email' => 'confirme@example.com']);
        abonne(['email' => 'en-attente@example.com', 'confirmed_at' => null]);
        abonne(['email' => 'parti@example.com', 'unsubscribed_at' => now()]);

        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);

        Mail::assertSent(RecapitulatifHebdomadaire::class, fn ($mail): bool => $mail->hasTo('confirme@example.com'));
        Mail::assertNotSent(RecapitulatifHebdomadaire::class, fn ($mail): bool => $mail->hasTo('en-attente@example.com'));
        Mail::assertNotSent(RecapitulatifHebdomadaire::class, fn ($mail): bool => $mail->hasTo('parti@example.com'));
    });

    it('rejouée le même jour, n\'envoie pas deux fois', function (): void {
        Mail::fake();
        breve();
        abonne(['email' => 'unique@example.com']);

        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);
        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);
        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);

        Mail::assertSentCount(1);

        // Une seule édition, une seule trace : la contrainte d'unicité de
        // `newsletter_deliveries` a fait son travail.
        expect(Campaign::whereNotNull('edition_key')->count())->toBe(1)
            ->and(NewsletterDelivery::count())->toBe(1);
    });

    it('reprend un envoi interrompu là où il s\'est arrêté', function (): void {
        Mail::fake();
        breve();

        $servi = abonne(['email' => 'deja-servi@example.com']);
        $oublie = abonne(['email' => 'jamais-servi@example.com']);

        // On simule une coupure : l'édition existe, le premier a reçu, le
        // second non, et la campagne est restée « en cours ».
        $infolettre = app(ServiceInfolettre::class);
        $depot = app(DepotAbonnes::class);
        $campagne = $infolettre->ouvrirEdition(now(), $infolettre->factChecksDeLaSemaine(now()));
        $depot->reserverEnvoi($campagne, $servi);
        $depot->marquerEnvoye($campagne, $servi);
        $depot->recalerCompteurs($campagne, 2, StatutCampagne::EnCours->value);

        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);

        Mail::assertSentCount(1);
        Mail::assertSent(RecapitulatifHebdomadaire::class, fn ($mail): bool => $mail->hasTo($oublie->email));

        $campagne->refresh();

        expect($campagne->sent_count)->toBe(2)
            ->and($campagne->failed_count)->toBe(0)
            ->and(StatutCampagne::depuis($campagne->status))->toBe(StatutCampagne::Envoyee);
    });

    it('n\'envoie rien quand aucun fact-check n\'a été publié — et le signale', function (): void {
        Mail::fake();
        abonne();

        // Publiée il y a trois semaines : hors fenêtre. Le silence n'est donc
        // pas dû à une base vide, mais à une semaine sans publication.
        breve(['published_at' => now()->subWeeks(3)]);

        $this->artisan('verifall:infolettre-hebdomadaire')
            ->expectsOutputToContain('Aucune brève publiée')
            // Ni 0 ni 1 : rien n'est parti, mais rien n'est cassé. Une
            // supervision doit pouvoir distinguer les trois.
            ->assertExitCode(EnvoyerInfolettreHebdomadaire::RIEN_A_DIRE);

        Mail::assertNothingOutgoing();
        expect(Campaign::whereNotNull('edition_key')->count())->toBe(0);
    });

    it('ignore les brèves NON publiées', function (): void {
        Mail::fake();
        abonne();
        breve(['is_published' => false]);

        $this->artisan('verifall:infolettre-hebdomadaire')
            ->assertExitCode(EnvoyerInfolettreHebdomadaire::RIEN_A_DIRE);

        Mail::assertNothingOutgoing();
    });

    it('n\'écrit rien en essai à blanc', function (): void {
        Mail::fake();
        breve();
        abonne();

        $this->artisan('verifall:infolettre-hebdomadaire', ['--dry-run' => true])->assertExitCode(0);

        Mail::assertNothingOutgoing();
        expect(Campaign::count())->toBe(0)
            ->and(NewsletterDelivery::count())->toBe(0);
    });

    it('renseigne les TROIS compteurs de la campagne', function (): void {
        Mail::fake();
        breve();
        abonne();
        abonne();

        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);

        $campagne = Campaign::whereNotNull('edition_key')->firstOrFail();

        expect($campagne->recipients_count)->toBe(2)
            ->and($campagne->sent_count)->toBe(2)
            // Zéro s'affiche : une campagne qui ne montrerait que ses succès
            // laisserait croire qu'elle a touché tout le monde.
            ->and($campagne->failed_count)->toBe(0)
            ->and($campagne->sent_at)->not->toBeNull();
    });

    it('compte un échec sans interrompre l\'édition', function (): void {
        breve();
        $casse = abonne(['email' => 'boite-pleine@example.com']);
        abonne(['email' => 'ok@example.com']);

        // Le transport lève sur une adresse et une seule : une boîte pleine ne
        // doit pas priver le reste de la liste.
        Mail::shouldReceive('to')->andReturnUsing(function (string $adresse) use ($casse) {
            $pending = Mockery::mock();
            $pending->shouldReceive('send')->andReturnUsing(function () use ($adresse, $casse): void {
                if ($adresse === $casse->email) {
                    throw new RuntimeException('550 mailbox full');
                }
            });

            return $pending;
        });

        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);

        $campagne = Campaign::whereNotNull('edition_key')->firstOrFail();

        expect($campagne->sent_count)->toBe(1)
            ->and($campagne->failed_count)->toBe(1)
            ->and(NewsletterDelivery::whereNotNull('failed_at')->first()?->error)
            ->toContain('mailbox full');
    });
});

describe('lien de désabonnement dans les courriels', function (): void {

    it('est présent dans le CORPS RENDU du récapitulatif', function (): void {
        Mail::fake();
        breve();
        $destinataire = abonne(['email' => 'lecteur@example.com']);

        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);

        Mail::assertSent(RecapitulatifHebdomadaire::class, function ($mail) use ($destinataire): bool {
            // Le corps rendu, pas l'intention : c'est la seule vérification qui
            // survive à un oubli dans le gabarit.
            $corps = $mail->render();

            return str_contains($corps, '/infolettre/desabonnement/'.$destinataire->uuid)
                && str_contains($corps, 'Se désabonner');
        });
    });

    it('est présent dans le CORPS RENDU de la confirmation', function (): void {
        Mail::fake();

        $this->post(route('public.newsletter.subscribe'), ['email' => 'nouveau@example.com']);

        $inscrit = NewsletterSubscriber::where('email', 'nouveau@example.com')->firstOrFail();

        Mail::assertQueued(ConfirmationAbonnement::class, function ($mail) use ($inscrit): bool {
            $corps = $mail->render();

            return str_contains($corps, '/infolettre/desabonnement/'.$inscrit->uuid)
                && str_contains($corps, '/infolettre/confirmation/'.$inscrit->confirmation_token);
        });
    });

    it('annonce le désabonnement en un clic aux clients de messagerie', function (): void {
        $lien = app(ServiceInfolettre::class)->lienDeDesabonnement(abonne());

        $entetes = (new RecapitulatifHebdomadaire(
            objet: 'Objet', articles: collect(), lienDeDesabonnement: $lien,
            nomDuSite: "Vérif'All", periode: 'du 1 au 8 août',
        ))->headers();

        // C'est cet en-tête qui fait apparaître « Se désabonner » à côté de
        // l'expéditeur dans Gmail — le chemin le plus court hors de la liste,
        // et donc le meilleur rempart contre le bouton « indésirable ».
        expect($entetes->text)->toHaveKey('List-Unsubscribe')
            ->and($entetes->text['List-Unsubscribe'])->toContain('/infolettre/desabonnement/');
    });
});

describe('écran d\'administration', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->create(['otp_verified_at' => now()]);
        $this->admin->assignRole(UserRole::Admin->value);
    });

    it('refuse un mentor', function (): void {
        $mentor = User::factory()->create(['otp_verified_at' => now()]);
        $mentor->assignRole(UserRole::Mentor->value);

        // La liste d'abonnés est un fichier d'adresses : c'est la donnée la
        // plus facilement exfiltrable de la plateforme.
        Livewire::actingAs($mentor, 'staff')->test(Infolettre::class)->assertForbidden();
    });

    /*
     | Adaptés au passage de l'onglet « Abonnés » sous DataTables (14 septembre
     | 2026). Ces deux tests lisaient la liste dans le rendu Livewire ; elle
     | n'y est plus — elle arrive par `staff.newsletter.data`. Ils vérifient
     | donc la même chose là où elle se trouve désormais : les indicateurs dans
     | le rendu, les adresses et leurs états dans la source JSON. Le détail
     | (jetons, tri, pagination, garde) est dans `TableDesAbonnesTest`.
     */
    it('montre les abonnés et leurs états à un administrateur', function (): void {
        abonne(['email' => 'confirme@example.com']);
        abonne(['email' => 'attente@example.com', 'confirmed_at' => null]);

        Livewire::actingAs($this->admin, 'staff')->test(Infolettre::class)
            ->assertOk()
            ->assertSee('data-vdt="abonnes"', false)
            ->assertSee('En attente de confirmation');

        $etats = collect($this->actingAs($this->admin, 'staff')
            ->getJson(route('staff.newsletter.data'))
            ->assertOk()
            ->json('data'))
            ->pluck('etat', 'email')
            ->all();

        expect($etats)->toBe(['attente@example.com' => 'en_attente', 'confirme@example.com' => 'confirme']);
    });

    it('filtre sur l\'état de confirmation', function (): void {
        abonne(['email' => 'confirme@example.com']);
        abonne(['email' => 'attente@example.com', 'confirmed_at' => null]);

        $adresses = $this->actingAs($this->admin, 'staff')
            ->getJson(route('staff.newsletter.data', ['filtres' => ['etat' => 'en-attente']]))
            ->assertOk()
            ->json('data.*.email');

        expect($adresses)->toBe(['attente@example.com']);
    });

    it('affiche les trois compteurs d\'un envoi, échecs compris', function (): void {
        Mail::fake();
        breve();
        abonne();

        $this->artisan('verifall:infolettre-hebdomadaire')->assertExitCode(0);

        Livewire::actingAs($this->admin, 'staff')->test(Infolettre::class)
            ->call('changerOnglet', 'historique')
            ->assertSee('Destinataires')
            ->assertSee('Envoyés')
            // Les échecs ne se masquent pas quand ils valent zéro : une colonne
            // qui disparaît quand tout va bien ne s'apprend pas.
            ->assertSee('Échoués');
    });

    it('permet de désabonner une adresse à la demande, et le journalise', function (): void {
        $inscrit = abonne();

        Livewire::actingAs($this->admin, 'staff')->test(Infolettre::class)
            ->call('retirer', $inscrit->uuid);

        expect($inscrit->refresh()->estDestinataire())->toBeFalse();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'newsletter',
            'target_label' => $inscrit->email,
        ]);
    });
});

describe('bloc public d\'abonnement', function (): void {

    /*
     | `$errors` est partagé par le middleware de session sur toute requête web ;
     | hors requête, il n'existe pas. On le fournit donc explicitement, ce qui
     | reproduit l'état exact d'une page servie.
     */
    beforeEach(fn () => View::share('errors', new ViewErrorBag));

    it('rend un formulaire qui poste vers la bonne route', function (): void {
        $rendu = Blade::render('<x-infolettre-abonnement />');

        expect($rendu)->toContain(route('public.newsletter.subscribe'))
            ->toContain('name="email"')
            // L'ancre que la campagne « newsletter-boom » vise depuis toujours,
            // et qui n'existait nulle part.
            ->toContain('id="newsletter-section"')
            // Dit AVANT l'envoi : savoir comment sortir fait entrer.
            ->toContain('lien de désabonnement');
    });

    it('affiche le retour de l\'inscription sans trahir l\'existence d\'une adresse', function (): void {
        session()->put('infolettre', 'verifiez_votre_boite');

        $rendu = Blade::render('<x-infolettre-abonnement />');

        /*
         | « Si cette adresse peut recevoir… » — le conditionnel n'est pas une
         | coquetterie : c'est la seule formulation qui vaille pour une adresse
         | nouvelle comme pour une adresse déjà inscrite.
         */
        expect($rendu)->toContain('Si cette adresse peut recevoir');
    });
});
