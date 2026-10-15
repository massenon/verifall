<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\CalculDeLaComposition;
use App\Core\FactChecking\Services\ExtractionDesAffirmations;
use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\ActeDeLangage;
use App\Enums\CompositionDuRapport;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Contrat V-8 — extraction des claims et composition du rapport
|--------------------------------------------------------------------------
| Trois niveaux, du plus rapide au plus coûteux :
|   1. `CalculDeLaComposition` en unitaire pur — les tests V8-A à V8-I ;
|   2. `ExtractionDesAffirmations` simulée — les sept scénarios de la phase 2 ;
|   3. l'orchestrateur de bout en bout — claim_id, idempotence, remboursement,
|      et une vraie composition MIXTE avec les moteurs simulés.
|
| Les deux affirmations ci-dessous ont été choisies empiriquement DANS CET
| ENVIRONNEMENT DE TEST précis (SQLite, réglages par défaut tout juste
| semés) : aucun mot-clé ne force leur verdict, c'est la pluralité des
| moteurs simulés qui décide — exactement comme en production avec de vrais
| moteurs. Vérifié avec un test-sonde jetable plutôt qu'en tinker : le
| registre de moteurs actifs dépend des `Reglages` persistés, qui diffèrent
| entre la base de développement et la base de test vierge — une sonde
| menée ailleurs donne un verdict DIFFÉRENT pour le même texte.
*/
const CLAIM_VRAI_EMPIRIQUE = 'Le prix du carburant a baisse ce trimestre.';
const CLAIM_FAUX_EMPIRIQUE = 'La capitale economique du Benin est Cotonou.';

function sacTexte(string $texte): SacDePreuves
{
    return new SacDePreuves($texte, TypeContenu::Texte);
}

describe('composition — contrat V-8, tests obligatoires', function (): void {
    beforeEach(function (): void {
        $this->composition = app(CalculDeLaComposition::class);
    });

    it('V8-A : VRAI + VRAI → homogène', function (): void {
        $c = $this->composition->pour([VerdictFactCheck::Vrai, VerdictFactCheck::Vrai]);
        expect($c)->toBe(CompositionDuRapport::Homogene);
    });

    it('V8-B : FAUX + FAUX → homogène', function (): void {
        $c = $this->composition->pour([VerdictFactCheck::Faux, VerdictFactCheck::Faux]);
        expect($c)->toBe(CompositionDuRapport::Homogene);
    });

    it('V8-C : VRAI + FAUX → mixte', function (): void {
        $c = $this->composition->pour([VerdictFactCheck::Vrai, VerdictFactCheck::Faux]);
        expect($c)->toBe(CompositionDuRapport::Mixte);
    });

    it('V8-D : VRAI + TROMPEUR → mixte', function (): void {
        $c = $this->composition->pour([VerdictFactCheck::Vrai, VerdictFactCheck::Trompeur]);
        expect($c)->toBe(CompositionDuRapport::Mixte);
    });

    it('V8-E : VRAI + NON_VERIFIABLE → homogène (décision verrouillée)', function (): void {
        $c = $this->composition->pour([VerdictFactCheck::Vrai, VerdictFactCheck::NonVerifiable]);
        expect($c)->toBe(CompositionDuRapport::Homogene);

        $verdict = $this->composition->verdictScalaire([VerdictFactCheck::Vrai, VerdictFactCheck::NonVerifiable]);
        expect($verdict)->toBe(VerdictFactCheck::Vrai);
    });

    it('V8-F : trois NON_VERIFIABLE → insuffisante ; FAUX + deux NON_VERIFIABLE → homogène', function (): void {
        $c1 = $this->composition->pour([
            VerdictFactCheck::NonVerifiable, VerdictFactCheck::NonVerifiable, VerdictFactCheck::NonVerifiable,
        ]);
        expect($c1)->toBe(CompositionDuRapport::Insuffisante);

        $c2 = $this->composition->pour([
            VerdictFactCheck::Faux, VerdictFactCheck::NonVerifiable, VerdictFactCheck::NonVerifiable,
        ]);
        expect($c2)->toBe(CompositionDuRapport::Homogene);
    });

    it('FAUX + TROMPEUR → mixte (règle générale, au-delà des six cas nommés)', function (): void {
        $c = $this->composition->pour([VerdictFactCheck::Faux, VerdictFactCheck::Trompeur]);
        expect($c)->toBe(CompositionDuRapport::Mixte);
    });

    it('un claim seul tranché reproduit le comportement d\'avant cette phase', function (VerdictFactCheck $v): void {
        expect($this->composition->pour([$v]))->toBe(CompositionDuRapport::Homogene)
            ->and($this->composition->verdictScalaire([$v]))->toBe($v);
    })->with([VerdictFactCheck::Vrai, VerdictFactCheck::Faux, VerdictFactCheck::Trompeur]);

    it('un claim seul NON_VERIFIABLE est INSUFFISANT, pas homogène : rien n\'a été tranché', function (): void {
        // Distinct du cas précédent à dessein : `Homogene` promet que les
        // claims tranchés concordent. Un unique `NonVerifiable` n'a rien
        // tranché du tout — mais le verdict scalaire reste NonVerifiable,
        // pas null, exactement le rapport rendu avant cette phase.
        expect($this->composition->pour([VerdictFactCheck::NonVerifiable]))->toBe(CompositionDuRapport::Insuffisante)
            ->and($this->composition->verdictScalaire([VerdictFactCheck::NonVerifiable]))->toBe(VerdictFactCheck::NonVerifiable);
    });

    it('verdictScalaire ne vaut jamais un mot-valise « mixte » : NULL, ou rien d\'autre', function (): void {
        $v = $this->composition->verdictScalaire([VerdictFactCheck::Vrai, VerdictFactCheck::Faux]);
        expect($v)->toBeNull();
    });

    it('aucune pondération : permuter l\'ordre des claims ne change pas la composition', function (): void {
        $a = $this->composition->pour([VerdictFactCheck::Vrai, VerdictFactCheck::Faux, VerdictFactCheck::Trompeur]);
        $b = $this->composition->pour([VerdictFactCheck::Trompeur, VerdictFactCheck::Vrai, VerdictFactCheck::Faux]);
        expect($a)->toBe($b);
    });

    it('la fonction est pure : zéro claim transmis → insuffisante, jamais une erreur', function (): void {
        expect($this->composition->pour([]))->toBe(CompositionDuRapport::Insuffisante);
    });
});

describe('extraction — les sept scénarios de la phase 2', function (): void {
    beforeEach(function (): void {
        $this->extraction = app(ExtractionDesAffirmations::class);
    });

    it('une affirmation simple reste un seul claim', function (): void {
        $claims = $this->extraction->extraire(sacTexte('Le pont de Cotonou a été inauguré en 2020.'));

        expect($claims)->toHaveCount(1)
            ->and($claims[0]->acteDeLangage)->toBe(ActeDeLangage::Affirmation);
    });

    it('une question reste une QUESTION — jamais convertie en affirmation', function (): void {
        $claims = $this->extraction->extraire(sacTexte('Qui dirige actuellement cette institution ?'));

        expect($claims)->toHaveCount(1)
            ->and($claims[0]->acteDeLangage)->toBe(ActeDeLangage::Question);
    });

    it('une négation reste une AFFIRMATION à part entière, jamais retournée ni supprimée', function (): void {
        $texte = "Le vaccin ne cause pas l'autisme.";
        $claims = $this->extraction->extraire(sacTexte($texte));

        expect($claims)->toHaveCount(1)
            ->and($claims[0]->acteDeLangage)->toBe(ActeDeLangage::Affirmation)
            ->and($claims[0]->texte)->toBe($texte);
    });

    it('trois phrases déclarées produisent trois claims distincts', function (): void {
        $claims = $this->extraction->extraire(sacTexte(
            'Le pont a été inauguré en 2020. Le tarif a doublé en janvier. Une nouvelle loi entre en vigueur demain.'
        ));

        expect($claims)->toHaveCount(3);

        foreach ($claims as $claim) {
            expect($claim->acteDeLangage)->toBe(ActeDeLangage::Affirmation);
        }
    });

    it('une opinion n\'est ni vraie ni fausse — extraite, jamais soumise à un verdict', function (): void {
        $claims = $this->extraction->extraire(sacTexte('À mon avis, cette politique est une honte.'));

        expect($claims)->toHaveCount(1)
            ->and($claims[0]->acteDeLangage)->toBe(ActeDeLangage::Opinion)
            ->and($claims[0]->acteDeLangage->peutPorterUnVerdict())->toBeFalse();
    });

    it('une prédiction ne se vérifie pas avant son échéance', function (): void {
        $claims = $this->extraction->extraire(sacTexte('Le prix du pain va bientôt augmenter.'));

        expect($claims)->toHaveCount(1)
            ->and($claims[0]->acteDeLangage)->toBe(ActeDeLangage::Prediction)
            ->and($claims[0]->acteDeLangage->peutPorterUnVerdict())->toBeFalse();
    });

    it('un contenu non factuel ne porte aucune proposition vérifiable', function (): void {
        $claims = $this->extraction->extraire(sacTexte('Quelle belle journée !'));

        expect($claims)->toHaveCount(1)
            ->and($claims[0]->acteDeLangage)->toBe(ActeDeLangage::NonFactuel)
            ->and($claims[0]->acteDeLangage->peutPorterUnVerdict())->toBeFalse();
    });

    it('ne rend jamais un tableau vide, même sur un texte vide', function (): void {
        expect($this->extraction->extraire(sacTexte('')))->toHaveCount(1);
    });
});

describe('orchestration multi-claims — de bout en bout', function (): void {
    beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->citoyen = User::factory()->actif()->create();
        $this->citoyen->assignRole(UserRole::Citoyen->value);
        $this->citoyen->forceFill(['units' => 20])->save();
        Queue::fake();
    });

    function verifDeTexte(User $utilisateur, string $texte): Verification
    {
        return app(SoumissionVerificationService::class)
            ->soumettre($utilisateur, TypeContenu::Texte, $texte);
    }

    it('V8-G : un seul claim reproduit le rapport d\'avant cette phase', function (): void {
        $verification = verifDeTexte($this->citoyen, CLAIM_FAUX_EMPIRIQUE);
        app(OrchestrateurVerification::class)->executer($verification);
        $verification->refresh();

        expect($verification->claims)->toHaveCount(1)
            ->and($verification->report->composition_status)->toBe(CompositionDuRapport::Homogene)
            ->and($verification->report->verdict)->toBe(VerdictFactCheck::Faux)
            ->and($verification->report->estMixte())->toBeFalse();
    });

    it('tague chaque moteur et chaque preuve avec le bon claim_id', function (): void {
        $verification = verifDeTexte($this->citoyen, CLAIM_FAUX_EMPIRIQUE);
        app(OrchestrateurVerification::class)->executer($verification);
        $verification->refresh();

        $claim = $verification->claims->first();

        expect($verification->engineRuns)->not->toBeEmpty();

        foreach ($verification->engineRuns as $run) {
            expect($run->claim_id)->toBe($claim->id);
        }
    });

    it('V8-J : une QUESTION ne reçoit jamais de verdict, et n\'entre pas dans la composition', function (): void {
        $verification = verifDeTexte($this->citoyen, 'Qui dirige actuellement cette institution ?');
        app(OrchestrateurVerification::class)->executer($verification);
        $verification->refresh();

        expect($verification->claims)->toHaveCount(1);

        $claim = $verification->claims->first();

        expect($claim->acte_de_langage)->toBe(ActeDeLangage::Question)
            ->and($claim->verdict)->toBeNull()
            // Aucune question ne peut produire de composition HOMOGENE/MIXTE :
            // elle ne participe à aucune des deux, donc le rapport est
            // INSUFFISANT — exactement le même traitement qu'une opinion.
            ->and($verification->report->composition_status)->toBe(CompositionDuRapport::Insuffisante)
            ->and($verification->report->verdict)->toBeNull();
    });

    it('V8-K : aucune affirmation vérifiable → remboursement, sans débit net', function (): void {
        $utilisateur = $this->citoyen;
        $soldeAvant = $utilisateur->units;

        $verification = verifDeTexte($utilisateur, 'À mon avis, cette politique est une honte.');
        app(OrchestrateurVerification::class)->executer($verification);
        $verification->refresh();
        $utilisateur->refresh();

        expect($verification->report->composition_status)->toBe(CompositionDuRapport::Insuffisante)
            ->and($verification->report->verdict)->toBeNull()
            ->and($verification->refunded_at)->not->toBeNull()
            ->and($utilisateur->units)->toBe($soldeAvant);
    });

    it('ne ré-extrait jamais les claims d\'une vérification déjà analysée (idempotence de la reprise)', function (): void {
        /*
         | Simule une interruption RÉELLE du pipeline : une panne technique
         | entre l'extraction et le rapport laisse les claims déjà écrits,
         | mais aucun rapport — exactement `apresEchecTechnique()` suivi
         | d'une reprise programmée. Reproduire ce scénario en resetant une
         | vérification DÉJÀ RAPPORTÉE violerait l'unicité
         | `analysis_reports.verification_id`, ce qu'aucune reprise réelle ne
         | fait jamais : une reprise n'a lieu que tant qu'aucun rapport
         | n'existe encore.
         */
        $verification = verifDeTexte($this->citoyen, CLAIM_FAUX_EMPIRIQUE);

        $claimPreexistant = $verification->claims()->create([
            'position' => 0,
            'texte' => CLAIM_FAUX_EMPIRIQUE,
            'acte_de_langage' => ActeDeLangage::Affirmation->value,
        ]);

        app(OrchestrateurVerification::class)->executer($verification->fresh());
        $verification->refresh();

        expect($verification->claims)->toHaveCount(1)
            ->and($verification->claims->first()->id)->toBe($claimPreexistant->id)
            ->and($verification->report->verdict)->toBe(VerdictFactCheck::Faux);
    });

    it('MIXTE de bout en bout : deux claims aux verdicts distincts composent un rapport mixte', function (): void {
        $verification = verifDeTexte(
            $this->citoyen,
            CLAIM_VRAI_EMPIRIQUE.' '.CLAIM_FAUX_EMPIRIQUE,
        );
        app(OrchestrateurVerification::class)->executer($verification);
        $verification->refresh();

        expect($verification->claims)->toHaveCount(2)
            ->and($verification->report->composition_status)->toBe(CompositionDuRapport::Mixte)
            ->and($verification->report->verdict)->toBeNull()
            ->and($verification->report->estMixte())->toBeTrue();

        $verdicts = $verification->claims->pluck('verdict')->map(fn (?VerdictFactCheck $v) => $v?->value)->all();
        expect($verdicts)->toContain('true')->toContain('false');

        // L'écran citoyen ne doit PAS planter sur `verdict` null — exactement
        // le défaut que ce contrat corrige dans quatre vues.
        $this->actingAs($this->citoyen)
            ->get(route('citizen.report', ['verification' => $verification->uuid]))
            ->assertOk()
            ->assertSee(CompositionDuRapport::Mixte->libelleRapport());
    });
});
