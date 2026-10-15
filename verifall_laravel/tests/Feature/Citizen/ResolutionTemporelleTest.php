<?php

declare(strict_types=1);

use App\Core\FactChecking\Adapters\ConstructeurDePrompt;
use App\Core\FactChecking\Adapters\PerplexitySearchAdapter;
use App\Core\FactChecking\Services\ExtractionDesAffirmations;
use App\Core\FactChecking\ValueObjects\AffirmationExtraite;
use App\Core\FactChecking\ValueObjects\ContexteDuClaim;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\PorteeTemporelle;
use App\Enums\TypeContenu;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Phase 3 — résolution temporelle et contextuelle
|--------------------------------------------------------------------------
| Objectif du plan en 13 phases, au mot près : « la temporalité devient une
| donnée du domaine, pas une consigne de prompt ». Les six scénarios du plan :
| claim présent, claim historique, changement récent, date explicite, date
| relative, ancien titulaire non déclaré actuel.
*/

function extraireUnClaim(string $texte): AffirmationExtraite
{
    return app(ExtractionDesAffirmations::class)->extraire(new SacDePreuves($texte, TypeContenu::Texte))[0];
}

describe('classification temporelle — les six scénarios du plan', function (): void {
    it('claim présent : un état actuel explicite est PRESENT', function (): void {
        expect(extraireUnClaim('Le ministre de la Santé est actuellement en poste depuis janvier.')->porteeTemporelle)
            ->toBe(PorteeTemporelle::Present);
    });

    it('claim historique : un fait daté et clos est HISTORIQUE', function (): void {
        expect(extraireUnClaim('Le fleuve Niger traverse plusieurs pays.')->porteeTemporelle)
            ->toBe(PorteeTemporelle::Historique);
    });

    it('changement récent : un marqueur relatif récent est RELATIF', function (): void {
        expect(extraireUnClaim('Le tarif a changé récemment.')->porteeTemporelle)
            ->toBe(PorteeTemporelle::Relatif);
    });

    it('date explicite : une année nommée est DATE_EXPLICITE, même si elle est présente ailleurs', function (): void {
        expect(extraireUnClaim('Le prix du pain a doublé en 2026 au Bénin.')->porteeTemporelle)
            ->toBe(PorteeTemporelle::DateExplicite);
    });

    it('date relative : "cette semaine" exige de résoudre la date depuis {{DATE}}', function (): void {
        expect(extraireUnClaim('Le prix a baissé cette semaine.')->porteeTemporelle)
            ->toBe(PorteeTemporelle::Relatif);
    });

    it('ancien titulaire non déclaré actuel : un verbe de fonction au présent reste sensible au temps SANS "actuellement"', function (): void {
        // Le cas réel qui a motivé `PertinenceEtProvenanceTest` : aucun
        // marqueur temporel explicite, et pourtant exactement le genre de
        // claim qu'une mémoire périmée confirme à tort. Aucun nom réel de
        // titulaire ici — une suite de tests ne doit en nommer aucun, voir
        // la règle du même nom dans `PertinenceEtProvenanceTest`.
        expect(extraireUnClaim('X est président du pays Y.')->porteeTemporelle)
            ->toBe(PorteeTemporelle::Present);
    });

    it('un titulaire au PASSÉ composé n\'est pas confondu avec un état présent', function (): void {
        expect(extraireUnClaim('X a été président du pays Y de 2006 à 2016.')->porteeTemporelle)
            ->toBe(PorteeTemporelle::DateExplicite);
    });
});

describe('la consigne temporelle est injectée dans le prompt, pas redevinée', function (): void {
    it('porte la consigne PRESENT pour un claim classé présent', function (): void {
        $contexte = new ContexteDuClaim(PorteeTemporelle::Present, now());

        $prompt = app(ConstructeurDePrompt::class)->pour(new SacDePreuves(
            affirmation: 'Le ministre est actuellement en poste.',
            type: TypeContenu::Texte,
            contexteTemporel: $contexte,
        ));

        expect($prompt)->toContain('CLASSIFICATION TEMPORELLE')
            ->toContain('FORMELLEMENT INTERDITE');
    });

    it('porte la consigne HISTORIQUE pour un claim classé historique, et admet la mémoire', function (): void {
        $contexte = new ContexteDuClaim(PorteeTemporelle::Historique, now());

        $prompt = app(ConstructeurDePrompt::class)->pour(new SacDePreuves(
            affirmation: 'Le fleuve Niger traverse le Bénin.',
            type: TypeContenu::Texte,
            contexteTemporel: $contexte,
        ));

        expect($prompt)->toContain('Ta mémoire est admise');
    });

    it('ne laisse AUCUN marqueur non substitué, même sans classification', function (): void {
        $prompt = app(ConstructeurDePrompt::class)->pour(new SacDePreuves(
            affirmation: 'Une affirmation quelconque.',
            type: TypeContenu::Texte,
        ));

        expect($prompt)->not->toContain('{{PORTEE_TEMPORELLE}}')
            ->toContain('vigilance générale');
    });

    it('résout la date relative {{DATE}} dans la consigne elle-même', function (): void {
        $reference = now()->setDate(2026, 10, 2);
        $contexte = new ContexteDuClaim(PorteeTemporelle::Relatif, $reference);

        expect($contexte->consigne())->toContain('2 octobre 2026')
            ->not->toContain('{{DATE}}');
    });
});

describe('la fraîcheur de recherche dépend de la portée, pas d\'un seul réglage global', function (): void {
    function perplexiteAvecPortee(?PorteeTemporelle $portee): void
    {
        config()->set('services.perplexity.key', 'cle-de-test');

        Http::fake(['api.perplexity.ai/*' => Http::response([
            'choices' => [['message' => ['content' => '{"verdict":"VRAI","confidence":80,"reasoning_fr":"x"}']]],
            'citations' => [],
        ])]);

        app(PerplexitySearchAdapter::class)->analyse(new SacDePreuves(
            affirmation: 'Une affirmation.',
            type: TypeContenu::Texte,
            contexteTemporel: $portee !== null ? new ContexteDuClaim($portee, now()) : null,
        ));
    }

    it('PRESENT interroge sur un mois, pas sur l\'année entière', function (): void {
        perplexiteAvecPortee(PorteeTemporelle::Present);

        Http::assertSent(fn ($requete): bool => $requete->data()['search_recency_filter'] === 'month');
    });

    it('HISTORIQUE garde une fenêtre large', function (): void {
        perplexiteAvecPortee(PorteeTemporelle::Historique);

        Http::assertSent(fn ($requete): bool => $requete->data()['search_recency_filter'] === 'year');
    });

    it('DATE_EXPLICITE n\'envoie AUCUN filtre de fraîcheur — la date du claim ancre déjà la recherche', function (): void {
        perplexiteAvecPortee(PorteeTemporelle::DateExplicite);

        Http::assertSent(fn ($requete): bool => ! array_key_exists('search_recency_filter', $requete->data()));
    });

    it('sans classification, le réglage global d\'avant la phase 3 s\'applique à l\'identique', function (): void {
        config()->set('factcheck.runtime.fraicheur_recherche', 'day');

        perplexiteAvecPortee(null);

        Http::assertSent(fn ($requete): bool => $requete->data()['search_recency_filter'] === 'day');
    });
});
