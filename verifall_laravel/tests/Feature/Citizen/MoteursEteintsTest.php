<?php

declare(strict_types=1);

use App\Core\Application\Reglages\Reglages;
use App\Core\FactChecking\Adapters\OpenAiAdapter;
use App\Core\FactChecking\Adapters\TranscriptionAdapter;
use App\Core\FactChecking\Services\RegistreDesMoteurs;
use App\Core\FactChecking\Services\SanteDesMoteurs;
use App\Enums\EtatMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Livewire\Citizen\Verifier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Éteindre un moteur est une DÉCISION, pas une panne
|--------------------------------------------------------------------------
|
| Arrêtées le 1er septembre 2026 par le porteur du projet, et chacune pour une
| raison différente :
|
|   · TinEye est un service PAYANT que le budget ne permet pas encore ;
|   · Vision dépend de la clé Google, que Google refuse aujourd'hui ;
|   · aucun fournisseur de transcription n'est souscrit.
|
| Trois propriétés à garantir, et la troisième est celle qu'on oublie :
|
|   1. un moteur éteint n'est PAS APPELÉ, même avec une clé valide ;
|   2. l'écran le dit « Éteint » et non « Clé absente » — sinon quelqu'un part
|      chercher une panne qui n'existe pas ;
|   3. **tout se rouvre d'une case**, sans toucher au code.
|
*/

beforeEach(function (): void {
    Cache::flush();
    config()->set('factcheck.fake_adapters', false);
});

it('n’appelle PAS un moteur éteint, même avec sa clé', function () {
    config()->set('services.tineye.key', 'une-cle-parfaitement-valide');

    $moteurs = array_map(
        static fn ($a): string => $a->nom()->value,
        app(RegistreDesMoteurs::class)->pour(TypeContenu::Image),
    );

    expect($moteurs)->not->toContain(MoteurAnalyse::TinEye->value)
        ->and($moteurs)->not->toContain(MoteurAnalyse::Vision->value);
});

it('dit « Éteint » et non « Clé absente »', function () {
    /*
     | La confusion coûterait une demi-journée : quelqu'un lit « Clé absente »
     | sur TinEye, part chercher la clé, la trouve absente — et conclut à un
     | oubli de configuration au lieu d'une décision de budget.
     */
    Http::fake(['*' => Http::response([], 200)]);

    $releve = app(SanteDesMoteurs::class)->mesurer();

    $etats = collect($releve['moteurs'])->keyBy('moteur');

    expect($etats[MoteurAnalyse::TinEye->value]['etat'])->toBe(EtatMoteur::Desactive)
        ->and($etats[MoteurAnalyse::Vision->value]['etat'])->toBe(EtatMoteur::Desactive)
        ->and($etats[MoteurAnalyse::Transcription->value]['etat'])->toBe(EtatMoteur::Desactive)
        // Éteint ne compte pas dans un verdict : c'est bien un retrait.
        ->and(EtatMoteur::Desactive->compteDansLeScore())->toBeFalse();
});

it('ne SONDE pas un moteur éteint', function () {
    // Sur un service payant, la sonde EST la facture.
    Http::fake();

    config()->set('services.tineye.key', 'une-cle-valide');

    app(SanteDesMoteurs::class)->mesurer();

    Http::assertNotSent(fn ($requete): bool => str_contains($requete->url(), 'tineye'));
});

it('se rallume d’une case, sans toucher au code', function () {
    expect(app(RegistreDesMoteurs::class)->estAllume(MoteurAnalyse::TinEye))->toBeFalse();

    app(Reglages::class)->set('moteur_actif_'.MoteurAnalyse::TinEye->value, '1');

    expect(app(RegistreDesMoteurs::class)->estAllume(MoteurAnalyse::TinEye))->toBeTrue();
});

it('porte OpenAI, désormais', function () {
    // Il faisait partie des six moteurs « non portés ». La clé est valide, le
    // poids est de 0,70 : il ne manquait que la classe.
    $adaptateur = app(RegistreDesMoteurs::class)->adaptateurReel(MoteurAnalyse::OpenAI);

    expect($adaptateur)->toBeInstanceOf(OpenAiAdapter::class)
        ->and($adaptateur->nom())->toBe(MoteurAnalyse::OpenAI);
});

describe('vidéo et audio, fermés côté citoyen', function (): void {
    beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->citoyen = User::factory()->actif()->create();
        $this->citoyen->assignRole(UserRole::Citoyen->value);
        $this->citoyen->forceFill(['units' => 50])->save();
    });

    it('refuse le choix et DIT pourquoi', function () {
        /*
         | Sans transcription, il n'y a pas de texte à analyser dans un fichier
         | audio. Laisser choisir le type ferait débiter une unité pour un
         | rapport vide.
         */
        Livewire::actingAs($this->citoyen)
            ->test(Verifier::class)
            ->call('choisirType', TypeContenu::Audio->value)
            // Le type ne change PAS.
            ->assertSet('type', TypeContenu::Texte->value)
            ->assertDispatched('toast');
    });

    it('laisse passer les types qui fonctionnent', function () {
        Livewire::actingAs($this->citoyen)
            ->test(Verifier::class)
            ->call('choisirType', TypeContenu::Image->value)
            ->assertSet('type', TypeContenu::Image->value);
    });

    it('marque les boutons fermés sans les retirer', function () {
        // Les retirer laisserait croire que la plateforme n'analyse pas la
        // vidéo : c'est une indisponibilité, pas un périmètre.
        Livewire::actingAs($this->citoyen)
            ->test(Verifier::class)
            ->assertSee(TypeContenu::Video->libelle())
            ->assertSee(TypeContenu::Audio->libelle())
            ->assertSeeHtml('is-ferme');
    });

    it('ROUVRE les boutons dès qu’un moteur de transcription existe', function () {
        /*
         | La propriété qui compte : la fermeture est DÉDUITE, jamais codée en
         | dur. Le jour où un fournisseur est souscrit et l'adaptateur écrit,
         | les deux boutons se rouvrent sans qu'on touche à cet écran.
         |
         | NEWINSTRUCTION32 : l'adaptateur existe désormais
         | ({@see TranscriptionAdapter}, OpenAI Whisper / gpt-4o-transcribe).
         | Seule la DEUXIÈME condition — le réglage administrateur — reste à
         | remplir, et c'est elle que ce test actionne.
         */
        config()->set('services.openai.key', 'cle-de-test');
        app(Reglages::class)->set('moteur_actif_'.MoteurAnalyse::Transcription->value, '1');

        $registre = app(RegistreDesMoteurs::class);

        expect($registre->estAllume(MoteurAnalyse::Transcription))->toBeTrue()
            ->and($registre->adaptateurReel(MoteurAnalyse::Transcription))
            ->toBeInstanceOf(TranscriptionAdapter::class);

        Livewire::actingAs($this->citoyen)
            ->test(Verifier::class)
            ->call('choisirType', TypeContenu::Video->value)
            ->assertSet('type', TypeContenu::Video->value);
    });

    it('reste fermé tant que le réglage n’est pas activé, même avec l’adaptateur disponible', function () {
        // L'existence de la classe ne suffit pas : c'est une DÉCISION
        // d'exploitation, pas une conséquence automatique du code écrit.
        config()->set('services.openai.key', 'cle-de-test');

        Livewire::actingAs($this->citoyen)
            ->test(Verifier::class)
            ->call('choisirType', TypeContenu::Audio->value)
            ->assertSet('type', TypeContenu::Texte->value);
    });
});
