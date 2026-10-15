<?php

declare(strict_types=1);

use App\Core\FactChecking\Adapters\ConstructeurDePrompt;
use App\Core\FactChecking\Services\PertinenceDuFactCheck;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\PositionPreuve;
use App\Enums\TypeContenu;

/*
|--------------------------------------------------------------------------
| CURRENT_PUBLIC_OFFICE — test de non-régression
|--------------------------------------------------------------------------
|
| Né d'un cas réel : sur « Le Président actuel du Bénin est Patrice Talon »,
| l'API Fact Check Tools de Google a rendu un vieux démenti de l'AFP titré
| « Cette vidéo montre le soutien populaire du peuple béninois au coup d'État ».
| Un mot en commun. Ce démenti a compté comme un vote « FAUX » adossé à une
| rédaction accréditée, et l'indice est tombé de 95 % à 64 % sans qu'une ligne du
| rapport ne l'explique.
|
| ## Ce que ces tests n'affirment PAS
|
| Ils ne disent pas qui est président du Bénin. Ce serait figer dans la suite de
| tests un fait qui change — exactement la faute que le pipeline doit cesser de
| commettre. Un titulaire de fonction se vérifie par une preuve datée, jamais par
| une constante du code.
|
| Ce qu'ils gardent, c'est le COMPORTEMENT : qu'un fact-check hors sujet soit
| écarté, qu'une négation ne soit pas confondue avec son affirmation, qu'une
| source nommée de mémoire ne compte pas comme consultée, et que le prompt
| interdise de trancher un état présent sur la seule mémoire du modèle.
|
*/

describe('pertinence d’un fact-check antérieur', function (): void {
    it('ÉCARTE un démenti qui ne partage que le nom du pays', function (): void {
        $pertinence = app(PertinenceDuFactCheck::class);

        expect($pertinence->concorde(
            'Le Président actuel du Bénin est Patrice Talon',
            'Cette vidéo montre le soutien populaire du peuple béninois au coup d’État',
        ))->toBeFalse();
    });

    it('retient un démenti qui porte bien sur la même affirmation', function (): void {
        $pertinence = app(PertinenceDuFactCheck::class);

        expect($pertinence->concorde(
            'La taxe sur les transferts Mobile Money double lundi au Bénin',
            'Non, la taxe sur les transferts Mobile Money ne double pas au Bénin',
        ))->toBeFalse('Une affirmation et sa négation ne portent pas le même verdict.');

        expect($pertinence->concorde(
            'La taxe sur les transferts Mobile Money double lundi au Bénin',
            'La taxe sur les transferts Mobile Money va doubler au Bénin lundi prochain',
        ))->toBeTrue();
    });

    /*
     | LE CAS QUE `similar_text() < 40` SEUL NE VOIT PAS, et c'est pour lui que
     | le contrôle de négation existe. Ces deux phrases dépassent 85 % de
     | similarité lexicale et portent des verdicts OPPOSÉS : un seuil lexical
     | les déclarerait pertinentes et reporterait le verdict à l'envers.
     */
    it('refuse de confondre une affirmation avec sa négation, si proches soient-elles', function (): void {
        $pertinence = app(PertinenceDuFactCheck::class);

        $affirmation = 'Patrice Talon est président du Bénin';
        $negation = 'Patrice Talon n’est plus président du Bénin';

        similar_text(mb_strtolower($affirmation), mb_strtolower($negation), $lexical);

        expect($lexical)->toBeGreaterThan(80.0, 'Prémisse du test : les deux phrases sont lexicalement proches.');
        expect($pertinence->concorde($affirmation, $negation))->toBeFalse();
    });

    it('écarte un fait vérifié vide ou absent', function (): void {
        $pertinence = app(PertinenceDuFactCheck::class);

        expect($pertinence->concorde('Une affirmation quelconque', null))->toBeFalse();
        expect($pertinence->concorde('Une affirmation quelconque', '   '))->toBeFalse();
    });
});

describe('provenance d’une preuve', function (): void {
    /*
     | OpenAI et DeepSeek n'ont pas d'accès web. Leur champ `sources` liste des
     | domaines tirés de leur entraînement, avec l'extrait générique « Élément
     | factuel recoupé. » Comptées comme consultées, elles faisaient annoncer
     | « 5 sources consultées » là où UNE avait été récupérée.
     */
    it('pèse moitié moins quand la page n’a pas été ouverte', function (): void {
        $ouverte = new Preuve(
            url: 'https://gouv.bj/communique',
            nomSource: 'gouv.bj',
            extrait: 'Le communiqué dément la rumeur.',
            position: PositionPreuve::Contredit,
            fiabilite: 90,
        );

        $citee = new Preuve(
            url: 'https://gouv.bj/communique',
            nomSource: 'gouv.bj',
            extrait: 'Élément factuel recoupé.',
            position: PositionPreuve::Contredit,
            fiabilite: 90,
            recuperee: false,
        );

        expect($citee->poids())->toBeLessThan($ouverte->poids());
        expect($citee->poids())->toBeGreaterThan(0.0, 'Une référence citée garde une valeur : elle nomme un canal.');
    });

    it('expose la provenance dans la représentation persistée', function (): void {
        $citee = new Preuve(
            url: 'https://rfi.fr',
            nomSource: 'rfi.fr',
            extrait: 'Élément factuel recoupé.',
            position: PositionPreuve::Neutre,
            recuperee: false,
        );

        expect($citee->versTableau())->toHaveKey('recuperee');
        expect($citee->versTableau()['recuperee'])->toBeFalse();
    });
});

describe('le prompt interdit de trancher un état présent de mémoire', function (): void {
    it('nomme les marqueurs temporels et impose la recherche contradictoire', function (): void {
        $prompt = app(ConstructeurDePrompt::class)->pour(new SacDePreuves(
            affirmation: 'Le Président actuel du Bénin est Patrice Talon',
            type: TypeContenu::Texte,
            contexte: null,
            cheminFichier: null,
        ));

        expect($prompt)->toContain('JUSTESSE TEMPORELLE');
        expect($prompt)->toContain('actuellement');
        expect($prompt)->toContain('en exercice');
        expect($prompt)->toContain('recherche CONTRADICTOIRE');
        expect($prompt)->toContain('NE SUPPOSE JAMAIS');
    });

    it('fait chercher une preuve d’occupation de fonction, datée', function (): void {
        $prompt = app(ConstructeurDePrompt::class)->pour(new SacDePreuves(
            affirmation: 'X est ministre de la Santé',
            type: TypeContenu::Texte,
            contexte: null,
            cheminFichier: null,
        ));

        expect($prompt)->toContain('TITULAIRE DE FONCTION PUBLIQUE');
        expect($prompt)->toContain("preuve d'OCCUPATION de la fonction");
        expect($prompt)->toContain('investiture');
    });

    /*
     | La suite de tests ne doit nommer AUCUN titulaire de fonction : ce serait
     | figer un fait qui change, et le test deviendrait faux sans que personne
     | ne le relise. Ce contrôle garde cette règle sur les prompts.
     */
    it('n’inscrit aucun nom de titulaire dans les consignes', function (): void {
        $fichiers = array_merge(
            glob(resource_path('prompts/factcheck/*.md')) ?: [],
            glob(resource_path('prompts/factcheck/types/*.md')) ?: [],
        );

        expect($fichiers)->not->toBeEmpty();

        $fautifs = [];

        foreach ($fichiers as $fichier) {
            $contenu = (string) file_get_contents($fichier);

            if (preg_match('/\b(Talon|Wadagni)\b/u', $contenu) === 1) {
                $fautifs[] = basename($fichier);
            }
        }

        expect($fautifs)->toBe(
            [],
            'Un nom de titulaire est écrit dans un prompt : '.implode(', ', $fautifs)
            .'. Ces consignes doivent apprendre à CHERCHER une preuve datée, jamais porter la réponse.',
        );
    });
});
