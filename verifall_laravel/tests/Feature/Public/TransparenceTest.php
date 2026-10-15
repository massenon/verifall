<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotCorrections;
use App\Core\FactChecking\Services\GestionDesFaitsVerifies;
use App\Core\FactChecking\Services\JournalDesCorrections;
use App\Core\FactChecking\Services\RaisonDeCorrectionManquante;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Infrastructure\Persistence\DepotCorrectionsEloquent;
use App\Livewire\Public\Corrections;
use App\Livewire\Public\Methodologie;
use App\Livewire\Staff\FaitsVerifies;
use App\Models\HubArticle;
use App\Models\User;
use App\Models\VerdictRevision;
use App\Models\VerifiedFact;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Transparence IFCN — corrections, méthodologie, financement
|--------------------------------------------------------------------------
|
| Ce que ces tests protègent tient en une phrase : QU'UN VERDICT NE PUISSE PAS
| CHANGER EN SILENCE.
|
| Le reste — trois pages qui se rendent — est vérifiable à l'œil. La garantie
| qui ne l'est pas, c'est qu'aucun chemin de code n'écrit un nouveau verdict
| sans écrire aussi pourquoi. Elle ne se relit pas : elle se teste.
|
| Les trois routes vivent dans `routes/web.php`, verrouillé pendant le chantier
| parallèle. Tant que l'intégration ne les a pas posées, elles sont déclarées
| ici — et dès qu'elles existent pour de bon, ce sont ELLES qui sont
| exercées. Le garde `Route::has()` fait la bascule tout seul.
*/

beforeEach(function (): void {
    if (! app()->bound(DepotCorrections::class)) {
        app()->bind(DepotCorrections::class, DepotCorrectionsEloquent::class);
    }

    if (! Route::has('public.corrections')) {
        Route::middleware('web')->name('public.')->group(function (): void {
            Route::get('corrections', Corrections::class)->name('corrections');
            Route::get('methodologie', Methodologie::class)->name('methodology');
            Route::view('financement', 'public.financement', [
                'title' => "Financement — Vérif'All",
            ])->name('funding');
        });

        // Le tableau des noms est construit au chargement des routes : des
        // routes ajoutées après coup restent introuvables par `route()` tant
        // qu'on ne le reconstruit pas.
        Route::getRoutes()->refreshNameLookups();
    }
});

function unRedacteur(): User
{
    return User::factory()->create(['otp_verified_at' => now()]);
}

function unFaitTranche(array $attributs = []): VerifiedFact
{
    return VerifiedFact::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'title' => 'Une taxe WhatsApp entre en vigueur au Bénin lundi',
        'summary' => '<p>Aucun texte réglementaire ne l\'établit.</p>',
        'verdict' => VerdictFactCheck::Faux->value,
        'keywords' => 'taxe whatsapp benin',
        'sources' => [['url' => 'https://arcep.bj/communique', 'name' => 'ARCEP Bénin']],
        'is_published' => true,
    ], $attributs));
}

function uneVerificationPubliee(array $attributs = []): HubArticle
{
    return HubArticle::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'une-rumeur-corrigee',
        'title' => 'Une rumeur corrigée',
        'excerpt' => 'Ce que dit la rumeur, et ce que disent les faits.',
        'body' => '<p>Le corps de la vérification.</p>',
        'category' => 'sante',
        'verdict' => VerdictFactCheck::Vrai->value,
        'is_published' => true,
        'published_at' => now()->subDay(),
    ], $attributs));
}

describe('le journal des corrections s\'écrit tout seul', function (): void {
    it('consigne la correction dès que le verdict change, sans qu\'on ait à le demander', function (): void {
        $fait = unFaitTranche();
        $redacteur = unRedacteur();

        app(GestionDesFaitsVerifies::class)->mettreAJour(
            $redacteur, $fait,
            'Une taxe WhatsApp entre en vigueur au Bénin lundi',
            VerdictFactCheck::Trompeur,
            '<p>Une redevance existe, mais elle ne porte pas sur WhatsApp.</p>',
            [['url' => 'https://arcep.bj/communique', 'name' => 'ARCEP']],
            'taxe whatsapp benin',
            'Un arrêté publié depuis établit une redevance sur les services de données, distincte de la rumeur.',
        );

        $revision = VerdictRevision::sole();

        expect($revision->ancien_verdict)->toBe(VerdictFactCheck::Faux)
            ->and($revision->nouveau_verdict)->toBe(VerdictFactCheck::Trompeur)
            ->and($revision->raison)->toContain('redevance sur les services de données')
            ->and($revision->auteur_id)->toBe($redacteur->id)
            ->and($revision->revisable_id)->toBe($fait->id)
            ->and($revision->revisable_type)->toBe(VerifiedFact::class)
            // L'affirmation est FIGÉE : le journal doit rester lisible même si
            // le contenu d'origine est réécrit ou supprimé plus tard.
            ->and($revision->affirmation)->toBe('Une taxe WhatsApp entre en vigueur au Bénin lundi');
    });

    it('REFUSE le changement de verdict quand la raison manque — et n\'écrit pas le verdict non plus', function (): void {
        $fait = unFaitTranche();

        expect(fn () => app(GestionDesFaitsVerifies::class)->mettreAJour(
            unRedacteur(), $fait,
            $fait->title, VerdictFactCheck::Vrai,
            '<p>Analyse révisée.</p>',
            [], 'taxe whatsapp benin',
            '   ', // des espaces ne sont pas une raison
        ))->toThrow(RaisonDeCorrectionManquante::class);

        /*
         | LE POINT DU TEST. Le refus ne doit pas être un simple message : la
         | transaction est annulée, donc le verdict reste celui d'avant. Sans
         | cela, on obtiendrait un verdict corrigé sans entrée au journal —
         | exactement ce que cette table existe pour empêcher.
         */
        expect($fait->refresh()->verdict)->toBe(VerdictFactCheck::Faux)
            ->and(VerdictRevision::count())->toBe(0);
    });

    it('ne consigne rien quand le verdict ne bouge pas', function (): void {
        $fait = unFaitTranche();

        app(GestionDesFaitsVerifies::class)->mettreAJour(
            unRedacteur(), $fait,
            'Une taxe WhatsApp entre en vigueur au Bénin lundi — formulation corrigée',
            VerdictFactCheck::Faux,
            '<p>Analyse enrichie, verdict inchangé.</p>',
            [], 'taxe whatsapp benin',
        );

        // Une correction de forme n'est pas une correction de verdict : la
        // noyer dans le journal le rendrait illisible, donc inutile.
        expect(VerdictRevision::count())->toBe(0)
            ->and($fait->refresh()->title)->toContain('formulation corrigée');
    });

    it('refuse une raison vide jusque dans le modèle, quel que soit le chemin emprunté', function (): void {
        $fait = unFaitTranche();

        expect(fn () => VerdictRevision::create([
            'uuid' => (string) Str::uuid(),
            'revisable_type' => VerifiedFact::class,
            'revisable_id' => $fait->id,
            'affirmation' => $fait->title,
            'ancien_verdict' => VerdictFactCheck::Faux->value,
            'nouveau_verdict' => VerdictFactCheck::Vrai->value,
            'raison' => '',
        ]))->toThrow(RaisonDeCorrectionManquante::class);

        expect(VerdictRevision::count())->toBe(0);
    });

    it('garde l\'historique complet : deux corrections successives, la plus récente en tête', function (): void {
        $fait = unFaitTranche();
        $gestion = app(GestionDesFaitsVerifies::class);
        $redacteur = unRedacteur();

        $gestion->mettreAJour(
            $redacteur, $fait, $fait->title, VerdictFactCheck::Trompeur,
            '<p>Première révision.</p>', [], '',
            'Première correction : la rumeur mélange deux textes réglementaires distincts.',
        );

        $gestion->mettreAJour(
            $redacteur, $fait->refresh(), $fait->title, VerdictFactCheck::Vrai,
            '<p>Seconde révision.</p>', [], '',
            'Seconde correction : le texte est finalement entré en vigueur à la date annoncée.',
        );

        $historique = app(JournalDesCorrections::class)->historiqueDe($fait->refresh());

        expect($historique)->toHaveCount(2)
            ->and($historique->first()->nouveau_verdict)->toBe(VerdictFactCheck::Vrai)
            ->and($historique->last()->ancien_verdict)->toBe(VerdictFactCheck::Faux);
    });
});

describe('l\'écran de rédaction exige la raison avant d\'écrire', function (): void {
    beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['otp_verified_at' => now()]);
        $this->admin->assignRole(UserRole::Admin->value);

        $this->actingAs($this->admin, 'staff')
            ->withSession(['staff.last_activity' => now()->timestamp]);
    });

    it('bloque l\'enregistrement d\'un verdict changé sans motif, avec un message sous le champ', function (): void {
        $fait = unFaitTranche();

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirEdition', $fait->uuid)
            ->set('verdict', VerdictFactCheck::Vrai->value)
            ->call('enregistrer')
            ->assertHasErrors(['raisonDeLaCorrection']);

        expect($fait->refresh()->verdict)->toBe(VerdictFactCheck::Faux)
            ->and(VerdictRevision::count())->toBe(0);
    });

    it('laisse passer une modification qui ne touche pas au verdict', function (): void {
        $fait = unFaitTranche();

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirEdition', $fait->uuid)
            ->set('affirmation', 'Une taxe WhatsApp au Bénin — titre reformulé')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($fait->refresh()->title)->toContain('titre reformulé');
    });

    it('accepte la correction motivée et la publie au journal', function (): void {
        $fait = unFaitTranche();

        Livewire::test(FaitsVerifies::class)
            ->call('ouvrirEdition', $fait->uuid)
            ->set('verdict', VerdictFactCheck::Trompeur->value)
            ->set('raisonDeLaCorrection', 'Un arrêté publié depuis nuance la rumeur sans la confirmer.')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($fait->refresh()->verdict)->toBe(VerdictFactCheck::Trompeur)
            ->and(VerdictRevision::sole()->raison)->toContain('arrêté publié depuis');
    });
});

describe('la page publique des corrections', function (): void {
    it('affiche l\'ancien verdict, le nouveau, la raison et la date', function (): void {
        $verification = uneVerificationPubliee();

        app(JournalDesCorrections::class)->consigner(
            $verification,
            'Le concours de la fonction publique a été annulé',
            VerdictFactCheck::Vrai,
            VerdictFactCheck::Faux,
            'Le communiqué que nous avions retenu était un faux, désavoué par le ministère le lendemain.',
        );

        $this->get(route('public.corrections'))
            ->assertOk()
            ->assertSee('Le concours de la fonction publique a été annulé')
            ->assertSee('Le communiqué que nous avions retenu était un faux')
            // L'ancien verdict reste LISIBLE, seulement barré : c'est toute la
            // différence entre corriger et réécrire.
            ->assertSee('verdict-ancien', escape: false)
            ->assertSee('Vrai')
            ->assertSee('Faux')
            // Et la page renvoie vers la vérification corrigée.
            ->assertSee(route('public.fact', $verification), escape: false);
    });

    it('montre la correction la plus récente en premier', function (): void {
        $journal = app(JournalDesCorrections::class);
        $fait = unFaitTranche();

        $journal->consigner($fait, 'La plus ancienne affirmation corrigée', VerdictFactCheck::Vrai, VerdictFactCheck::Faux, 'Motif de la première correction.');
        $this->travel(1)->days();
        $journal->consigner($fait, 'La plus récente affirmation corrigée', VerdictFactCheck::Faux, VerdictFactCheck::Trompeur, 'Motif de la seconde correction.');

        $html = (string) $this->get(route('public.corrections'))->getContent();

        expect(strpos($html, 'La plus récente affirmation corrigée'))
            ->toBeLessThan(strpos($html, 'La plus ancienne affirmation corrigée'));
    });

    it('pagine plutôt que de tout rendre', function (): void {
        $fait = unFaitTranche();
        $journal = app(JournalDesCorrections::class);

        for ($i = 1; $i <= 17; $i++) {
            $journal->consigner(
                $fait, 'Affirmation corrigée numéro '.$i,
                VerdictFactCheck::Vrai, VerdictFactCheck::Faux,
                'Motif détaillé de la correction numéro '.$i.'.',
            );
        }

        $html = (string) $this->get(route('public.corrections'))->assertOk()->getContent();

        // Quinze par page, et la seizième correction n'est pas rendue : une
        // page qui grossit indéfiniment finit par ne plus se charger du tout.
        expect(substr_count($html, 'wire:key="correction-'))->toBe(15)
            ->and($html)->toContain('Affirmation corrigée numéro 17');
    });

    it('dit qu\'il est vide sans se féliciter de l\'être', function (): void {
        /*
         | `escape: false` datait du temps ou la phrase etait du HTML statique,
         | apostrophe comprise. Elle passe maintenant par `{{ __(...) }}`, qui
         | l'echappe. En retirant le drapeau, `assertSee` echappe l'attendu
         | exactement comme Blade echappe le rendu : on verifie toujours la
         | meme phrase, a la meme place.
         */
        $this->get(route('public.corrections'))
            ->assertOk()
            ->assertSee("Aucun verdict n'a encore été corrigé.");
    });

    it('signale la correction sur la page de la vérification elle-même', function (): void {
        $verification = uneVerificationPubliee();

        app(JournalDesCorrections::class)->consigner(
            $verification, $verification->title,
            VerdictFactCheck::Vrai, VerdictFactCheck::Faux,
            'La source que nous avions citée s\'est révélée être un montage.',
        );

        $this->get(route('public.fact', $verification))
            ->assertOk()
            ->assertSee('Cette vérification a été corrigée')
            ->assertSee('révélée être un montage')
            ->assertSee(route('public.corrections'), escape: false);
    });

    it('ne montre rien de tel sur une vérification jamais corrigée', function (): void {
        $this->get(route('public.fact', uneVerificationPubliee()))
            ->assertOk()
            ->assertDontSee('Cette vérification a été corrigée');
    });
});

describe('la page méthodologie dit ce que fait le code', function (): void {
    it('publie la pondération RÉELLE, lue dans la configuration', function (): void {
        // Une pondération changée doit changer la page : c'est la garantie
        // qu'elle n'est pas une plaquette figée dans du HTML.
        config(['factcheck.weights.google_factcheck' => 0.42]);

        $this->get(route('public.methodology'))
            ->assertOk()
            ->assertSee('Fact-checks antérieurs')
            ->assertSee('0,42');
    });

    it('publie les seuils qui mènent à « non vérifiable »', function (): void {
        config([
            'factcheck.decision.ecart_minimum' => 31,
            'factcheck.decision.score_minimum' => 67,
            'factcheck.decision.moteurs_minimum' => 4,
        ]);

        $this->get(route('public.methodology'))
            ->assertOk()
            ->assertSee('31 points')
            ->assertSee('67 sur 100')
            ->assertSee('non vérifiable');
    });

    it('énonce les trois limites plutôt que de les taire', function (): void {
        $this->get(route('public.methodology'))
            ->assertOk()
            ->assertSee("L'hypertrucage vidéo n'est pas détecté", escape: false)
            ->assertSee("Nous ne nous prononçons pas sur l'origine d'un texte", escape: false)
            ->assertSee('Nous ne datons pas la première apparition d\'une image', escape: false);
    });

    it('annonce que les moteurs sont simulés tant qu\'ils le sont', function (): void {
        config(['factcheck.fake_adapters' => true]);
        $this->get(route('public.methodology'))->assertSee('État actuel des moteurs');

        config(['factcheck.fake_adapters' => false]);
        $this->get(route('public.methodology'))->assertDontSee('État actuel des moteurs');
    });

    it('renvoie vers le journal des corrections', function (): void {
        $this->get(route('public.methodology'))
            ->assertOk()
            ->assertSee(route('public.corrections'), escape: false);
    });
});

describe('la page financement', function (): void {
    it('publie le seul montant établi, et nomme son financeur', function (): void {
        $this->get(route('public.funding'))
            ->assertOk()
            ->assertSee('800 €')
            ->assertSee('Fonds de dotation Demain')
            ->assertSee('Aix-Marseille Université');
    });

    it('nomme les soutiens sans leur inventer de montant', function (): void {
        $reponse = $this->get(route('public.funding'))->assertOk();

        foreach ([
            'Fondation de France', 'AIESEC', 'U-Report Porto-Novo',
            'CEMAAC Bénin', 'Office franco-allemand pour la Jeunesse',
            'Regional Youth Cooperation Office', 'EVZ',
            'Union internationale des télécommunications',
        ] as $soutien) {
            $reponse->assertSee($soutien, escape: false);
        }

        // Le seul montant de la page est celui du 2ᵉ prix. Toute autre somme
        // serait une invention — c'est la règle §2 du chantier, et c'est la
        // seule chose qui rende cette page utile à qui la lit pour décider
        // s'il peut nous citer.
        $texte = strip_tags((string) $reponse->getContent());

        expect(substr_count($texte, '€'))->toBe(1)
            ->and($texte)->toContain('800 €');
    });
});

describe('les trois pages ne revendiquent aucune accréditation', function (): void {
    it('parle du code de conduite de l\'IFCN sans se dire signataire', function (): void {
        /*
         | On DECODE les entites avant de chercher. La phrase passe desormais
         | par `{{ __(...) }}`, qui echappe l'apostrophe en `&#039;` : la page
         | dit toujours exactement la meme chose, mais une comparaison brute
         | sur le HTML ne la reconnait plus.
         |
         | Le decodage ne relache RIEN, il resserre : sans lui, un
         | « membre de l&#039;IFCN » echappe serait passe sous le nez de la
         | garde negative, qui est pourtant la raison d'etre de ce test.
         */
        $html = html_entity_decode(
            (string) $this->get(route('public.methodology'))->getContent(),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        expect($html)
            ->toContain("Vérif'All n'en est pas signataire à ce jour")
            ->not->toContain('accrédité')
            ->not->toContain('membre de l\'IFCN');
    });
});

describe('le référencement des trois pages', function (): void {
    it('pose un titre et une description propres à chacune', function (): void {
        foreach ([
            'public.corrections' => 'Journal des corrections',
            'public.methodology' => 'Notre méthodologie',
            'public.funding' => 'Financement',
        ] as $route => $titre) {
            $html = (string) $this->get(route($route))->getContent();

            expect($html)
                ->toContain('<title>'.$titre)
                ->toContain('<link rel="canonical"')
                ->toContain('<meta property="og:title" content="'.$titre)
                ->toContain('<meta property="og:image"');
        }
    });
});
