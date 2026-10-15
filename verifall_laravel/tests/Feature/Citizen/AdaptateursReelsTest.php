<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Core\FactChecking\Adapters\AiOrNotAdapter;
use App\Core\FactChecking\Adapters\AnalyseTechniqueAdapter;
use App\Core\FactChecking\Adapters\DeepSeekAdapter;
use App\Core\FactChecking\Adapters\GeminiAdapter;
use App\Core\FactChecking\Adapters\GoogleCustomSearchAdapter;
use App\Core\FactChecking\Adapters\GoogleFactCheckAdapter;
use App\Core\FactChecking\Adapters\OpenAiAdapter;
use App\Core\FactChecking\Adapters\TranscriptionAdapter;
use App\Core\FactChecking\Services\RegistreDesMoteurs;
use App\Core\FactChecking\ValueObjects\ElementDuPaquet;
use App\Core\FactChecking\ValueObjects\PaquetDePreuves;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use App\Models\SourceCategory;
use App\Models\TrustedSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Adaptateurs réels
|--------------------------------------------------------------------------
| Aucun appel réseau : les réponses des fournisseurs sont simulées. Ce qui est
| vérifié, c'est notre lecture de ces réponses — et surtout notre tenue quand
| elles sont absurdes, ce qui arrive tous les jours en production.
*/

function sac(string $affirmation = 'Une affirmation à vérifier.', ?TypeContenu $type = null): SacDePreuves
{
    return new SacDePreuves($affirmation, $type ?? TypeContenu::Texte);
}

/** Un paquet à un élément, pour le contrat V-10 — citation par source_id. */
function paquetAUnElement(string $id = 'SRC_001'): PaquetDePreuves
{
    return PaquetDePreuves::depuis([new ElementDuPaquet(
        id: $id,
        urlFinale: 'https://arcep.bj/note-reelle',
        urlCanonique: null,
        editeur: 'arcep.bj',
        titre: 'Note officielle',
        extrait: 'Extrait réellement récupéré par le collecteur.',
        publieeLe: null,
        fiabilite: 90,
    )]);
}

/** Réponse Gemini bien formée. */
function reponseGemini(array $charge): array
{
    return ['candidates' => [[
        'content' => ['parts' => [['text' => json_encode($charge)]]],
        'groundingMetadata' => ['groundingChunks' => [
            ['web' => ['uri' => 'https://gouv.bj/communique', 'title' => 'Portail du Gouvernement']],
        ]],
    ]]];
}

describe('garantie du contrat', function (): void {
    it('ne lève jamais, même quand le service répond n\'importe quoi', function (): void {
        config()->set('services.gemini.keys', ['cle-de-test']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('<html>erreur</html>', 200)]);

        $resultat = app(GeminiAdapter::class)->analyse(sac());

        // Un fournisseur qui renvoie du HTML au lieu du JSON promis ne doit pas
        // faire tomber l'analyse : onze autres moteurs ont peut-être répondu.
        expect($resultat->echoue)->toBeTrue()
            ->and($resultat->verdict)->toBeNull();
    });

    it('ne lève jamais quand le service est injoignable', function (): void {
        config()->set('services.deepseek.key', 'cle-de-test');
        Http::fake(['api.deepseek.com/*' => fn () => throw new RuntimeException('réseau coupé')]);

        expect(app(DeepSeekAdapter::class)->analyse(sac())->echoue)->toBeTrue();
    });

    it('s\'abstient sans clé, au lieu d\'échouer bruyamment', function (): void {
        config()->set('services.deepseek.key', null);

        $resultat = app(DeepSeekAdapter::class)->analyse(sac());

        // Une installation sans clé DeepSeek doit fonctionner avec les autres
        // moteurs, pas afficher une erreur à chaque analyse.
        expect($resultat->echoue)->toBeTrue()
            ->and($resultat->motifEchec)->toBe('Moteur non configuré');
    });

    it('ne laisse fuir aucun détail technique dans le verdict', function (): void {
        config()->set('services.deepseek.key', 'cle-de-test');
        Http::fake(['api.deepseek.com/*' => fn () => throw new RuntimeException(
            'Connexion refusée vers https://api.deepseek.com avec la clé sk-secret-123',
        )]);

        $resultat = app(DeepSeekAdapter::class)->analyse(sac());

        // Le message d'exception peut contenir une URL de service, un
        // identifiant, voire une clé recopiée par une bibliothèque tierce.
        expect($resultat->motifEchec)->toBe('Erreur technique du moteur')
            ->and($resultat->motifEchec)->not->toContain('sk-secret-123');
    });

    it('mesure la latence même en cas d\'échec', function (): void {
        config()->set('services.deepseek.key', null);

        expect(app(DeepSeekAdapter::class)->analyse(sac())->latenceMs)->toBeGreaterThanOrEqual(0);
    });
});

describe('Gemini', function (): void {
    beforeEach(function (): void {
        config()->set('services.gemini.keys', ['cle-a', 'cle-b']);
    });

    it('lit le verdict, la confiance et l\'indice de haine', function (): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(reponseGemini([
            'verdict' => 'FAUX',
            'confidence' => 88,
            'reasoning_fr' => 'Le communiqué officiel dément cette information.',
            'sources' => [['name' => 'ARCEP', 'url' => 'https://arcep.bj/note', 'stance' => 'REFUTES']],
            'hate_speech' => ['score' => 12, 'tags' => [], 'explanation' => 'Aucun signal.'],
        ]))]);

        $resultat = app(GeminiAdapter::class)->analyse(sac());

        expect($resultat->verdict)->toBe(VerdictFactCheck::Faux)
            ->and($resultat->confiance)->toBe(88)
            ->and($resultat->indiceHaine->score)->toBe(12)
            ->and($resultat->preuves)->not->toBeEmpty();
    });

    it('retient les pages réellement ouvertes, pas seulement celles déclarées', function (): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(reponseGemini([
            'verdict' => 'VRAI', 'confidence' => 70,
        ]))]);

        $urls = array_map(
            static fn ($preuve): string => $preuve->url,
            app(GeminiAdapter::class)->analyse(sac())->preuves,
        );

        // La différence entre une citation vérifiable et une citation plausible.
        expect($urls)->toContain('https://gouv.bj/communique');
    });

    it('bascule sur la clé suivante quand la première est épuisée', function (): void {
        $appels = 0;

        Http::fake(['generativelanguage.googleapis.com/*' => function () use (&$appels) {
            $appels++;

            return $appels === 1
                ? Http::response(['error' => 'quota'], 429)
                : Http::response(reponseGemini(['verdict' => 'VRAI', 'confidence' => 75]));
        }]);

        // Avec une seule clé, la plateforme s'arrête net au milieu de la
        // journée : c'est le problème que la rotation résout.
        expect(app(GeminiAdapter::class)->analyse(sac())->verdict)->toBe(VerdictFactCheck::Vrai);
    });

    it('CRIE quand le modèle demandé n’existe plus', function (): void {
        /*
         | Le défaut le plus insidieux de l'adaptateur, et il a réellement
         | vécu : le repli valait `gemini-1.5-flash`, retiré du catalogue. Un
         | 404 ne figurait dans aucune liste de codes traités, la boucle passait
         | au modèle suivant puis à la clé suivante, où le même 404 se
         | reproduisait — et RIEN n'était journalisé.
         |
         | Le repli n'a jamais pris le relais et ne le pouvait pas. Une panne
         | muette se découvre des semaines plus tard, sur une réclamation.
         */
        Log::spy();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(
            ['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'models/x is not found']],
            404,
        )]);

        $resultat = app(GeminiAdapter::class)->analyse(sac());

        expect($resultat->echoue)->toBeTrue();

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message): bool => str_contains($message, 'Modèle inconnu'))
            ->atLeast()->once();
    });

    it('DIT quand la réponse revient sans candidat, au lieu de se taire', function (): void {
        /*
         | Gemini rend 200 avec zéro candidat quand il bloque la réponse pour
         | raison de sécurité. Sur une plateforme de fact-checking — dont le
         | métier est justement de soumettre des propos haineux ou violents à
         | l'analyse — ce n'est pas un cas limite, c'est un cas ORDINAIRE.
         |
         | Il était muet : le moteur rendait « rien », et rien ne distinguait un
         | blocage d'une panne réseau.
         */
        Log::spy();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'promptFeedback' => ['blockReason' => 'PROHIBITED_CONTENT'],
            'candidates' => [],
        ])]);

        expect(app(GeminiAdapter::class)->analyse(sac())->echoue)->toBeTrue();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $contexte): bool => str_contains($message, 'sans candidat')
                && ($contexte['blocage'] ?? null) === 'PROHIBITED_CONTENT')
            ->once();
    });

    it('accepte un JSON enrobé dans un bloc markdown', function (): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
            'content' => ['parts' => [['text' => "```json\n".json_encode(['verdict' => 'TROMPEUR', 'confidence' => 60])."\n```"]]],
        ]]])]);

        // Les modèles enrobent volontiers leur JSON malgré la consigne. Ne pas
        // le prévoir coûterait un moteur qui échoue une fois sur trois.
        expect(app(GeminiAdapter::class)->analyse(sac())->verdict)->toBe(VerdictFactCheck::Trompeur);
    });

    it('ne prétend pas mesurer la haine quand le modèle ne l\'a pas fait', function (): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(reponseGemini([
            'verdict' => 'VRAI', 'confidence' => 80,
        ]))]);

        expect(app(GeminiAdapter::class)->analyse(sac())->indiceHaine->mesure())->toBeFalse();
    });

    it('borne une confiance aberrante', function (): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(reponseGemini([
            'verdict' => 'VRAI', 'confidence' => 900,
        ]))]);

        // Un modèle rend parfois 120, ou -5 : non borné, le score général
        // basculerait pour un seul moteur bavard.
        expect(app(GeminiAdapter::class)->analyse(sac())->confiance)->toBe(100);
    });
});

describe('fact-checks antérieurs', function (): void {
    beforeEach(function (): void {
        config()->set('services.google_factcheck.key', 'cle-de-test');
    });

    it('reprend le verdict des rédactions et cite leurs articles', function (): void {
        /*
         | L'affirmation SOUMISE et le fait vérifié par la rédaction sont les
         | mêmes — c'est la condition pour que le verdict se reporte.
         |
         | Ce jeu d'essai appariait auparavant « Une affirmation à vérifier. »
         | avec un démenti sur la taxe WhatsApp : zéro mot en commun. Il décrivait
         | donc, sans le dire, le défaut même que le filtre de pertinence est
         | venu corriger — un démenti hors sujet reporté comme un verdict.
         */
        $affirmation = 'La taxe WhatsApp entre en vigueur lundi au Bénin.';

        Http::fake(['factchecktools.googleapis.com/*' => Http::response(['claims' => [[
            'text' => 'La taxe WhatsApp entre en vigueur lundi au Bénin.',
            'claimReview' => [[
                'publisher' => ['name' => 'AFP Factuel'],
                'textualRating' => 'Faux',
                'url' => 'https://factuel.afp.com/taxe-whatsapp',
            ]],
        ]]])]);

        $resultat = app(GoogleFactCheckAdapter::class)->analyse(sac($affirmation));

        expect($resultat->verdict)->toBe(VerdictFactCheck::Faux)
            ->and($resultat->preuves[0]->position)->toBe(PositionPreuve::Contredit)
            // Une rédaction accréditée : la source la plus fiable du pipeline.
            ->and($resultat->preuves[0]->fiabilite)->toBe(90);
    });

    /*
     | LE CAS RÉEL, devenu test de non-régression.
     |
     | Sur « Le Président actuel du Bénin est Patrice Talon », l'API a rendu un
     | vieux démenti titré « Cette vidéo montre le soutien populaire du peuple
     | béninois au coup d'État » — la recherche est en texte flou, et « Bénin »
     | a suffi. Ce démenti comptait comme un vote « FAUX » à 68 %, adossé à une
     | fiabilité de source de 90 % parce qu'une rédaction accréditée l'avait
     | publié : il pesait donc plus lourd que deux moteurs concordants, et
     | l'indice tombait de 95 % à 64 % sans qu'une ligne du rapport ne l'explique.
     */
    it('S\'ABSTIENT sur un fact-check qui porte sur une autre affirmation', function (): void {
        Http::fake(['factchecktools.googleapis.com/*' => Http::response(['claims' => [[
            'text' => 'Cette vidéo montre le soutien populaire du peuple béninois au coup d\'État',
            'claimReview' => [[
                'publisher' => ['name' => 'AFP Factuel'],
                'textualRating' => 'Faux',
                'url' => 'https://factuel.afp.com/video-coup-etat',
            ]],
        ]]])]);

        $resultat = app(GoogleFactCheckAdapter::class)->analyse(
            sac('Le Président actuel du Bénin est en exercice depuis mai.'),
        );

        // Abstention, pas « faux » : verdict nul, donc hors du calcul.
        expect($resultat->verdict)->toBeNull()
            ->and($resultat->compteDansLeScore())->toBeFalse()
            // Et l'article hors sujet n'entre pas dans « Sources et preuves ».
            ->and($resultat->preuves)->toBe([])
            // Le tableau dit « abstenu », jamais « indisponible » : l'API a
            // parfaitement répondu, c'est son résultat qui ne s'applique pas.
            ->and($resultat->echoue)->toBeFalse()
            ->and($resultat->raisonnement)->toContain('autre affirmation');
    });

    it('s\'abstient quand personne n\'a encore vérifié', function (): void {
        Http::fake(['factchecktools.googleapis.com/*' => Http::response(['claims' => []])]);

        $resultat = app(GoogleFactCheckAdapter::class)->analyse(sac());

        // Beaucoup de rumeurs béninoises n'ont jamais été fact-checkées :
        // rendre « douteux » ferait peser sur chacune le simple fait que
        // personne ne s'en est encore occupé.
        expect($resultat->echoue)->toBeFalse()
            ->and($resultat->verdict)->toBeNull()
            ->and($resultat->compteDansLeScore())->toBeFalse();
    });
});

describe('date de publication — dette comblée', function (): void {
    /*
     | `published_at` existait depuis la migration d'origine et n'était
     | renseigné par AUCUN des neuf points de construction d'une `Preuve`.
     | Ce bloc prouve que ce n'est plus vrai pour les deux adaptateurs qui
     | disposent d'un vrai signal — et que les autres continuent de rendre
     | `null` plutôt que d'inventer une date.
     */
    beforeEach(function (): void {
        config()->set('services.google_factcheck.key', 'cle-de-test');
    });

    it('lit la date du démenti sur un ClaimReview', function (): void {
        Http::fake(['factchecktools.googleapis.com/*' => Http::response(['claims' => [[
            'text' => 'Une affirmation à vérifier.',
            'claimReview' => [[
                'publisher' => ['name' => 'AFP Factuel'],
                'textualRating' => 'Faux',
                'url' => 'https://factuel.afp.com/dementi',
                'reviewDate' => '2026-09-12T08:00:00Z',
            ]],
        ]]])]);

        $resultat = app(GoogleFactCheckAdapter::class)->analyse(sac());

        expect($resultat->preuves[0]->publieeLe)->not->toBeNull()
            ->and($resultat->preuves[0]->publieeLe->toDateString())->toBe('2026-09-12');
    });

    it('ne devine pas de date quand reviewDate est absent ou illisible', function (): void {
        Http::fake(['factchecktools.googleapis.com/*' => Http::response(['claims' => [[
            'text' => 'Une affirmation à vérifier.',
            'claimReview' => [[
                'publisher' => ['name' => 'AFP Factuel'],
                'textualRating' => 'Faux',
                'url' => 'https://factuel.afp.com/dementi',
                'reviewDate' => 'pas une date',
            ]],
        ]]])]);

        expect(app(GoogleFactCheckAdapter::class)->analyse(sac())->preuves[0]->publieeLe)->toBeNull();
    });

    it('ne devine jamais de date depuis une source de mémoire — DeepSeek', function (): void {
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'verdict' => 'VRAI', 'confidence' => 70,
                'reasoning_fr' => 'x',
                'sources' => [['url' => 'https://gouv.bj', 'name' => 'gouv.bj']],
            ])]]],
        ])]);
        config()->set('services.deepseek.key', 'cle');

        $preuves = app(DeepSeekAdapter::class)->analyse(sac())->preuves;

        expect($preuves)->not->toBeEmpty()
            ->and($preuves[0]->publieeLe)->toBeNull();
    });

    it('ne prend pas la première capture d\'archive pour une date de publication', function (): void {
        Http::fake(['archive.org/*' => Http::response(['archived_snapshots' => [
            'closest' => ['timestamp' => '20180314120000'],
        ]])]);

        $preuves = app(AnalyseTechniqueAdapter::class)
            ->analyse(sac('https://lanationbenin.info/a', TypeContenu::Lien))->preuves;

        expect($preuves[0]->publieeLe)->toBeNull();
    });
});

describe('recherche web', function (): void {
    it('interroge aussi la formulation adverse', function (): void {
        config()->set('services.google_cse.key', 'cle');
        config()->set('services.google_cse.id', 'identifiant');

        $requetes = [];

        Http::fake(['www.googleapis.com/customsearch/*' => function ($requete) use (&$requetes) {
            parse_str((string) parse_url((string) $requete->url(), PHP_URL_QUERY), $parametres);
            $requetes[] = $parametres['q'] ?? '';

            return Http::response(['items' => [[
                'link' => 'https://lanationbenin.info/article-'.count($requetes),
                'displayLink' => 'lanationbenin.info',
                'snippet' => 'Extrait',
            ]]]);
        }]);

        app(GoogleCustomSearchAdapter::class)->analyse(sac('Le décret a été signé hier.'));

        // Ne chercher que l'affirmation ne trouve que ce qui la répète : c'est
        // ainsi qu'une rumeur se confirme elle-même.
        expect($requetes)->toHaveCount(2)
            ->and($requetes[1])->toContain('démenti');
    });

    it('ne tranche pas : il apporte des sources', function (): void {
        config()->set('services.google_cse.key', 'cle');
        config()->set('services.google_cse.id', 'identifiant');

        Http::fake(['www.googleapis.com/customsearch/*' => Http::response(['items' => [
            ['link' => 'https://gouv.bj/a', 'displayLink' => 'gouv.bj', 'snippet' => 'x'],
        ]])]);

        $resultat = app(GoogleCustomSearchAdapter::class)->analyse(sac());

        expect($resultat->verdict)->toBeNull()
            ->and($resultat->compteDansLeScore())->toBeFalse()
            ->and($resultat->preuves)->not->toBeEmpty();
    });

    it('note un portail officiel au-dessus d\'un réseau social', function (): void {
        config()->set('services.google_cse.key', 'cle');
        config()->set('services.google_cse.id', 'identifiant');

        Http::fake(['www.googleapis.com/customsearch/*' => Http::response(['items' => [
            ['link' => 'https://services.gouv.bj/note', 'displayLink' => 'services.gouv.bj', 'snippet' => 'x'],
            ['link' => 'https://facebook.com/post', 'displayLink' => 'facebook.com', 'snippet' => 'y'],
        ]])]);

        $fiabilites = [];
        foreach (app(GoogleCustomSearchAdapter::class)->analyse(sac())->preuves as $preuve) {
            $fiabilites[$preuve->nomSource] = $preuve->fiabilite;
        }

        // Un 50 % uniforme mettrait un blog anonyme au niveau du portail
        // de l'État.
        expect($fiabilites['services.gouv.bj'])->toBeGreaterThan($fiabilites['facebook.com']);
    });

    it('lit la date déclarée par la page dans ses métadonnées', function (): void {
        config()->set('services.google_cse.key', 'cle');
        config()->set('services.google_cse.id', 'identifiant');

        Http::fake(['www.googleapis.com/customsearch/*' => Http::response(['items' => [
            [
                'link' => 'https://lanationbenin.info/article',
                'displayLink' => 'lanationbenin.info',
                'snippet' => 'x',
                'pagemap' => ['metatags' => [
                    ['article:published_time' => '2026-08-30T10:00:00+01:00'],
                ]],
            ],
        ]])]);

        $preuve = app(GoogleCustomSearchAdapter::class)->analyse(sac())->preuves[0];

        expect($preuve->publieeLe)->not->toBeNull()
            ->and($preuve->publieeLe->toDateString())->toBe('2026-08-30');
    });

    it('ne devine pas de date quand la page n\'en déclare aucune', function (): void {
        config()->set('services.google_cse.key', 'cle');
        config()->set('services.google_cse.id', 'identifiant');

        Http::fake(['www.googleapis.com/customsearch/*' => Http::response(['items' => [
            ['link' => 'https://gouv.bj/a', 'displayLink' => 'gouv.bj', 'snippet' => 'x'],
        ]])]);

        expect(app(GoogleCustomSearchAdapter::class)->analyse(sac())->preuves[0]->publieeLe)->toBeNull();
    });
});

describe('analyse technique', function (): void {
    it('repère un domaine qui usurpe un nom officiel', function (): void {
        Http::fake(['archive.org/*' => Http::response(['archived_snapshots' => []])]);

        $resultat = app(AnalyseTechniqueAdapter::class)
            ->analyse(sac('https://gouv-benin-officiel.xyz/decret', TypeContenu::Lien));

        expect($resultat->verdict)->toBe(VerdictFactCheck::Trompeur)
            ->and($resultat->raisonnement)->toContain('institution');
    });

    it('plafonne sa confiance : la technique éclaire, elle ne tranche pas', function (): void {
        Http::fake(['archive.org/*' => Http::response(['archived_snapshots' => []])]);

        $resultat = app(AnalyseTechniqueAdapter::class)
            ->analyse(sac('https://gouv-benin-officiel.tk/x', TypeContenu::Lien));

        // Un domaine récent peut publier une information exacte.
        expect($resultat->confiance)->toBeLessThanOrEqual(70);
    });

    it('n\'accuse pas un site quand les archives sont en panne', function (): void {
        Http::fake(['archive.org/*' => Http::response('', 503)]);

        $resultat = app(AnalyseTechniqueAdapter::class)
            ->analyse(sac('https://lanationbenin.info/article', TypeContenu::Lien));

        // Traiter une panne d'Internet Archive comme une absence d'archive
        // accuserait un site parfaitement sain.
        expect($resultat->verdict)->toBeNull();
    });

    it('fonctionne sans aucune clé', function (): void {
        Http::fake(['archive.org/*' => Http::response(['archived_snapshots' => [
            'closest' => ['timestamp' => '20180314120000'],
        ]])]);

        expect(app(AnalyseTechniqueAdapter::class)
            ->analyse(sac('https://lanationbenin.info/a', TypeContenu::Lien))->echoue)->toBeFalse();
    });
});

describe('registre', function (): void {
    it('sert les moteurs simulés hors production', function (): void {
        config()->set('factcheck.fake_adapters', true);

        expect(app(RegistreDesMoteurs::class)->pour(TypeContenu::Texte))->not->toBeEmpty();
    });

    it('refuse la simulation en production', function (): void {
        config()->set('factcheck.fake_adapters', true);
        app()->detectEnvironment(fn () => 'production');

        // Une plateforme de fact-checking qui rendrait des verdicts inventés
        // se détruirait elle-même.
        expect(fn () => app(RegistreDesMoteurs::class)->pour(TypeContenu::Texte))
            ->toThrow(RuntimeException::class);
    });

    it('ne sert que les moteurs réellement implémentés', function (): void {
        config()->set('factcheck.fake_adapters', false);

        $moteurs = array_map(
            static fn ($a): string => $a->nom()->value,
            app(RegistreDesMoteurs::class)->pour(TypeContenu::Texte),
        );

        // Le catalogue compte dix-neuf entrées ; toutes n'ont pas encore leur
        // adaptateur, et l'analyse tourne avec celles qui existent.
        expect($moteurs)->toContain('gemini')
            ->and($moteurs)->not->toContain('tineye');
    });

    it('ajoute OpenAI sur l’Image, et OpenAI + DeepSeek sur Vidéo/Audio — NEWINSTRUCTION32', function (): void {
        expect(MoteurAnalyse::pour(TypeContenu::Image))->toContain(MoteurAnalyse::OpenAI)
            ->and(MoteurAnalyse::pour(TypeContenu::Video))
            ->toContain(MoteurAnalyse::OpenAI)->toContain(MoteurAnalyse::DeepSeek)
            ->and(MoteurAnalyse::pour(TypeContenu::Audio))
            ->toContain(MoteurAnalyse::OpenAI)->toContain(MoteurAnalyse::DeepSeek);
    });
});

describe('transcription — OpenAI (NEWINSTRUCTION32)', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        config()->set('services.openai.key', 'cle-de-test');
    });

    /*
     | Chemin UNIQUE par appel, jamais "test.mp3" réutilisé d'un test à
     | l'autre : sur Windows, `TranscriptionAdapter::executer()` ouvre le
     | fichier avec `fopen()` et ne le ferme jamais lui-même — Guzzle s'en
     | charge normalement, mais `Http::fake()` intercepte avant que le flux
     | ne soit consommé. Le verrou qui en résulte sur le MÊME nom de fichier
     | fait `exists()` rendre faux, par intermittence, dans le test SUIVANT.
     */
    function sacAvecFichier(TypeContenu $type = TypeContenu::Audio): SacDePreuves
    {
        $chemin = 'verifications/'.Str::uuid().'.mp3';
        Storage::disk('local')->put($chemin, 'faux contenu audio');

        return new SacDePreuves('Une affirmation à vérifier.', $type, cheminFichier: $chemin);
    }

    it('ne rend JAMAIS de verdict : il transcrit, il ne juge pas', function (): void {
        Http::fake(['api.openai.com/*' => Http::response(['text' => 'Ceci est la transcription du fichier.'])]);

        $resultat = app(TranscriptionAdapter::class)->analyse(sacAvecFichier());

        expect($resultat->verdict)->toBeNull()
            ->and($resultat->echoue)->toBeFalse()
            ->and($resultat->raisonnement)->toBe('Ceci est la transcription du fichier.');
    });

    it('envoie le fichier au bon endpoint, en une seule requête', function (): void {
        Http::fake(['api.openai.com/*' => Http::response(['text' => 'x'])]);

        app(TranscriptionAdapter::class)->analyse(sacAvecFichier());

        Http::assertSent(fn ($requete): bool => str_contains($requete->url(), 'audio/transcriptions'));
        Http::assertSentCount(1);
    });

    it('s’abstient sans fichier joint, sans appeler le fournisseur', function (): void {
        $resultat = app(TranscriptionAdapter::class)->analyse(new SacDePreuves('x', TypeContenu::Audio));

        expect($resultat->echoue)->toBeTrue();
        Http::assertNothingSent();
    });

    it('s’abstient quand le fournisseur rend un texte vide', function (): void {
        Http::fake(['api.openai.com/*' => Http::response(['text' => ''])]);

        expect(app(TranscriptionAdapter::class)->analyse(sacAvecFichier())->echoue)->toBeTrue();
    });

    it('ne travaille que sur Audio et Vidéo', function (): void {
        $adaptateur = app(TranscriptionAdapter::class);

        expect($adaptateur->supporte(TypeContenu::Audio))->toBeTrue()
            ->and($adaptateur->supporte(TypeContenu::Video))->toBeTrue()
            ->and($adaptateur->supporte(TypeContenu::Image))->toBeFalse()
            ->and($adaptateur->supporte(TypeContenu::Texte))->toBeFalse()
            ->and($adaptateur->supporte(TypeContenu::Lien))->toBeFalse();
    });

    it('accepte directement un conteneur vidéo — pas besoin d’en extraire l’audio localement', function (): void {
        Storage::disk('local')->put('verifications/clip.mp4', 'faux contenu video');
        Http::fake(['api.openai.com/*' => Http::response(['text' => 'Paroles entendues dans la vidéo.'])]);

        $resultat = app(TranscriptionAdapter::class)->analyse(
            new SacDePreuves('x', TypeContenu::Video, cheminFichier: 'verifications/clip.mp4'),
        );

        expect($resultat->raisonnement)->toBe('Paroles entendues dans la vidéo.');
    });
});

describe('OpenAI — vision sur l’Image (NEWINSTRUCTION32)', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        config()->set('services.openai.key', 'cle-de-test');
    });

    it('joint l’image au message utilisateur en image_url', function (): void {
        Storage::disk('local')->put('verifications/photo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ));

        Http::fake(['api.openai.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode(['verdict' => 'VRAI', 'confidence' => 70])],
        ]]])]);

        app(OpenAiAdapter::class)->analyse(new SacDePreuves(
            'Cette image montre X.', TypeContenu::Image, cheminFichier: 'verifications/photo.png',
        ));

        Http::assertSent(function ($requete): bool {
            $contenu = data_get($requete->data(), 'messages.1.content');

            return is_array($contenu)
                && ($contenu[1]['type'] ?? null) === 'image_url'
                && str_starts_with((string) data_get($contenu[1], 'image_url.url'), 'data:image/png;base64,');
        });
    });

    it('garde un message texte simple hors de l’Image', function (): void {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode(['verdict' => 'VRAI', 'confidence' => 70])],
        ]]])]);

        app(OpenAiAdapter::class)->analyse(sac());

        Http::assertSent(fn ($requete): bool => is_string(data_get($requete->data(), 'messages.1.content')));
    });
});

describe('détecteur d\'IA — AI or Not', function (): void {
    beforeEach(function (): void {
        config()->set('services.aiornot.key', 'cle-de-test');
    });

    /** Réponse texte, au format exact de la fiche « Reports by Modality ». */
    function reponseAiOrNot(bool $detecte, float $confiance): array
    {
        return [
            'id' => '3c90c3cc-0d44-4b50-8888-8dd25736052a',
            'report' => ['ai_text' => ['confidence' => $confiance, 'is_detected' => $detecte]],
            'metadata' => ['word_count' => 150, 'character_count' => 750],
            'created_at' => '2026-09-10T05:31:56Z',
        ];
    }

    /** Un texte assez long pour franchir le plancher de 250 caractères. */
    function sacLong(): SacDePreuves
    {
        return new SacDePreuves(str_repeat('Une affirmation à vérifier, écrite au long. ', 8), TypeContenu::Texte);
    }

    it('ne rend JAMAIS de verdict : ce moteur mesure un style, pas une véracité', function (): void {
        Http::fake(['api.aiornot.com/*' => Http::response(reponseAiOrNot(true, 0.95))]);

        $resultat = app(AiOrNotAdapter::class)->analyse(sacLong());

        /*
         | LE test de ce fichier. Un détecteur d'IA dit qu'un texte RESSEMBLE à
         | une production automatique — pas qu'il est faux. Confondre les deux
         | ferait condamner une information vraie sur la seule foi de son style,
         | et ces détecteurs se trompent surtout contre les personnes qui
         | n'écrivent pas dans leur langue maternelle. Sur une plateforme
         | béninoise, ce n'est pas une hypothèse d'école.
         */
        expect($resultat->verdict)->toBeNull()
            ->and($resultat->echoue)->toBeFalse()
            ->and($resultat->confiance)->toBe(95);
    });

    it('dit dans son raisonnement ce qu’il ne sait pas', function (): void {
        Http::fake(['api.aiornot.com/*' => Http::response(reponseAiOrNot(true, 0.88))]);

        $raisonnement = app(AiOrNotAdapter::class)->analyse(sacLong())->raisonnement;

        // La seule chose qui empêche un signal de style d'être relu comme un
        // verdict de véracité par quelqu'un qui n'a pas lu le code.
        expect($raisonnement)->toContain('RESSEMBLANCE')
            ->and($raisonnement)->toContain('faux positifs');
    });

    it('n’appelle même pas le fournisseur sous son plancher de 250 caractères', function (): void {
        Http::fake(['api.aiornot.com/*' => Http::response(reponseAiOrNot(true, 0.9))]);

        $resultat = app(AiOrNotAdapter::class)->analyse(new SacDePreuves('Trop court.', TypeContenu::Texte));

        /*
         | Le fournisseur rendrait 422, la clé serait comptée en échec, et le
         | journal parlerait d'une panne là où il n'y a qu'un texte bref. La
         | plupart des affirmations soumises tiennent en une phrase : c'est la
         | règle, pas l'exception.
         */
        expect($resultat->echoue)->toBeTrue();
        Http::assertNothingSent();
    });

    it('convertit le score du fournisseur, qui va de 0 à 1 et non de 0 à 100', function (): void {
        Http::fake(['api.aiornot.com/*' => Http::response(reponseAiOrNot(true, 0.42))]);

        // Lu tel quel, 0.42 devenait 0 : le signal le plus fort passait pour
        // une absence de signal.
        expect(app(AiOrNotAdapter::class)->analyse(sacLong())->confiance)->toBe(42);
    });

    it('ignore un sous-modèle en erreur au lieu de le lire comme un « rien détecté »', function (): void {
        // La doc prévient : « Some endpoints return null responses for sub-model
        // errors without failing completely. »
        Http::fake(['api.aiornot.com/*' => Http::response([
            'id' => 'x', 'report' => ['ai_text' => null], 'created_at' => '2026-09-10T05:31:56Z',
        ])]);

        expect(app(AiOrNotAdapter::class)->analyse(sacLong())->echoue)->toBeTrue();
    });

    it('s’abstient quand rien n’est détecté, plutôt que d’affirmer le contraire', function (): void {
        Http::fake(['api.aiornot.com/*' => Http::response(reponseAiOrNot(false, 0.03))]);

        // Ne rien détecter n'est pas la preuve d'une écriture humaine : le
        // moteur se tait, il ne témoigne pas en faveur du texte.
        expect(app(AiOrNotAdapter::class)->analyse(sacLong())->echoue)->toBeTrue();
    });

    it('ne travaille ni sur un lien ni sur un son : le fournisseur n’a pas d’endpoint pour eux', function (): void {
        $adaptateur = app(AiOrNotAdapter::class);

        expect($adaptateur->supporte(TypeContenu::Texte))->toBeTrue()
            ->and($adaptateur->supporte(TypeContenu::Image))->toBeTrue()
            ->and($adaptateur->supporte(TypeContenu::Video))->toBeTrue()
            ->and($adaptateur->supporte(TypeContenu::Audio))->toBeFalse()
            ->and($adaptateur->supporte(TypeContenu::Lien))->toBeFalse();
    });

    it('met la clé au repos sur un 402 : des crédits épuisés ne sont pas une panne', function (): void {
        Http::fake(['api.aiornot.com/*' => Http::response(['error' => 'no credits'], 402)]);

        // 402 n'était couvert nulle part : chaque analyse repayait un
        // aller-retour pour se faire refuser de la même façon.
        expect(app(AiOrNotAdapter::class)->analyse(sacLong())->echoue)->toBeTrue();
    });
});

describe('une clé morte doit se voir', function (): void {
    /*
    |--------------------------------------------------------------------------
    | Google rend 400, pas 401, pour une clé invalide
    |--------------------------------------------------------------------------
    |
    | Trouvé le 10 septembre 2026 en sondant les vrais fournisseurs depuis le
    | poste. Trois moteurs Google sortaient « Injoignable », sous un message qui
    | ne tranchait pas : « le fournisseur refuse la clé OU ne répond pas ».
    |
    | Ils rendaient en réalité `400 API_KEY_INVALID` — une seule clé, morte,
    | partagée par Fact Check, Custom Search, Knowledge Graph et Vision. Aucune
    | garde ne s'en saisissait : 400 n'était dans aucune liste, l'échec
    | ressemblait à n'importe quel autre, et chaque analyse repayait
    | l'aller-retour.
    |
    | Les deux cas n'appellent pas le même geste — régénérer une clé, ou ouvrir
    | une sortie réseau. Il fallait donc les distinguer, SANS prendre tout 400
    | pour une clé morte : le code sert aussi aux requêtes mal formées, et
    | mettre une clé saine au repos six heures sur une maladresse serait pire
    | que le défaut d'origine.
    |
    */

    beforeEach(function (): void {
        config()->set('services.google_factcheck.key', 'cle-de-test');
    });

    it('reconnaît une clé refusée derrière un 400, et le DIT', function (): void {
        Http::fake(['factchecktools.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 400,
                'message' => 'API key not valid. Please pass a valid API key.',
                'status' => 'INVALID_ARGUMENT',
            ],
        ], 400)]);

        $adaptateur = app(GoogleFactCheckAdapter::class);

        expect($adaptateur->joignable())->toBeFalse()
            // C'est cette phrase que lit l'exploitant sur « Santé du système ».
            ->and($adaptateur->precision())->toContain('clé refusée');
    });

    it('ne prend PAS un 400 ordinaire pour une clé morte', function (): void {
        Http::fake(['factchecktools.googleapis.com/*' => Http::response([
            'error' => ['code' => 400, 'message' => 'Request contains an invalid argument.'],
        ], 400)]);

        $adaptateur = app(GoogleFactCheckAdapter::class);

        // Injoignable, oui — mais la clé n'est pas accusée, et elle ne part pas
        // au repos pour six heures sur une requête maladroite.
        expect($adaptateur->joignable())->toBeFalse()
            ->and($adaptateur->precision())->not->toContain('clé refusée')
            ->and($adaptateur->precision())->toContain('HTTP 400');
    });

    it('ne recopie JAMAIS la clé dans ce qu’il donne à lire', function (): void {
        config()->set('services.google_factcheck.key', 'AIzaSyUNSECRETQUINEDOITPASSORTIR');

        Http::fake(['factchecktools.googleapis.com/*' => Http::response([
            'error' => ['code' => 400, 'message' => 'API key not valid.', 'status' => 'INVALID_ARGUMENT'],
        ], 400)]);

        $adaptateur = app(GoogleFactCheckAdapter::class);
        $adaptateur->joignable();

        // Un état de moteur se recopie dans un courriel au support et se colle
        // dans un ticket.
        expect((string) $adaptateur->precision())->not->toContain('AIzaSyUNSECRET');
    });
});

describe('le référentiel crédite enfin les sources', function (): void {
    /*
    |--------------------------------------------------------------------------
    | Ce que `trusted_sources` ne faisait pas, alors qu'il existait pour ça
    |--------------------------------------------------------------------------
    |
    | La table était là, l'écran d'administration permettait de noter chaque
    | source à la main, et AUCUN adaptateur ne lisait cette note. Le commentaire
    | de `GoogleCustomSearchAdapter::fiabilite()` l'annonçait pourtant depuis
    | l'origine : « elle sera remplacée par la table trusted_sources […] en
    | attendant ». Cet « en attendant » a duré, et le référentiel est passé de
    | 22 à 408 fiches sans que rien ne change au score d'une vérification.
    |
    | Concrètement : `lemonde.fr` valait 55 comme un blog anonyme, et amender
    | une crédibilité dans le back-office n'avait aucun effet sur une analyse.
    |
    */

    beforeEach(function (): void {
        Cache::flush();

        $famille = SourceCategory::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Agences de presse', 'slug' => 'agences-de-presse',
            'default_credibility' => 90, 'sort_order' => 30,
        ]);

        TrustedSource::query()->create([
            'uuid' => (string) Str::uuid(),
            'source_category_id' => $famille->id,
            'name' => 'Une agence référencée', 'domain' => 'agence-referencee.test',
            'url' => 'https://agence-referencee.test', 'credibility' => 92, 'is_active' => true,
        ]);
    });

    it('donne à une source référencée la note de la rédaction, pas une supposition', function (): void {
        config()->set('services.gemini.keys', ['cle-a']);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
            'content' => ['parts' => [['text' => json_encode(['verdict' => 'VRAI', 'confidence' => 70])]]],
            'groundingMetadata' => ['groundingChunks' => [
                ['web' => ['uri' => 'https://agence-referencee.test/article', 'title' => 'Un article']],
            ]],
        ]]])]);

        $preuves = app(GeminiAdapter::class)->analyse(sac())->preuves;

        // 92 vient de la fiche, pas du 75 forfaitaire du code.
        expect(collect($preuves)->firstWhere('url', 'https://agence-referencee.test/article')?->fiabilite)
            ->toBe(92);
    });

    it('ne touche à RIEN pour une source inconnue du référentiel', function (): void {
        config()->set('services.gemini.keys', ['cle-a']);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
            'content' => ['parts' => [['text' => json_encode(['verdict' => 'VRAI', 'confidence' => 70])]]],
            'groundingMetadata' => ['groundingChunks' => [
                ['web' => ['uri' => 'https://parfaitement-inconnu.test/page', 'title' => 'Page']],
            ]],
        ]]])]);

        $preuves = app(GeminiAdapter::class)->analyse(sac())->preuves;

        /*
         | Le garde-fou de ce changement. L'immense majorité des domaines
         | rencontrés ne sont PAS au référentiel : ils gardent exactement le
         | score d'hier. Aucun rapport déjà rendu ne serait relu autrement.
         */
        expect(collect($preuves)->firstWhere('url', 'https://parfaitement-inconnu.test/page')?->fiabilite)
            ->toBe(75);
    });

    it('laisse la décision de la rédaction l’emporter sur l’heuristique de domaine', function (): void {
        config()->set('services.google_cse.key', 'cle');
        config()->set('services.google_cse.id', 'cx');

        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [[
            'title' => 'Un article', 'link' => 'https://agence-referencee.test/depeche', 'snippet' => 'Extrait.',
        ]]])]);

        $preuves = app(GoogleCustomSearchAdapter::class)->analyse(sac())->preuves;

        /*
         | Sans référentiel, l'heuristique aurait rendu 55 — le défaut d'un
         | domaine qu'elle ne reconnaît pas. Une fiche du référentiel est une
         | DÉCISION éditoriale ; l'heuristique est une supposition sur la forme
         | d'un nom de domaine. Quand les deux parlent, la décision gagne.
         */
        expect(collect($preuves)->firstWhere('url', 'https://agence-referencee.test/depeche')?->fiabilite)
            ->toBe(92);
    });

    it('suit la rédaction quand elle change une note', function (): void {
        config()->set('services.gemini.keys', ['cle-a']);

        TrustedSource::query()->where('domain', 'agence-referencee.test')
            ->update(['credibility' => 40]);

        // Le cache du dépôt est vidé à chaque écriture du back-office ; ici on
        // reproduit ce geste, sans quoi le test mesurerait le cache.
        app(DepotSourcesFiables::class)->oublier();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
            'content' => ['parts' => [['text' => json_encode(['verdict' => 'VRAI', 'confidence' => 70])]]],
            'groundingMetadata' => ['groundingChunks' => [
                ['web' => ['uri' => 'https://agence-referencee.test/article', 'title' => 'Un article']],
            ]],
        ]]])]);

        // Tout l'intérêt de l'écran d'administration : ce qu'on y décide doit
        // se voir dans la vérification suivante.
        expect(collect(app(GeminiAdapter::class)->analyse(sac())->preuves)
            ->firstWhere('url', 'https://agence-referencee.test/article')?->fiabilite)
            ->toBe(40);
    });
});

describe('contrat V-10 — citation par source_id, phase 7', function (): void {
    beforeEach(function (): void {
        config()->set('services.gemini.keys', ['cle-de-test']);
        config()->set('services.openai.key', 'cle-de-test');
        config()->set('services.deepseek.key', 'cle-de-test');
    });

    it('Gemini : résout un source_id présent dans le paquet, jamais une URL devinée', function (): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(reponseGemini([
            'verdict' => 'FAUX', 'confidence' => 88,
            'source_ids' => [['id' => 'SRC_001', 'stance' => 'REFUTES']],
            // Présentes dans la réponse MAIS doivent être ignorées : un
            // paquet actif retire tout pouvoir de citation libre au modèle.
            'sources' => [['name' => 'Inventée', 'url' => 'https://jamais-verifie.test/page', 'stance' => 'REFUTES']],
        ]))]);

        $preuves = app(GeminiAdapter::class)->analyse(sac()->avecPaquet(paquetAUnElement()))->preuves;

        expect($preuves)->toHaveCount(1)
            ->and($preuves[0]->url)->toBe('https://arcep.bj/note-reelle')
            ->and($preuves[0]->recuperee)->toBeTrue()
            // Le grounding Google, lui non plus, ne doit plus passer : il
            // pointerait vers gouv.bj/communique (voir reponseGemini()).
            ->and(collect($preuves)->pluck('url'))->not->toContain('https://gouv.bj/communique')
            ->and(collect($preuves)->pluck('url'))->not->toContain('https://jamais-verifie.test/page');
    });

    it('Gemini : un identifiant absent du paquet est IGNORÉ, jamais traité comme une URL', function (): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(reponseGemini([
            'verdict' => 'NON_VERIFIABLE', 'confidence' => 10,
            'source_ids' => [['id' => 'SRC_999', 'stance' => 'NEUTRAL']],
        ]))]);

        $preuves = app(GeminiAdapter::class)->analyse(sac()->avecPaquet(paquetAUnElement()))->preuves;

        expect($preuves)->toBeEmpty();
    });

    it('Gemini : un paquet VIDE ne laisse citer aucune preuve', function (): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(reponseGemini([
            'verdict' => 'NON_VERIFIABLE', 'confidence' => 5,
        ]))]);

        $preuves = app(GeminiAdapter::class)->analyse(sac()->avecPaquet(PaquetDePreuves::vide()))->preuves;

        expect($preuves)->toBeEmpty();
    });

    it('OpenAI : résout un source_id et marque la preuve RÉELLEMENT récupérée', function (): void {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode([
                'verdict' => 'VRAI', 'confidence' => 80,
                'source_ids' => [['id' => 'SRC_001', 'stance' => 'SUPPORTS']],
                'sources' => [['name' => 'Inventée', 'url' => 'https://jamais-verifie.test/x', 'stance' => 'SUPPORTS']],
            ])],
        ]]])]);

        $preuves = app(OpenAiAdapter::class)->analyse(sac()->avecPaquet(paquetAUnElement()))->preuves;

        // À la différence de son format libre — toujours CITÉE, PAS
        // OUVERTE, puisqu'OpenAI n'a pas d'accès web — une preuve résolue
        // depuis le paquet a RÉELLEMENT été récupérée par le collecteur.
        expect($preuves)->toHaveCount(1)
            ->and($preuves[0]->url)->toBe('https://arcep.bj/note-reelle')
            ->and($preuves[0]->recuperee)->toBeTrue();
    });

    it('DeepSeek : résout un source_id et marque la preuve RÉELLEMENT récupérée', function (): void {
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode([
                'verdict' => 'FAUX', 'confidence' => 75,
                'source_ids' => [['id' => 'SRC_001', 'stance' => 'REFUTES']],
            ])],
        ]]])]);

        $preuves = app(DeepSeekAdapter::class)->analyse(sac()->avecPaquet(paquetAUnElement()))->preuves;

        expect($preuves)->toHaveCount(1)
            ->and($preuves[0]->url)->toBe('https://arcep.bj/note-reelle')
            ->and($preuves[0]->recuperee)->toBeTrue();
    });

    it('sans paquet attaché, les trois adaptateurs gardent le comportement libre d\'avant cette phase', function (): void {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(reponseGemini([
                'verdict' => 'VRAI', 'confidence' => 70,
                'sources' => [['name' => 'ARCEP', 'url' => 'https://arcep.bj/libre', 'stance' => 'SUPPORTS']],
            ])),
            'api.openai.com/*' => Http::response(['choices' => [[
                'message' => ['content' => json_encode([
                    'verdict' => 'VRAI', 'confidence' => 70,
                    'sources' => [['name' => 'ARCEP', 'url' => 'https://arcep.bj/libre', 'stance' => 'SUPPORTS']],
                ])],
            ]]]),
        ]);

        // $sac SANS avecPaquet() : exactement l'appel d'avant cette phase.
        expect(collect(app(GeminiAdapter::class)->analyse(sac())->preuves)->pluck('url'))
            ->toContain('https://arcep.bj/libre');
        expect(collect(app(OpenAiAdapter::class)->analyse(sac())->preuves)->pluck('url'))
            ->toContain('https://arcep.bj/libre');
    });
});
