<?php

declare(strict_types=1);

use App\Core\FactChecking\Adapters\AnalyseTechniqueAdapter;
use App\Core\FactChecking\Adapters\PerplexitySearchAdapter;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\TypeContenu;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| PHASE 1 — la découverte ne dépend d'aucune juridiction
|--------------------------------------------------------------------------
|
| Trois dettes levées ici : D-01 (filtre de domaine béninois), D-10 (fiabilité
| codée en dur) et D-12 (liste fermée de suffixes gouvernementaux).
|
| La règle que ces tests gardent : les listes de domaines sont des INDICES de
| réputation, jamais des œilletons. Le remède ne consiste pas à remplacer une
| liste fermée par une plus longue — `benin`, `france`, `usa`, `nigeria`… —
| mais à reconnaître des FORMES et à déléguer la réputation au référentiel.
|
*/

function sacLien(string $url): SacDePreuves
{
    return new SacDePreuves(affirmation: $url, type: TypeContenu::Lien);
}

describe('la recherche web n’est plus enfermée', function (): void {
    /*
     | `search_domain_filter` n'était pas une priorité, c'était un filtre DUR de
     | l'API Perplexity : la recherche ne voyait rien d'autre que cinq domaines
     | béninois. Une affirmation sur la France, l'OMS ou une entreprise ne
     | pouvait structurellement produire aucune preuve pertinente.
     */
    it('n’envoie AUCUN filtre de domaine au fournisseur', function (): void {
        config()->set('services.perplexity.key', 'cle-de-test');

        Http::fake(['api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'verdict' => 'NON_VERIFIABLE',
                'confidence' => 20,
                'reasoning_fr' => 'Aucune source concluante.',
            ])]]],
            'citations' => [],
        ])]);

        app(PerplexitySearchAdapter::class)->analyse(
            new SacDePreuves(affirmation: 'Une affirmation sur un autre pays.', type: TypeContenu::Texte),
        );

        Http::assertSent(function ($requete): bool {
            $charge = $requete->data();

            expect($charge)->not->toHaveKey(
                'search_domain_filter',
                'La recherche ne doit être enfermée dans aucune liste de domaines.',
            );

            return true;
        });
    });

    it('rend la fenêtre de fraîcheur réglable, et non figée dans le code', function (): void {
        config()->set('services.perplexity.key', 'cle-de-test');
        config()->set('factcheck.runtime.fraicheur_recherche', 'month');

        Http::fake(['api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => '{"verdict":"VRAI","confidence":80,"reasoning_fr":"x"}']]],
            'citations' => [],
        ])]);

        app(PerplexitySearchAdapter::class)->analyse(
            new SacDePreuves(affirmation: 'Une affirmation.', type: TypeContenu::Texte),
        );

        Http::assertSent(fn ($requete): bool => $requete->data()['search_recency_filter'] === 'month');
    });

    it('note les citations par le référentiel, sans privilège de suffixe', function (): void {
        config()->set('services.perplexity.key', 'cle-de-test');

        Http::fake(['api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => '{"verdict":"VRAI","confidence":80,"reasoning_fr":"x"}']]],
            // Deux sources inconnues du référentiel : une locale, une étrangère.
            'citations' => ['https://exemple-inconnu.bj/article', 'https://exemple-inconnu.fr/article'],
        ])]);

        $resultat = app(PerplexitySearchAdapter::class)->analyse(
            new SacDePreuves(affirmation: 'Une affirmation.', type: TypeContenu::Texte),
        );

        expect($resultat->preuves)->toHaveCount(2);

        /*
         | MÊME note pour les deux. L'ancienne heuristique donnait 85 au `.bj`
         | et 65 au reste : un portail d'État français, nigérian ou canadien
         | était noté moins qu'un blog béninois. Une réputation inconnue n'est
         | pas une mauvaise réputation — c'est une inconnue.
         */
        expect($resultat->preuves[0]->fiabilite)->toBe($resultat->preuves[1]->fiabilite);
    });
});

describe('les suffixes institutionnels sont reconnus par leur forme', function (): void {
    it('crédite une institution de n’importe quelle juridiction', function (string $domaine): void {
        $methode = new ReflectionMethod(AnalyseTechniqueAdapter::class, 'estUnSuffixeInstitutionnel');

        expect($methode->invoke(app(AnalyseTechniqueAdapter::class), $domaine))->toBeTrue(
            "{$domaine} porte un suffixe réservé aux institutions.",
        );
    })->with([
        // L'ancienne liste en comptait sept, toutes francophones ou anglophones.
        'gouv.bj', 'www.gouv.bj', 'service-public.gouv.fr', 'whitehouse.gov',
        'gov.uk', 'nhs.gov.uk', 'gov.ng', 'gob.mx', 'mhlw.go.jp',
        'canada.gc.ca', 'govt.nz', 'india.gov.in', 'who.int', 'army.mil',
        'gouv.ml', 'gouv.tg', 'gob.es',
    ]);

    /*
     | Le faux positif inverse serait pire : créditer un domaine acheté douze
     | euros qui se contente de contenir « gouv ».
     */
    it('ne crédite pas un domaine qui imite une institution', function (string $domaine): void {
        $methode = new ReflectionMethod(AnalyseTechniqueAdapter::class, 'estUnSuffixeInstitutionnel');

        expect($methode->invoke(app(AnalyseTechniqueAdapter::class), $domaine))->toBeFalse(
            "{$domaine} n'est pas une institution.",
        );
    })->with([
        'actu-gouv-benin.info', 'gouvernement-benin.xyz', 'mongov.example.com',
        'sante.gouv.fr.evil.com', 'govtech.io', 'lemonde.fr', 'harvard.edu',
    ]);
});

describe('aucune liste fermée ne subsiste dans le cœur', function (): void {
    /*
     | Le garde-fou de la décision : « le futur système ne doit surtout pas
     | remplacer cette liste par une autre liste du type France / USA / Benin /
     | Togo / Nigeria. Cela ne ferait que recréer le problème. »
     */
    it('ne réintroduit pas une liste de domaines dans les adaptateurs', function (): void {
        $fautifs = [];

        foreach (glob(app_path('Core/FactChecking/Adapters/*.php')) ?: [] as $fichier) {
            // Le simulateur a le droit : ses sources sont inventées par nature.
            if (str_contains($fichier, 'AdaptateurSimule')) {
                continue;
            }

            $lignes = preg_split('/\R/', (string) file_get_contents($fichier)) ?: [];

            foreach ($lignes as $numero => $ligne) {
                // Commentaires exclus : ils EXPLIQUENT les anciennes listes.
                if (preg_match('/^\s*(\*|\/\/|\||\/\*)/', $ligne) === 1) {
                    continue;
                }

                if (preg_match('/search_domain_filter|[\'"]gouv\.bj[\'"]|[\'"]lanationbenin/', $ligne) === 1) {
                    $fautifs[] = basename($fichier).':'.($numero + 1);
                }
            }
        }

        expect($fautifs)->toBe([], 'Liste de domaines réintroduite : '.implode(', ', $fautifs));
    });
});
