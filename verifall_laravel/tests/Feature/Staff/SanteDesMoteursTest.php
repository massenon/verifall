<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\SanteDesMoteurs;
use App\Enums\EtatMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Le défaut que ces tests gardent
|--------------------------------------------------------------------------
|
| `RegistreDesMoteurs::etat()` répondait « disponible » à une seule question :
| une CLASSE d'adaptateur existe-t-elle ? Le 1er septembre 2026, sur la
| configuration réelle du projet, cela donnait — vérifié par appel aux
| fournisseurs :
|
|   · Perplexity annoncé « disponible » avec une clé VIDE ;
|   · Google Fact Check annoncé « disponible » avec une clé que Google refuse.
|
| Une carte « Santé du système » verte sur deux moteurs morts transforme une
| panne en confiance. Ces tests interdisent le retour de ce raccourci.
|
*/

beforeEach(function () {
    Cache::flush();
    config()->set('factcheck.fake_adapters', false);
});

/** Un état, pour un moteur donné, dans le relevé rendu par le service. */
function etatDe(array $releve, MoteurAnalyse $moteur): EtatMoteur
{
    foreach ($releve['moteurs'] as $ligne) {
        if ($ligne['moteur'] === $moteur->value) {
            return $ligne['etat'];
        }
    }

    throw new RuntimeException('Moteur absent du relevé : '.$moteur->value);
}

it('ne dit PAS opérationnel quand la clé est absente', function () {
    // Le cas exact de Perplexity au 1er septembre 2026.
    config()->set('services.perplexity.key', '');

    $releve = app(SanteDesMoteurs::class)->mesurer();

    expect(etatDe($releve, MoteurAnalyse::PerplexitySearch))->toBe(EtatMoteur::NonConfigure);
});

it('ne dit PAS opérationnel quand le fournisseur REFUSE la clé', function () {
    // Le cas exact de Google Fact Check : clé présente, et rejetée.
    config()->set('services.google_factcheck.key', 'une-cle-que-google-refuse');

    Http::fake([
        'factchecktools.googleapis.com/*' => Http::response(
            ['error' => ['code' => 400, 'message' => 'API Key not found.']],
            400,
        ),
        '*' => Http::response([], 200),
    ]);

    $releve = app(SanteDesMoteurs::class)->mesurer();

    expect(etatDe($releve, MoteurAnalyse::GoogleFactCheck))->toBe(EtatMoteur::Injoignable);
});

it('distingue « pas vérifiable » de « vérifié et refusé »', function () {
    /*
     | Perplexity n'expose aucun point d'appel gratuit en lecture seule : sa
     | sonde rend `null`. Confondre ce cas avec un refus ferait passer un moteur
     | sain pour une panne — et l'inverse, peindre du vert sur une supposition.
     */
    config()->set('services.perplexity.key', 'une-cle-presente');

    Http::fake(['*' => Http::response([], 500)]);

    $releve = app(SanteDesMoteurs::class)->mesurer();

    expect(etatDe($releve, MoteurAnalyse::PerplexitySearch))->toBe(EtatMoteur::Configure)
        ->and(EtatMoteur::Configure->compteDansLeScore())->toBeTrue();
});

it('annonce « non porté » pour les moteurs sans adaptateur', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $releve = app(SanteDesMoteurs::class)->mesurer();

    /*
     | LA LISTE A CHANGÉ le 1er septembre 2026, et le test avec.
     |
     | OpenAI a été porté — la clé était valide et le poids de 0,70 le
     | justifiait. Vision n'est plus « non porté » mais **ÉTEINT** : une
     | décision d'exploitation, sa clé Google étant refusée.
     |
     | Restent sans adaptateur : Knowledge Graph, TinEye, détecteur d'IA,
     | transcription.
     */
    expect(etatDe($releve, MoteurAnalyse::KnowledgeGraph))->toBe(EtatMoteur::NonPorte)
        ->and(EtatMoteur::NonPorte->compteDansLeScore())->toBeFalse();
});

it('annonce SIMULÉ pour tous les moteurs sous FAKE_ADAPTERS', function () {
    config()->set('factcheck.fake_adapters', true);

    Http::fake();

    $releve = app(SanteDesMoteurs::class)->mesurer();

    expect($releve['simule'])->toBeTrue()
        ->and(collect($releve['moteurs'])->pluck('etat')->unique()->all())
        ->toBe([EtatMoteur::Simule]);

    // Et rien n'a été demandé à personne : sous simulation, aucun fournisseur
    // n'a de raison d'être contacté.
    Http::assertNothingSent();
});

it('compte les clés Gemini acceptées, pas seulement leur présence', function () {
    /*
     | « 5 clés sur 5 » et « 1 clé sur 5 » sont tous deux « opérationnel », et
     | pourtant le second annonce l'arrêt de la plateforme pour l'après-midi.
     | C'est précisément le problème que la rotation existe pour repousser :
     | l'écran doit pouvoir le voir venir.
     */
    config()->set('services.gemini.keys', ['vivante', 'morte-1', 'morte-2']);

    Http::fake([
        'generativelanguage.googleapis.com/*key=vivante*' => Http::response(['models' => []], 200),
        'generativelanguage.googleapis.com/*' => Http::response([], 429),
        '*' => Http::response([], 200),
    ]);

    $releve = app(SanteDesMoteurs::class)->mesurer();

    $gemini = collect($releve['moteurs'])->firstWhere('moteur', MoteurAnalyse::Gemini->value);

    expect($gemini['etat'])->toBe(EtatMoteur::Operationnel)
        ->and($gemini['precision'])->toBe('1 clé sur 3 acceptée');
});

it('ne sonde JAMAIS au simple affichage du tableau de bord', function () {
    /*
     | Le quota gratuit de Google Custom Search est de CENT requêtes par jour.
     | Un tableau de bord qui sonde à chaque ouverture épuiserait avant midi le
     | quota du moteur qu'il surveille : le dispositif de surveillance
     | provoquerait la panne qu'il affiche.
     */
    Http::fake();

    $this->seed(RolesAndPermissionsSeeder::class);

    $membre = User::factory()->create(['otp_verified_at' => now()]);
    $membre->assignRole(UserRole::Admin->value);

    $this->actingAs($membre, 'staff')
        ->get(route('staff.dashboard'))
        ->assertOk();

    Http::assertNothingSent();
});

it('affiche « jamais sondé » plutôt qu’une date inventée', function () {
    $releve = app(SanteDesMoteurs::class)->etat();

    expect($releve['mesure_le'])->toBeNull()
        // …et les états LOCAUX restent justes : ils se connaissent sans réseau.
        ->and(etatDe($releve, MoteurAnalyse::KnowledgeGraph))->toBe(EtatMoteur::NonPorte)
        ->and(etatDe($releve, MoteurAnalyse::Vision))->toBe(EtatMoteur::Desactive);
});
