<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\CompositionDuRapport;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| NEWINSTRUCTION28 — une preuve institutionnelle et récente doit pouvoir
| trancher là où les moteurs divergent, jamais un simple vote majoritaire
|--------------------------------------------------------------------------
| Cas réel observé : un claim sensible au temps (« l'actuel titulaire d'une
| fonction publique ») a divisé les trois moteurs d'analyse sémantique —
| FAUX / VRAI / NON_VERIFIABLE — alors que deux fact-checks institutionnels
| DATÉS, trouvés par ailleurs, établissaient sans ambiguïté la réponse : le
| plus récent des deux contredisait directement le claim. Avant ce correctif,
| ce cas retombait systématiquement sur TROMPEUR + arbitrage humain — un
| verdict de vote, pas de preuve.
|
| Entité et pays FICTIFS à dessein : le claim se vérifie par son
| COMPORTEMENT temporel (une transition datée, deux preuves contradictoires),
| jamais par le nom d'un titulaire réel qui peut quitter sa fonction — même
| règle que AucunTitulaireDansLesFixturesTest.
|
| Moteurs réels, jamais simulés ({@see AdaptateurSimule} ne date aucune de
| ses preuves, donc ne peut structurellement pas exercer ce correctif) :
| Gemini/OpenAI/DeepSeek/Google Fact Check via Http::fake, exactement le
| patron de AdaptateursReelsTest.php.
*/

const CLAIM_TEMPOREL_SENSIBLE = 'Jean Doremus est l\'actuel président de la République de Kadovie.';

function reponseGeminiAdjudication(string $verdict, int $confidence): array
{
    return ['candidates' => [[
        'content' => ['parts' => [['text' => json_encode([
            'verdict' => $verdict,
            'confidence' => $confidence,
            'reasoning_fr' => 'Analyse du modèle.',
        ])]]],
    ]]];
}

function fakerLesQuatreMoteurs(): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(reponseGeminiAdjudication('FAUX', 100)),
        'api.openai.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode(['verdict' => 'NON_VERIFIABLE', 'confidence' => 50])],
        ]]]),
        'api.deepseek.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode(['verdict' => 'VRAI', 'confidence' => 95])],
        ]]]),
        // Deux fact-checks institutionnels DATÉS et CONTRADICTOIRES : l'ancien
        // confirme le claim, le plus récent le contredit directement — c'est
        // exactement la preuve d'une succession à une fonction publique.
        'factchecktools.googleapis.com/*' => Http::response(['claims' => [
            [
                'text' => CLAIM_TEMPOREL_SENSIBLE,
                'claimReview' => [[
                    'publisher' => ['name' => 'Presse de Kadovie'],
                    'textualRating' => 'Vrai',
                    'url' => 'https://presse-kadovie.test/ancien-mandat',
                    'reviewDate' => '2024-01-10T00:00:00Z',
                ]],
            ],
            [
                'text' => CLAIM_TEMPOREL_SENSIBLE,
                'claimReview' => [[
                    'publisher' => ['name' => 'Agence Officielle de Kadovie'],
                    'textualRating' => 'Faux',
                    'url' => 'https://agence-kadovie.test/nouveau-president',
                    'reviewDate' => '2026-09-20T00:00:00Z',
                ]],
            ],
        ]]),
    ]);
}

beforeEach(function (): void {
    config([
        'factcheck.fake_adapters' => false,
        'services.gemini.keys' => ['cle-de-test'],
        'services.openai.key' => 'cle-de-test',
        'services.deepseek.key' => 'cle-de-test',
        'services.google_factcheck.key' => 'cle-de-test',
        // Jamais configuré : ce moteur doit s'abstenir sans bruit, comme
        // chaque adaptateur sans clé le fait déjà (voir AdaptateursReelsTest).
        'services.aiornot.key' => null,
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->citoyen = User::factory()->actif()->create(['units' => 20]);
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    Queue::fake();
});

it('une preuve institutionnelle plus récente tranche une contradiction ENTRE MOTEURS, jamais un vote majoritaire', function (): void {
    fakerLesQuatreMoteurs();

    $verification = app(SoumissionVerificationService::class)
        ->soumettre($this->citoyen, TypeContenu::Texte, CLAIM_TEMPOREL_SENSIBLE);

    app(OrchestrateurVerification::class)->executer($verification);
    $verification->refresh();

    // AVANT ce correctif : TROMPEUR ("contenu douteux") + RevueHumaine,
    // par simple absence de majorité claire entre les moteurs.
    expect($verification->report->verdict)->toBe(VerdictFactCheck::Faux)
        ->and($verification->report->composition_status)->toBe(CompositionDuRapport::Homogene)
        ->and($verification->status)->toBe(StatutVerification::Terminee)
        ->and($verification->report->published_at)->not->toBeNull()
        // La synthèse explique le résultat par la PREUVE, jamais par
        // « les moteurs divergent » — exactement ce que NEWINSTRUCTION28
        // reprochait à la version précédente.
        ->and($verification->report->summary)
        ->toContain('Agence Officielle de Kadovie')
        ->not->toContain('un vérificateur humain examine ce dossier');
});

it('cas inverse : si c\'est la preuve CONFIRMANT le claim qui est la plus récente, le verdict est VRAI', function (): void {
    // Même scénario, dates inversées : la preuve la plus récente confirme
    // désormais le claim plutôt que de le contredire. Test explicitement
    // demandé par NEWINSTRUCTION28 pour écarter un correctif qui favoriserait
    // silencieusement un verdict plutôt que l'autre.
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(reponseGeminiAdjudication('VRAI', 100)),
        'api.openai.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode(['verdict' => 'NON_VERIFIABLE', 'confidence' => 50])],
        ]]]),
        'api.deepseek.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode(['verdict' => 'FAUX', 'confidence' => 95])],
        ]]]),
        'factchecktools.googleapis.com/*' => Http::response(['claims' => [
            [
                'text' => CLAIM_TEMPOREL_SENSIBLE,
                'claimReview' => [[
                    'publisher' => ['name' => 'Agence Officielle de Kadovie'],
                    'textualRating' => 'Faux',
                    'url' => 'https://agence-kadovie.test/ancien-dementi',
                    'reviewDate' => '2024-01-10T00:00:00Z',
                ]],
            ],
            [
                'text' => CLAIM_TEMPOREL_SENSIBLE,
                'claimReview' => [[
                    'publisher' => ['name' => 'Presse de Kadovie'],
                    'textualRating' => 'Vrai',
                    'url' => 'https://presse-kadovie.test/confirmation-recente',
                    'reviewDate' => '2026-09-20T00:00:00Z',
                ]],
            ],
        ]]),
    ]);

    $verification = app(SoumissionVerificationService::class)
        ->soumettre($this->citoyen, TypeContenu::Texte, CLAIM_TEMPOREL_SENSIBLE);

    app(OrchestrateurVerification::class)->executer($verification);

    expect($verification->refresh()->report->verdict)->toBe(VerdictFactCheck::Vrai);
});

it('sans preuve datée pour départager, le comportement d\'avant ce correctif reste intact : arbitrage humain', function (): void {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(reponseGeminiAdjudication('FAUX', 100)),
        'api.openai.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode(['verdict' => 'NON_VERIFIABLE', 'confidence' => 50])],
        ]]]),
        'api.deepseek.com/*' => Http::response(['choices' => [[
            'message' => ['content' => json_encode(['verdict' => 'VRAI', 'confidence' => 95])],
        ]]]),
        // Aucun fact-check antérieur trouvé cette fois : rien ne permet de
        // départager, et le correctif ne doit RIEN changer à ce cas.
        'factchecktools.googleapis.com/*' => Http::response(['claims' => []]),
    ]);

    $verification = app(SoumissionVerificationService::class)
        ->soumettre($this->citoyen, TypeContenu::Texte, CLAIM_TEMPOREL_SENSIBLE);

    app(OrchestrateurVerification::class)->executer($verification);
    $verification->refresh();

    expect($verification->report->verdict)->toBe(VerdictFactCheck::Trompeur)
        ->and($verification->status)->toBe(StatutVerification::RevueHumaine)
        ->and($verification->report->summary)->toContain('un vérificateur humain examine ce dossier');
});
