<?php

declare(strict_types=1);

use App\Core\Marketing\Cartes\CarteDeDementi;
use App\Enums\VerdictFactCheck;
use App\Http\Controllers\Public\CarteDeDementiController;
use App\Jobs\EngendrerLaCarteDeDementi;
use App\Models\HubArticle;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| La carte de démenti
|--------------------------------------------------------------------------
| Ce qui est éprouvé ici n'est pas « la méthode existe » mais « la carte dit
| la vérité » : le bon verdict, la bonne date, et un poids qui passe sur une
| 3G. Une carte périmée qui annonce FAUX sur un verdict devenu VRAI est pire
| que pas de carte du tout.
*/

beforeEach(function (): void {
    Storage::fake(CarteDeDementi::DISQUE);

    /*
     | La route de la carte vit dans `routes/web.php`, fichier VERROUILLÉ pour
     | le lot : elle est déclarée ici à l'identique de la ligne que
     | l'intégration doit poser. Si les deux divergent un jour, ce test le dira
     | avant le premier partage.
     */
    Route::middleware('web')
        ->get('actualites/{article}/carte.png', CarteDeDementiController::class)
        ->name('public.fact.carte');

    // Une route déclarée après l'amorçage n'entre pas d'elle-même dans la
    // table des noms : sans cette ligne, `route('public.fact.carte')` lève.
    Route::getRoutes()->refreshNameLookups();
});

function carteBreve(array $extra = []): HubArticle
{
    return HubArticle::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'gratuite-de-l-electricite-en-septembre',
        'title' => "Non, le gouvernement n'a pas annoncé la gratuité de l'électricité en septembre",
        'excerpt' => 'Aucun communiqué de la SBEE ni du ministère ne mentionne cette mesure.',
        'body' => '<p>Analyse.</p>',
        'category' => 'Actualités',
        'verdict' => VerdictFactCheck::Faux->value,
        'is_published' => true,
        'published_at' => now()->subDay(),
    ], $extra));
}

/** La couleur réelle d'un pixel de la carte, image indexée comprise. */
function carteCouleurAu(string $png, int $x, int $y): array
{
    $image = imagecreatefromstring($png);
    expect($image)->not->toBeFalse();

    $teinte = imagecolorsforindex($image, imagecolorat($image, $x, $y));
    imagedestroy($image);

    return [$teinte['red'], $teinte['green'], $teinte['blue']];
}

/** Écart maximal entre deux teintes, composante par composante. */
function carteEcart(array $a, array $b): int
{
    return max(abs($a[0] - $b[0]), abs($a[1] - $b[1]), abs($a[2] - $b[2]));
}

describe('ce qui déclenche la fabrication', function (): void {
    it('met en file à la publication', function (): void {
        Queue::fake();

        expect(app(CarteDeDementi::class)->planifierSiNecessaire(carteBreve()))->toBeTrue();

        Queue::assertPushed(EngendrerLaCarteDeDementi::class);
    });

    it("n'engendre rien pour un fact-check non publié", function (): void {
        Queue::fake();
        $brouillon = carteBreve(['is_published' => false, 'published_at' => null]);
        $fabrique = app(CarteDeDementi::class);

        expect($fabrique->planifierSiNecessaire($brouillon))->toBeFalse()
            ->and($fabrique->engendrer($brouillon))->toBeNull()
            ->and($brouillon->refresh()->share_card_path)->toBeNull();

        Queue::assertNothingPushed();
        expect(Storage::disk(CarteDeDementi::DISQUE)->allFiles())->toBe([]);
    });

    it("n'engendre rien tant qu'aucun verdict n'est rendu", function (): void {
        Queue::fake();
        $sansVerdict = carteBreve(['verdict' => null]);

        expect(app(CarteDeDementi::class)->planifierSiNecessaire($sansVerdict))->toBeFalse();
        Queue::assertNothingPushed();
    });

    it('ne refabrique pas une carte déjà à jour', function (): void {
        $article = carteBreve();
        app(CarteDeDementi::class)->engendrer($article);

        Queue::fake();

        expect(app(CarteDeDementi::class)->planifierSiNecessaire($article))->toBeFalse();
        Queue::assertNothingPushed();
    });

    it('fabrique hors de la requête, par le travail de file', function (): void {
        $article = carteBreve();

        (new EngendrerLaCarteDeDementi($article->id))->handle(app(CarteDeDementi::class));

        expect($article->refresh()->share_card_path)->not->toBeNull();
    });

    it('ne casse pas quand la brève a disparu entre-temps', function (): void {
        $article = carteBreve();
        $identifiant = $article->id;
        $article->forceDelete();

        (new EngendrerLaCarteDeDementi($identifiant))->handle(app(CarteDeDementi::class));
    })->throwsNoExceptions();
});

describe('la carte elle-même', function (): void {
    it('existe après publication et voyage sur une 3G', function (): void {
        $article = carteBreve();

        $chemin = app(CarteDeDementi::class)->engendrer($article);

        expect($chemin)->not->toBeNull();
        Storage::disk(CarteDeDementi::DISQUE)->assertExists($chemin);

        $octets = (string) Storage::disk(CarteDeDementi::DISQUE)->get($chemin);

        // La contrainte dure du lot : une carte de 500 Ko ne se partage pas.
        expect(strlen($octets))->toBeLessThan(100 * 1024)
            ->and(getimagesizefromstring($octets))
            ->toMatchArray([0 => CarteDeDementi::LARGEUR, 1 => CarteDeDementi::HAUTEUR]);
    });

    it('porte la couleur de SON verdict, pas celle d’un autre', function (): void {
        $fabrique = app(CarteDeDementi::class);

        $faux = $fabrique->octetsPng(carteBreve());
        $vrai = $fabrique->octetsPng(carteBreve(['slug' => 'autre-breve', 'verdict' => VerdictFactCheck::Vrai->value]));

        // Plein milieu de la bande du verdict, là où rien n'est écrit.
        $teinteFausse = carteCouleurAu($faux, 900, 100);
        $teinteVraie = carteCouleurAu($vrai, 900, 100);

        // Les teintes de l'enum, jamais des valeurs recopiées à la main.
        expect(carteEcart($teinteFausse, [220, 38, 38]))->toBeLessThanOrEqual(8)
            ->and(carteEcart($teinteVraie, [22, 163, 74]))->toBeLessThanOrEqual(8)
            ->and(carteEcart($teinteFausse, $teinteVraie))->toBeGreaterThan(60);
    });

    it('couvre les quatre verdicts sans jamais retomber sur la même teinte', function (): void {
        $fabrique = app(CarteDeDementi::class);
        $teintes = [];

        foreach (VerdictFactCheck::cases() as $rang => $verdict) {
            $carte = $fabrique->octetsPng(carteBreve([
                'slug' => 'breve-'.$rang,
                'verdict' => $verdict->value,
            ]));

            $teintes[$verdict->value] = carteCouleurAu($carte, 900, 100);

            expect(carteEcart($teintes[$verdict->value], sscanf($verdict->couleurBarre(), '#%02x%02x%02x')))
                ->toBeLessThanOrEqual(8);
        }

        expect(array_unique(array_map(fn (array $t): string => implode(',', $t), $teintes)))->toHaveCount(4);
    });

    it('porte la date, parce qu’une carte sans date se recycle contre nous', function (): void {
        $article = carteBreve(['published_at' => now()->setDate(2026, 8, 14)]);

        // La date entre dans l'empreinte : deux publications à des dates
        // différentes ne peuvent pas partager le même fichier.
        $autre = carteBreve(['slug' => 'meme-titre-autre-date', 'published_at' => now()->setDate(2026, 8, 15)]);

        $fabrique = app(CarteDeDementi::class);

        expect($fabrique->empreinte($article))->not->toBe($fabrique->empreinte($autre))
            ->and($fabrique->octetsPng($article))->not->toBe($fabrique->octetsPng($autre));
    });
});

describe('quand le verdict change', function (): void {
    it('regénère la carte et efface la périmée', function (): void {
        $fabrique = app(CarteDeDementi::class);
        $article = carteBreve();

        $ancien = (string) $fabrique->engendrer($article);
        expect(carteEcart(carteCouleurAu((string) Storage::disk(CarteDeDementi::DISQUE)->get($ancien), 900, 100), [220, 38, 38]))
            ->toBeLessThanOrEqual(8);

        // La vérification a été corrigée : l'affirmation était vraie.
        $article->forceFill(['verdict' => VerdictFactCheck::Vrai->value])->save();

        Queue::fake();
        expect($fabrique->planifierSiNecessaire($article))->toBeTrue();
        Queue::assertPushed(EngendrerLaCarteDeDementi::class);

        $nouveau = (string) $fabrique->engendrer($article->refresh());

        expect($nouveau)->not->toBe($ancien)
            ->and($article->refresh()->share_card_path)->toBe($nouveau)
            ->and(carteEcart(carteCouleurAu((string) Storage::disk(CarteDeDementi::DISQUE)->get($nouveau), 900, 100), [22, 163, 74]))
            ->toBeLessThanOrEqual(8);

        // Le fichier périmé ne survit pas : il annoncerait FAUX pour toujours.
        Storage::disk(CarteDeDementi::DISQUE)->assertMissing($ancien);
    });

    it('change l’URL de partage, pour forcer WhatsApp à relire l’image', function (): void {
        $fabrique = app(CarteDeDementi::class);
        $article = carteBreve();
        $fabrique->engendrer($article);

        $avant = (string) $fabrique->urlPublique($article);

        $article->forceFill(['verdict' => VerdictFactCheck::Vrai->value])->save();
        $fabrique->engendrer($article);

        expect($fabrique->urlPublique($article))->not->toBe($avant)
            ->and($avant)->toContain('/carte.png?v=');
    });
});

describe('un titre très long', function (): void {
    it('se coupe proprement au lieu de déborder', function (): void {
        $fabrique = app(CarteDeDementi::class);
        $titre = trim(str_repeat('Une affirmation interminable qui circule sur WhatsApp depuis trois jours. ', 5));

        expect(mb_strlen($titre))->toBeGreaterThan(300);

        $largeurMax = CarteDeDementi::LARGEUR - 120;
        $lignes = $fabrique->decouperEnLignes($titre, CarteDeDementi::policeGrasse(), 42.0, $largeurMax, 3);

        expect($lignes)->toHaveCount(3)
            ->and(end($lignes))->toEndWith('…');

        foreach ($lignes as $ligne) {
            expect($fabrique->largeur($ligne, CarteDeDementi::policeGrasse(), 42.0))
                ->toBeLessThanOrEqual($largeurMax);
        }
    });

    it('ne laisse aucune encre dans la marge ni sur le pied de carte', function (): void {
        $png = app(CarteDeDementi::class)->octetsPng(carteBreve([
            'title' => trim(str_repeat('Affirmation interminable sans le moindre espace de repos pour l’œil. ', 5)),
            'excerpt' => trim(str_repeat('Et une raison tout aussi longue, qui doit céder la place avant le pied. ', 5)),
        ]));

        /*
         | La marge droite du corps, sur toute la hauteur du bloc de texte. La
         | tolérance vient de l'indexation en palette, qui décale une teinte de
         | deux ou trois unités : ce qu'on vérifie ici est l'absence d'ENCRE,
         | et une lettre y laisserait un pixel sombre, pas un blanc cassé.
         */
        for ($y = 210; $y < 495; $y += 5) {
            expect(carteEcart(carteCouleurAu($png, CarteDeDementi::LARGEUR - 12, $y), [255, 255, 255]))
                ->toBeLessThanOrEqual(8);
        }

        // Le filet du pied de carte, intact : le texte ne l'a pas franchi.
        expect(carteEcart(carteCouleurAu($png, 400, 500), [229, 231, 235]))->toBeLessThanOrEqual(8);
    });

    it('coupe même un mot que rien ne sépare', function (): void {
        $fabrique = app(CarteDeDementi::class);
        $largeurMax = 600;

        $lignes = $fabrique->decouperEnLignes(
            'https://un-domaine-interminable-sans-le-moindre-espace-pour-le-couper.example/chemin',
            CarteDeDementi::policeNormale(), 24.0, $largeurMax, 3,
        );

        expect(count($lignes))->toBeGreaterThan(1);

        foreach ($lignes as $ligne) {
            expect($fabrique->largeur($ligne, CarteDeDementi::policeNormale(), 24.0))
                ->toBeLessThanOrEqual($largeurMax);
        }
    });
});

describe('la route publique', function (): void {
    it('sert la carte au robot de WhatsApp, sans session', function (): void {
        $article = carteBreve();
        app(CarteDeDementi::class)->engendrer($article);

        $reponse = $this->get(route('public.fact.carte', $article));

        $reponse->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'max-age=86400, public');

        expect(getimagesizefromstring((string) $reponse->getContent()))
            ->toMatchArray([0 => CarteDeDementi::LARGEUR, 1 => CarteDeDementi::HAUTEUR]);
    });

    it('rend 404 plutôt qu’une carte générique quand il n’y en a pas', function (): void {
        $this->get(route('public.fact.carte', carteBreve(['is_published' => false])))->assertNotFound();
    });

    it('renvoie 304 quand le navigateur a déjà la bonne carte', function (): void {
        $article = carteBreve();
        $fabrique = app(CarteDeDementi::class);
        $fabrique->engendrer($article);

        $this->withHeaders(['If-None-Match' => '"'.$fabrique->empreinte($article).'"'])
            ->get(route('public.fact.carte', $article))
            ->assertStatus(304);
    });
});
