<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\DomaineDeLaSource;
use App\Core\FactChecking\Services\FactCheckScoringService;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\VerdictFactCheck;
use App\Models\ReportEvidence;

/*
|--------------------------------------------------------------------------
| D'où vient la preuve, vraiment
|--------------------------------------------------------------------------
|
| Gemini ne rend pas les adresses des pages qu'il a lues : il rend des liens de
| redirection `vertexaisearch.cloud.google.com/grounding-api-redirect/…`, tous
| distincts, même quand ils mènent au même média. Trois conséquences, et chacune
| a son test ici :
|
|   · la colonne « Sources et preuves » affichait vingt fois le même
|     redirecteur Google au lieu du nom des médias ;
|   · `trusted_sources` était interrogée sur ce redirecteur, donc les 408
|     fiches de crédibilité de la rédaction n'avaient aucun effet ;
|   · la déduplication portait sur l'URL, donc vingt liens vers le même journal
|     comptaient pour vingt sources — l'illusion de recoupement exacte que le
|     fact-checking sert à défaire.
|
*/

describe('résolution du domaine', function (): void {
    it('rend le domaine réel derrière une redirection Google', function (): void {
        $domaine = app(DomaineDeLaSource::class)->pour(
            'https://vertexaisearch.cloud.google.com/grounding-api-redirect/AbCdEf123',
            'lemonde.fr',
        );

        expect($domaine)->toBe('lemonde.fr');
    });

    it('laisse une adresse directe intacte', function (): void {
        $domaine = app(DomaineDeLaSource::class)->pour('https://gouv.bj/communique/123', 'gouv.bj');

        expect($domaine)->toBe('gouv.bj');
    });

    /*
     | Le nom n'est pas toujours un domaine : l'API y met parfois un vrai titre
     | de page. Afficher « Communiqué du conseil des ministres » en guise de
     | provenance serait pire que d'avouer qu'on passe par Google.
     */
    it('refuse de prendre un titre de page pour un domaine', function (): void {
        $domaine = app(DomaineDeLaSource::class)->pour(
            'https://vertexaisearch.cloud.google.com/grounding-api-redirect/XyZ',
            'Communiqué du conseil des ministres',
        );

        expect($domaine)->toBe('vertexaisearch.cloud.google.com');
    });

    it('reconnaît les redirecteurs, et eux seuls', function (): void {
        $service = app(DomaineDeLaSource::class);

        expect($service->estUnRedirecteur('vertexaisearch.cloud.google.com'))->toBeTrue();
        expect($service->estUnRedirecteur('https://news.google.com/articles/abc'))->toBeTrue();
        expect($service->estUnRedirecteur('lemonde.fr'))->toBeFalse();
        expect($service->estUnRedirecteur('https://gouv.bj/x'))->toBeFalse();
    });
});

describe('ce que le lecteur voit', function (): void {
    it('affiche le média, pas le redirecteur', function (): void {
        $preuve = new ReportEvidence;
        $preuve->forceFill([
            'source_url' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/AbCd',
            'source_name' => 'rfi.fr',
        ]);

        expect($preuve->domaineAffiche())->toBe('rfi.fr');
    });

    it('n’altère pas une source citée directement', function (): void {
        $preuve = new ReportEvidence;
        $preuve->forceFill([
            'source_url' => 'https://www.rfi.fr/afrique/article',
            'source_name' => 'RFI Afrique',
        ]);

        expect($preuve->domaineAffiche())->toBe('www.rfi.fr');
    });
});

/*
|--------------------------------------------------------------------------
| « Indice de fiabilité » : ce que le chiffre promet
|--------------------------------------------------------------------------
|
| Il affichait la part de consensus. Deux moteurs d'accord donnaient 100 %,
| même quand ils annonçaient eux-mêmes 95 et 98 — la page promettait une
| certitude absolue là où les moteurs gardaient une réserve, sur un produit
| dont tout le propos est de ne pas surpromettre.
|
*/
describe('indice de fiabilité', function (): void {
    it('ne dit plus 100 % quand les moteurs eux-mêmes doutent un peu', function (): void {
        $decision = app(FactCheckScoringService::class)->decider([
            VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 95),
            VerdictMoteur::rendu(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 98),
        ]);

        expect($decision->verdict)->toBe(VerdictFactCheck::Faux);

        // Unanimes, donc consensus 100 — mais pas certains à 100 %.
        expect($decision->confiance)->toBeGreaterThan(90);
        expect($decision->confiance)->toBeLessThan(100);
    });

    it('rend bien 100 % quand les moteurs sont unanimes ET formels', function (): void {
        $decision = app(FactCheckScoringService::class)->decider([
            VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 100),
            VerdictMoteur::rendu(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 100),
        ]);

        expect($decision->confiance)->toBe(100);
    });

    /*
     | Un moteur dissident fait baisser le consensus ; l'indice doit suivre.
     | C'est la propriété qui compte : il ne peut être élevé que si les moteurs
     | sont d'accord ET sûrs.
     */
    it('baisse dès qu’un moteur s’écarte, même sûr de lui', function (): void {
        $unanime = app(FactCheckScoringService::class)->decider([
            VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 90),
            VerdictMoteur::rendu(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 90),
            VerdictMoteur::rendu(MoteurAnalyse::OpenAI, VerdictFactCheck::Faux, 90),
        ]);

        $divise = app(FactCheckScoringService::class)->decider([
            VerdictMoteur::rendu(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 90),
            VerdictMoteur::rendu(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 90),
            VerdictMoteur::rendu(MoteurAnalyse::OpenAI, VerdictFactCheck::Vrai, 90),
        ]);

        expect($divise->confiance)->toBeLessThan($unanime->confiance);
    });
});
