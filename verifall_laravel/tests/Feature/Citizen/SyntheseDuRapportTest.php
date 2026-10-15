<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\RenduDeSynthese;
use App\Enums\MoteurAnalyse;
use App\Enums\VerdictFactCheck;
use App\Models\EngineRun;
use Illuminate\Support\Collection;

/*
|--------------------------------------------------------------------------
| La synthèse affichée vient d'un modèle, donc d'une source hostile
|--------------------------------------------------------------------------
|
| Le texte de « Pourquoi ce résultat ? » est écrit par un modèle de langage
| dont l'ENTRÉE est l'affirmation soumise par le citoyen. Quiconque peut
| soumettre une affirmation peut tenter d'y glisser une consigne, et un modèle
| qui s'y plie renvoie ce qu'on lui a demandé d'écrire — y compris des balises.
|
| Ce texte était rendu par `{!! Str::markdown($texte) !!}` sans options.
| CommonMark vaut `html_input: allow` par défaut : le HTML passait tel quel
| dans la page d'un AUTRE lecteur. C'était le seul endroit du produit où la
| règle 3 du CLAUDE.md — « jamais `{!! !!}` sur données utilisateur » — était
| enfreinte, et le seul où elle comptait vraiment.
|
| Ces tests éprouvent la barrière, pas la mise en forme.
*/

/**
 * @param  array<int, array{moteur: MoteurAnalyse, raisonnement: ?string, echoue?: bool, verdict?: ?VerdictFactCheck}>  $lignes
 * @return Collection<int, EngineRun>
 */
function moteursRendus(array $lignes): Collection
{
    return collect($lignes)->map(function (array $ligne, int $index): EngineRun {
        $moteur = new EngineRun;

        $moteur->forceFill([
            'id' => $index + 1,
            'engine' => $ligne['moteur']->value,
            'verdict' => (array_key_exists('verdict', $ligne) ? $ligne['verdict'] : VerdictFactCheck::Faux)?->value,
            'confidence' => 90,
            'latency_ms' => 1200,
            'failed' => $ligne['echoue'] ?? false,
            'reasoning' => $ligne['raisonnement'],
        ]);

        return $moteur;
    });
}

describe('rendu sûr de la synthèse', function (): void {
    it('RETIRE le HTML écrit par le modèle au lieu de le servir', function (): void {
        $rendu = app(RenduDeSynthese::class)->html(
            "Voici l'analyse.\n\n<script>alert('xss')</script>\n\n<img src=x onerror=\"alert(1)\">"
        );

        expect($rendu)->not->toContain('<script');
        expect($rendu)->not->toContain('onerror');
        expect($rendu)->not->toContain('alert(');
        expect($rendu)->toContain("Voici l'analyse.");
    });

    it('refuse un lien javascript: déguisé en lien Markdown', function (): void {
        $rendu = app(RenduDeSynthese::class)->html(
            '[Cliquez ici](javascript:alert(document.cookie))'
        );

        expect($rendu)->not->toContain('javascript:');
    });

    it('garde la mise en forme Markdown légitime', function (): void {
        $rendu = app(RenduDeSynthese::class)->html(
            "## Ce qui est établi\n\n"
            ."L'affirmation est **fausse**.\n\n"
            ."- Aucun décret n'a été publié\n"
            ."- La présidence a démenti\n\n"
            .'Source : [gouv.bj](https://gouv.bj/communique)'
        );

        expect($rendu)
            ->toContain('<h2')
            ->toContain('<strong>')
            ->toContain('<li>')
            ->toContain('https://gouv.bj/communique');
    });

    it('retire le bloc JSON que Gemini colle en tête de réponse', function (): void {
        $rendu = app(RenduDeSynthese::class)->html(
            '{"final_verdict": "vrai", "confidence": 90} # Rapport'
        );

        expect($rendu)->not->toContain('final_verdict');
        expect($rendu)->toContain('Rapport');
    });

    it('rend une chaîne vide plutôt que du bruit quand il n’y a rien à dire', function (): void {
        expect(app(RenduDeSynthese::class)->html('   '))->toBe('');
    });
});

describe('choix du moteur dont on affiche le raisonnement', function (): void {
    /*
     | Le gabarit prenait `$moteurs->first(fn ($m) => ! empty($m->reasoning))`
     | sur une collection triée par `id` — donc, en pratique, l'ordre de
     | `MoteurAnalyse::pour(TypeContenu::Texte)`, qui place OpenAI EN TÊTE.
     | Le rapport mettait donc en avant le moteur SANS accès web, quelle que
     | soit la qualité de ce qu'avaient écrit les autres.
     */
    it('retient le moteur qui pèse le plus lourd au barème, pas le premier venu', function (): void {
        $moteurs = moteursRendus([
            ['moteur' => MoteurAnalyse::OpenAI, 'raisonnement' => 'Avis sans recherche web.'],
            ['moteur' => MoteurAnalyse::GoogleFactCheck, 'raisonnement' => 'Démenti déjà publié.'],
        ]);

        expect(app(RenduDeSynthese::class)->meilleurRaisonnement($moteurs))
            ->toBe('Démenti déjà publié.')
            ->and(MoteurAnalyse::GoogleFactCheck->poids())
            ->toBeGreaterThan(MoteurAnalyse::OpenAI->poids());
    });

    it('départage deux moteurs de même poids par ce qu’ils ont documenté', function (): void {
        $moteurs = moteursRendus([
            ['moteur' => MoteurAnalyse::Gemini, 'raisonnement' => 'Court.'],
            ['moteur' => MoteurAnalyse::Gemini, 'raisonnement' => 'Beaucoup plus détaillé, avec les sources et le raisonnement complet.'],
        ]);

        expect(app(RenduDeSynthese::class)->meilleurRaisonnement($moteurs))
            ->toContain('Beaucoup plus détaillé');
    });

    it('ignore un moteur en panne, même s’il a laissé un texte', function (): void {
        $moteurs = moteursRendus([
            ['moteur' => MoteurAnalyse::GoogleFactCheck, 'raisonnement' => 'Quota atteint.', 'echoue' => true],
            ['moteur' => MoteurAnalyse::DeepSeek, 'raisonnement' => 'Analyse complète.'],
        ]);

        expect(app(RenduDeSynthese::class)->meilleurRaisonnement($moteurs))->toBe('Analyse complète.');
    });

    it('rend null quand aucun moteur n’a rédigé', function (): void {
        $moteurs = moteursRendus([
            ['moteur' => MoteurAnalyse::Gemini, 'raisonnement' => null],
            ['moteur' => MoteurAnalyse::DeepSeek, 'raisonnement' => '   '],
        ]);

        expect(app(RenduDeSynthese::class)->pour($moteurs, VerdictFactCheck::Faux))->toBeNull();
    });

    /*
     | NEWINSTRUCTION30 — Google Fact Check porte le poids le PLUS FORT du
     | barème (1.0), et rend un texte non vide même quand il N'A TROUVÉ AUCUN
     | FACT-CHECK PRÉEXISTANT : « Aucune vérification antérieure trouvée dans
     | la base ClaimReview. », avec `verdict = null` (il s'abstient). Avant ce
     | correctif, ce texte gagnait le tri par poids pour la quasi-totalité des
     | claims — il n'existe presque jamais de fact-check préexistant pour une
     | rumeur locale — et s'affichait comme LA synthèse à la place de
     | l'analyse réelle de Gemini.
     */
    it('écarte un moteur SANS VERDICT même s’il a laissé un texte — il s’abstient, il n’analyse pas', function (): void {
        $moteurs = moteursRendus([
            [
                'moteur' => MoteurAnalyse::GoogleFactCheck,
                'raisonnement' => 'Aucune vérification antérieure trouvée dans la base ClaimReview.',
                'verdict' => null,
            ],
            ['moteur' => MoteurAnalyse::Gemini, 'raisonnement' => '**Cette affirmation est fausse.** Les sources institutionnelles le confirment.'],
        ]);

        expect(app(RenduDeSynthese::class)->meilleurRaisonnement($moteurs))
            ->toContain('Cette affirmation est fausse')
            ->not->toContain('ClaimReview');
    });

    it('rend null quand le seul texte disponible vient d’un moteur qui s’est abstenu', function (): void {
        $moteurs = moteursRendus([
            [
                'moteur' => MoteurAnalyse::GoogleFactCheck,
                'raisonnement' => 'Aucune vérification antérieure trouvée dans la base ClaimReview.',
                'verdict' => null,
            ],
        ]);

        expect(app(RenduDeSynthese::class)->meilleurRaisonnement($moteurs))->toBeNull();
    });
});

describe('le verdict a un droit de veto sur le texte affiché', function (): void {
    /*
    | Le verdict est calculé par l'algorithme ; le texte vient d'un moteur.
    | Rien n'empêchait un rapport de titrer « FAUX » puis de laisser lire,
    | quarante pixels plus bas, « cette information est exacte » — la
    | contradiction exacte que NEWINSTRUCTION14 décrivait comme le pire bug
    | possible pour une plateforme de fact-checking.
    */
    it('saute un raisonnement qui contredit le verdict et affiche le suivant', function (): void {
        // GoogleFactCheck pèse 1.0, Gemini 0.75 : c'est le premier qui est
        // affiché d'ordinaire — et c'est lui qui dément le verdict.
        $moteurs = moteursRendus([
            ['moteur' => MoteurAnalyse::GoogleFactCheck, 'raisonnement' => 'Après vérification, cette information est exacte.'],
            ['moteur' => MoteurAnalyse::Gemini, 'raisonnement' => 'Les sources consultées contredisent cette affirmation.'],
        ]);

        $rendu = app(RenduDeSynthese::class)->pour($moteurs, VerdictFactCheck::Faux);

        expect($rendu)->toContain('contredisent cette affirmation')
            ->and($rendu)->not->toContain('information est exacte');
    });

    it('n’affiche AUCUN raisonnement quand tous contredisent le verdict', function (): void {
        $moteurs = moteursRendus([
            ['moteur' => MoteurAnalyse::GoogleFactCheck, 'raisonnement' => 'Cette information est exacte.'],
            ['moteur' => MoteurAnalyse::Gemini, 'raisonnement' => 'Les sources confirment cette information.'],
        ]);

        // Mieux vaut un rapport muet sur son raisonnement qu'un rapport qui
        // se contredit : `$rapport->summary`, composé côté serveur, reste.
        expect(app(RenduDeSynthese::class)->pour($moteurs, VerdictFactCheck::Faux))->toBeNull();
    });

    it('affiche le raisonnement du meilleur moteur quand il est cohérent', function (): void {
        $moteurs = moteursRendus([
            ['moteur' => MoteurAnalyse::GoogleFactCheck, 'raisonnement' => 'Les sources consultées contredisent cette information.'],
            ['moteur' => MoteurAnalyse::OpenAI, 'raisonnement' => 'Réponse sans recherche web.'],
        ]);

        // Le veto ne doit PAS dégrader le choix : sans contradiction, le
        // moteur le plus lourd reste prioritaire.
        expect(app(RenduDeSynthese::class)->pour($moteurs, VerdictFactCheck::Faux))
            ->toContain('Les sources consultées contredisent');
    });
});
