<?php

declare(strict_types=1);

use App\Core\Marketing\Seo\Referencement;
use App\Enums\VerdictFactCheck;
use App\Models\HubArticle;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| ClaimReview : le balisage affirmait le CONTRAIRE de son verdict
|--------------------------------------------------------------------------
| `claimReviewed` recevait le TITRE de l'article. Or les titres sont
| éditoriaux : ils énoncent déjà la conclusion.
|
| « Aucune taxe WhatsApp n'est instaurée au Bénin », noté FAUX, se lit par une
| machine « il est FAUX qu'aucune taxe n'existe » — soit exactement l'inverse
| du démenti. Le défaut était systématique : pour chaque article de démenti,
| donc pour la majorité, le balisage disait le contraire du verdict.
|
| Ingéré par un moteur, il aurait fait publier à Vérif'All de la
| désinformation lisible par machine. C'est le pire mode de défaillance
| possible pour une organisation de fact-checking, et il était invisible :
| la page s'affiche normalement, aucun test ne rougit, rien n'alerte.
*/

function faitVerifie(array $extra = []): HubArticle
{
    return HubArticle::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'claim-'.Str::random(8),
        'title' => "Aucune « taxe WhatsApp » n'est instaurée au Bénin",
        'claim' => "Le gouvernement béninois a instauré une taxe sur l'utilisation de WhatsApp.",
        'excerpt' => 'Aucun communiqué officiel ne mentionne cette mesure.',
        'body' => '<p>Analyse.</p>',
        'category' => 'Actualités',
        'verdict' => VerdictFactCheck::Faux->value,
        'is_published' => true,
        'published_at' => now()->subDay(),
    ], $extra));
}

function claimReviewDe(HubArticle $article): ?array
{
    foreach (app(Referencement::class)->pourFaitVerifie($article)->donneesStructurees as $bloc) {
        if (($bloc['@type'] ?? null) === 'ClaimReview') {
            return $bloc;
        }
    }

    return null;
}

describe("l'affirmation examinée", function (): void {
    it("est la RUMEUR, jamais le titre de l'article", function (): void {
        /*
         | LE test de ce fichier. S'il tombe, le balisage affirme de nouveau le
         | contraire de son propre verdict.
         */
        $balisage = claimReviewDe(faitVerifie());

        expect($balisage)->not->toBeNull()
            ->and($balisage['claimReviewed'])->toBe(
                "Le gouvernement béninois a instauré une taxe sur l'utilisation de WhatsApp."
            )
            // Le titre énonce la conclusion : il n'a rien à faire ici.
            ->and($balisage['claimReviewed'])->not->toContain('Aucune');
    });

    it("n'émet AUCUN balisage quand l'affirmation manque", function (): void {
        /*
         | Pas de balisage vaut infiniment mieux qu'un balisage inversé : le
         | premier ne dit rien, le second ment. La page reste valide et perd
         | seulement sa chance de figurer dans un panneau de fact-checking, ce
         | qui est sans commune mesure.
         */
        expect(claimReviewDe(faitVerifie(['claim' => null])))->toBeNull();
    });

    it('ignore une affirmation vide ou faite d’espaces', function (): void {
        expect(claimReviewDe(faitVerifie(['claim' => '   '])))->toBeNull();
    });
});

describe('la note', function (): void {
    it('accompagne le verdict, sur une échelle bornée', function (): void {
        $balisage = claimReviewDe(faitVerifie());

        expect($balisage['reviewRating']['ratingValue'])->toBe(1)
            ->and($balisage['reviewRating']['bestRating'])->toBe(5)
            ->and($balisage['reviewRating']['worstRating'])->toBe(1)
            ->and($balisage['reviewRating']['alternateName'])->toBe('FAUX');
    });

    it('ne CHIFFRE PAS un verdict non vérifiable', function (): void {
        /*
         | Une note médiane se lirait « à moitié vrai », ce qui est une
         | affirmation que la plateforme n'a pas faite. Le libellé demeure ; le
         | nombre disparaît.
         */
        $balisage = claimReviewDe(faitVerifie([
            'verdict' => VerdictFactCheck::NonVerifiable->value,
        ]));

        expect($balisage['reviewRating'])->not->toHaveKey('ratingValue')
            ->and($balisage['reviewRating']['alternateName'])->toBe('NON VÉRIFIABLE');
    });
});

describe("l'éditeur", function (): void {
    it('est identifiable par une adresse, pas seulement par un nom', function (): void {
        // Un moteur relie les vérifications d'un même auteur par cette adresse.
        $balisage = claimReviewDe(faitVerifie());

        expect($balisage['author']['name'])->toBe("Vérif'All")
            ->and($balisage['author']['url'])->toBe(route('public.home'));
    });
});

describe('la publication examinée', function (): void {
    it('est reliée quand la source est connue', function (): void {
        $balisage = claimReviewDe(faitVerifie([
            'source_url' => 'https://exemple.test/publication',
            'source_name' => 'Un site',
        ]));

        expect($balisage['itemReviewed']['@type'])->toBe('Claim')
            ->and($balisage['itemReviewed']['appearance']['url'])->toBe('https://exemple.test/publication');
    });

    it('est OMISE quand la source est inconnue, plutôt que vide', function (): void {
        // Un `appearance` vide vaut moins qu'aucun `appearance` : il déclare une
        // source sans en donner aucune.
        expect(claimReviewDe(faitVerifie(['source_url' => null])))
            ->not->toHaveKey('itemReviewed');
    });
});

describe('le flux pour Google', function (): void {
    it('sert un DataFeed à une adresse stable', function (): void {
        /*
         | L'adresse ne doit plus jamais changer une fois déclarée : un flux
         | déplacé n'est pas suivi, il est perdu, et le silence qui suit
         | ressemble à s'y méprendre à un flux vide.
         */
        faitVerifie();

        $flux = test()->get('/claimreview.json')->assertOk()->json();

        expect($flux['@type'])->toBe('DataFeed')
            ->and($flux)->toHaveKey('dateModified')
            ->and($flux['dataFeedElement'])->toHaveCount(1);
    });

    it('EXCLUT toute vérification sans affirmation consignée', function (): void {
        /*
         | La même règle que sur la page, et pour une raison plus forte encore :
         | un flux est fait pour être ingéré SANS relecture humaine. Y laisser
         | entrer un balisage inversé reviendrait à faire publier à Vérif'All de
         | la désinformation lisible par machine.
         */
        faitVerifie();
        faitVerifie(['claim' => null]);
        faitVerifie(['claim' => '  ']);

        $flux = test()->get('/claimreview.json')->assertOk()->json();

        expect($flux['dataFeedElement'])->toHaveCount(1);
    });

    it('écarte les brouillons', function (): void {
        faitVerifie(['is_published' => false, 'published_at' => null]);

        expect(test()->get('/claimreview.json')->json('dataFeedElement'))->toHaveCount(0);
    });

    it('dit la MÊME chose que la page, produite par le même code', function (): void {
        /*
         | Deux générateurs distincts auraient fini par publier deux verdicts
         | différents pour la même vérification. Le flux et la page sortent donc
         | du même appel.
         */
        $article = faitVerifie();

        $duFlux = test()->get('/claimreview.json')->json('dataFeedElement.0');
        $deLaPage = claimReviewDe($article->refresh());

        expect($duFlux['claimReviewed'])->toBe($deLaPage['claimReviewed'])
            ->and($duFlux['reviewRating']['alternateName'])
            ->toBe($deLaPage['reviewRating']['alternateName']);
    });

    it('ne répète pas @context sur chaque élément', function (): void {
        // Déclaré une fois en tête. Le répéter deux cents fois alourdit le
        // fichier sans rien ajouter.
        faitVerifie();

        expect(test()->get('/claimreview.json')->json('dataFeedElement.0'))
            ->not->toHaveKey('@context');
    });

    it('SE RAFRAÎCHIT quand un verdict est corrigé', function (): void {
        /*
         | Le flux est mis en cache une heure. Sans invalidation, un verdict
         | corrigé continuerait d'être servi FAUX à un moteur qui l'ingère sans
         | relecture — exactement le scénario que la correction visait à
         | empêcher.
         */
        $article = faitVerifie();

        expect(test()->get('/claimreview.json')->json('dataFeedElement.0.reviewRating.alternateName'))
            ->toBe('FAUX');

        $article->update(['verdict' => VerdictFactCheck::Vrai->value]);

        expect(test()->get('/claimreview.json')->json('dataFeedElement.0.reviewRating.alternateName'))
            ->toBe('VRAI');
    });
});
