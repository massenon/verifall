<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\EmpreinteContenu;
use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\ReponseDejaConnue;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\MotifMouvementUnite;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Citizen\Verifier;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedFact;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Économie d'appels — empreinte, faits connus, limitation de débit
|--------------------------------------------------------------------------
| Une rumeur virale est signalée des centaines de fois. Relancer douze moteurs
| payants à chaque signalement coûterait cher pour rendre douze fois la même
| réponse.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 50])->save();

    Queue::fake();
});

function soumettre(User $utilisateur, string $affirmation): Verification
{
    return app(SoumissionVerificationService::class)
        ->soumettre($utilisateur, TypeContenu::Texte, $affirmation);
}

describe('empreinte de contenu', function (): void {
    it('reconnaît la même rumeur malgré la mise en forme', function (string $variante): void {
        $empreintes = app(EmpreinteContenu::class);
        $reference = $empreintes->pour('La taxe WhatsApp entre en vigueur lundi');

        // Une rumeur circule en se déformant : majuscules, accents perdus au
        // passage d'un clavier à l'autre, ponctuation ajoutée.
        expect($empreintes->pour($variante))->toBe($reference);
    })->with([
        'LA TAXE WHATSAPP ENTRE EN VIGUEUR LUNDI',
        '  la   taxe whatsapp entre en vigueur lundi !!! ',
        'La taxe WhatsApp entre en vigueur lundi.',
    ]);

    it('distingue deux rumeurs différentes', function (): void {
        $empreintes = app(EmpreinteContenu::class);

        expect($empreintes->pour('La taxe WhatsApp entre en vigueur lundi'))
            ->not->toBe($empreintes->pour('La taxe WhatsApp est annulée'));
    });

    it('écarte les mots-outils du rapprochement', function (): void {
        $mots = app(EmpreinteContenu::class)->motsCles('Le gouvernement a annoncé que la taxe est reportée');

        // « le », « que », « est » se retrouvent dans toutes les phrases et
        // rapprocheraient n'importe quoi de n'importe quoi.
        expect($mots)->not->toContain('le')
            ->and($mots)->not->toContain('que')
            ->and($mots)->toContain('gouvernement');
    });

    it('mesure la proximité de deux formulations', function (): void {
        $empreintes = app(EmpreinteContenu::class);

        $proche = $empreintes->proximite(
            'Le gouvernement instaure une taxe WhatsApp au Bénin',
            'taxe WhatsApp gouvernement Bénin instauration',
        );

        $lointain = $empreintes->proximite(
            'Le gouvernement instaure une taxe WhatsApp au Bénin',
            'Un nouveau stade sera construit à Parakou',
        );

        expect($proche)->toBeGreaterThan(0.5)->and($lointain)->toBeLessThan(0.2);
    });

    it('est posée à la soumission', function (): void {
        expect(soumettre($this->citoyen, 'Une affirmation à vérifier maintenant.')->content_fingerprint)
            ->not->toBeNull();
    });
});

describe('faits déjà tranchés', function (): void {
    beforeEach(function (): void {
        VerifiedFact::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Taxe WhatsApp au Bénin',
            'summary' => 'Aucune taxe sur WhatsApp n\'a été instaurée. L\'ARCEP l\'a démenti.',
            'verdict' => VerdictFactCheck::Faux->value,
            'keywords' => 'taxe whatsapp benin arcep reseaux sociaux vigueur',
            'sources' => [
                ['url' => 'https://arcep.bj/communique', 'name' => 'ARCEP Bénin'],
                ['url' => 'https://gouv.bj/dementi', 'name' => 'Gouvernement du Bénin'],
            ],
            'is_published' => true,
        ]);
    });

    it('répond sans appeler aucun moteur', function (): void {
        $verification = soumettre($this->citoyen, 'La taxe WhatsApp entre en vigueur au Bénin lundi.');

        app(OrchestrateurVerification::class)->executer($verification);

        $verification->refresh();

        // Quand une rédaction a déjà statué, aucun modèle n'a besoin d'en
        // rediscuter — et la réponse est meilleure.
        expect($verification->engineRuns()->count())->toBe(0)
            ->and($verification->report->verdict)->toBe(VerdictFactCheck::Faux)
            ->and($verification->report->confidence)->toBe(100);
    });

    it('rend les unités : la réponse n\'a rien coûté', function (): void {
        $verification = soumettre($this->citoyen, 'La taxe WhatsApp entre en vigueur au Bénin lundi.');

        expect($this->citoyen->fresh()->units)->toBe(49);

        app(OrchestrateurVerification::class)->executer($verification);

        // Faire payer une réponse qui n'a rien coûté serait indéfendable — et
        // la plateforme a tout intérêt à ce que la même rumeur soit signalée
        // par le plus de monde possible.
        expect($this->citoyen->fresh()->units)->toBe(50)
            ->and(UnitLog::where('reason', MotifMouvementUnite::Remboursement->value)
                ->where('label', 'like', '%non facturée%')->exists())->toBeTrue();
    });

    it('cite la source officielle du fait', function (): void {
        $verification = soumettre($this->citoyen, 'La taxe WhatsApp entre en vigueur au Bénin lundi.');

        app(OrchestrateurVerification::class)->executer($verification);

        expect($verification->fresh()->evidences()->first()->source_url)
            ->toBe('https://arcep.bj/communique');
    });

    it('compte les réutilisations', function (): void {
        foreach (['La taxe WhatsApp entre en vigueur au Bénin.', 'Taxe WhatsApp Bénin ARCEP vigueur.'] as $texte) {
            app(OrchestrateurVerification::class)->executer(soumettre($this->citoyen, $texte));
        }

        // Mesure l'économie réelle, et dit quelles rumeurs circulent le plus.
        expect(VerifiedFact::first()->hits)->toBe(2);
    });

    it('ne rapproche pas deux rumeurs qui partagent seulement du vocabulaire', function (): void {
        $verification = soumettre($this->citoyen, 'Le gouvernement construit un stade à Parakou cette année.');

        app(OrchestrateurVerification::class)->executer($verification);

        // Servir le verdict de l'une pour l'autre serait l'erreur la plus grave
        // possible sur un fact-check.
        expect($verification->fresh()->engineRuns()->count())->toBeGreaterThan(0);
    });

    it('ignore un fait non publié', function (): void {
        VerifiedFact::first()->update(['is_published' => false]);

        $verification = soumettre($this->citoyen, 'La taxe WhatsApp entre en vigueur au Bénin lundi.');
        app(OrchestrateurVerification::class)->executer($verification);

        expect($verification->fresh()->engineRuns()->count())->toBeGreaterThan(0);
    });
});

describe('analyse récente identique', function (): void {
    it('reprend la réponse au lieu de relancer les moteurs', function (): void {
        // « taxe » fait converger les moteurs simules : sans verdict tranche,
        // la premiere analyse partirait en arbitrage et ne serait pas
        // reutilisable — le test ne prouverait alors rien.
        $texte = 'La taxe sur les transferts bancaires double le mois prochain.';

        $premiere = soumettre($this->citoyen, $texte);
        app(OrchestrateurVerification::class)->executer($premiere);

        $autre = User::factory()->actif()->create();
        $autre->forceFill(['units' => 10])->save();

        $seconde = soumettre($autre, $texte);
        app(OrchestrateurVerification::class)->executer($seconde);

        $seconde->refresh();

        expect($seconde->engineRuns()->count())->toBe(0)
            ->and($seconde->estUneReprise())->toBeTrue()
            ->and($seconde->report->verdict)->toBe($premiere->fresh()->report->verdict);
    });

    it('recopie les preuves au lieu de les partager', function (): void {
        $texte = 'La taxe sur les carburants change encore la semaine prochaine.';

        $premiere = soumettre($this->citoyen, $texte);
        app(OrchestrateurVerification::class)->executer($premiere);

        $seconde = soumettre($this->citoyen, $texte);
        app(OrchestrateurVerification::class)->executer($seconde);

        // Le rapport doit rester lisible si l'analyse d'origine disparaît —
        // compte effacé, par exemple.
        expect($seconde->fresh()->estUneReprise())->toBeTrue()
            ->and($seconde->fresh()->evidences()->count())
            ->toBe($premiere->fresh()->evidences()->count())
            ->and($seconde->fresh()->evidences()->count())->toBeGreaterThan(0);
    });

    it('relance après le délai de fraîcheur', function (): void {
        $texte = 'Une rumeur ancienne qui refait surface aujourd\'hui.';

        $premiere = soumettre($this->citoyen, $texte);
        app(OrchestrateurVerification::class)->executer($premiere);

        $this->travel(ReponseDejaConnue::JOURS_DE_FRAICHEUR + 1)->days();

        $seconde = soumettre($this->citoyen, $texte);
        app(OrchestrateurVerification::class)->executer($seconde);

        // Une information peut être démentie la semaine suivante : servir un
        // verdict périmé serait pire que de ne rien servir.
        expect($seconde->fresh()->engineRuns()->count())->toBeGreaterThan(0);
    });

    it('ne reprend jamais une analyse remboursée', function (): void {
        $texte = 'Une affirmation impossible à recouper par les moteurs.';

        config()->set('factcheck.decision.moteurs_minimum', 99);
        $premiere = soumettre($this->citoyen, $texte);
        app(OrchestrateurVerification::class)->executer($premiere);

        config()->set('factcheck.decision.moteurs_minimum', 2);
        $seconde = soumettre($this->citoyen, $texte);
        app(OrchestrateurVerification::class)->executer($seconde);

        // Elle était incomplète : c'est précisément pourquoi elle a été
        // remboursée.
        expect($seconde->fresh()->engineRuns()->count())->toBeGreaterThan(0);
    });
});

describe('limitation de débit', function (): void {
    it('refuse au-delà du plafond horaire', function (): void {
        config()->set('factcheck.runtime.analyses_par_heure', 2);
        RateLimiter::clear('verification:'.$this->citoyen->id);

        $composant = Livewire::actingAs($this->citoyen)->test(Verifier::class);

        for ($i = 0; $i < 3; $i++) {
            $composant->set('texte', 'Une affirmation numéro '.$i.' à vérifier ici.')->call('lancer');
        }

        // Les unités bornent la dépense d'une personne ; ce plafond borne la
        // charge sur des services tiers facturés à l'appel.
        expect(Verification::count())->toBe(2);
    });

    it('ne consomme pas le quota quand la soumission échoue', function (): void {
        config()->set('factcheck.runtime.analyses_par_heure', 2);
        RateLimiter::clear('verification:'.$this->citoyen->id);

        $this->citoyen->forceFill(['units' => 0])->save();

        Livewire::actingAs($this->citoyen)->test(Verifier::class)
            ->set('texte', 'Une affirmation qu\'on ne peut pas payer ici.')
            ->call('lancer');

        $this->citoyen->forceFill(['units' => 10])->save();

        Livewire::actingAs($this->citoyen)->test(Verifier::class)
            ->set('texte', 'Une affirmation que l\'on peut payer cette fois.')
            ->call('lancer');

        // Une soumission refusée faute de solde ne doit pas grignoter le quota.
        expect(Verification::count())->toBe(1);
    });
});
