<?php

declare(strict_types=1);

use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\FactChecking\Adapters\AdaptateurSimule;
use App\Core\FactChecking\Services\FactCheckScoringService;
use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\RegistreDesMoteurs;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Core\FactChecking\ValueObjects\IndiceHaine;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\MotifMouvementUnite;
use App\Enums\PositionPreuve;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Jobs\AnalyserVerification;
use App\Livewire\Citizen\Verifier;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Vérification — soumission, orchestration, scoring
|--------------------------------------------------------------------------
| Deux fautes seraient impardonnables sur une plateforme de fact-checking :
| prélever des unités sans rendre de rapport, et publier un faux consensus.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 34])->save();
});

/** Fabrique un avis de moteur. */
function avis(MoteurAnalyse $moteur, VerdictFactCheck $verdict, int $confiance = 80): VerdictMoteur
{
    return VerdictMoteur::rendu($moteur, $verdict, $confiance, [
        new Preuve('https://gouv.bj/a', 'Portail officiel', 'extrait', PositionPreuve::Confirme, 95),
    ]);
}

describe('coût et soumission', function (): void {
    it('applique les coûts affichés par le prototype', function (TypeContenu $type, int $attendu): void {
        expect($type->cout())->toBe($attendu);
    })->with([
        [TypeContenu::Texte, 1],
        [TypeContenu::Lien, 1],
        [TypeContenu::Image, 2],
        [TypeContenu::Video, 5],
        [TypeContenu::Audio, 5],
    ]);

    it('débite le coût et met l\'analyse en file', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une rumeur qui circule depuis hier soir.',
        );

        expect($this->citoyen->fresh()->units)->toBe(33)
            ->and($verification->status)->toBe(StatutVerification::EnFile)
            // Figé sur la ligne : le tarif peut changer avant le remboursement.
            ->and($verification->cost_units)->toBe(1);
    });

    it('journalise le débit avec la vérification pour origine', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Image, 'Une image virale.',
        );

        $ligne = UnitLog::latest('id')->first();

        expect($ligne->delta)->toBe(-2)
            ->and($ligne->reason)->toBe(MotifMouvementUnite::Verification)
            ->and($ligne->source_id)->toBe($verification->id);
    });

    it('ne laisse aucune vérification orpheline quand le solde manque', function (): void {
        $this->citoyen->forceFill(['units' => 1])->save();

        expect(fn () => app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Image, 'Une capture à vérifier.',
        ))->toThrow(SoldeInsuffisant::class);

        // Créer d'abord et débiter ensuite hors transaction laisserait une
        // demande que rien n'analyserait jamais.
        expect(Verification::count())->toBe(0)
            ->and($this->citoyen->fresh()->units)->toBe(1);
    });
});

describe('scoring', function (): void {
    it('tranche quand un verdict devance nettement les autres', function (): void {
        $decision = app(FactCheckScoringService::class)->decider([
            avis(MoteurAnalyse::GoogleFactCheck, VerdictFactCheck::Faux, 95),
            avis(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 90),
            avis(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 85),
        ]);

        expect($decision->verdict)->toBe(VerdictFactCheck::Faux)
            ->and($decision->statut)->toBe(StatutVerification::Terminee)
            ->and($decision->confiance)->toBeGreaterThanOrEqual(60);
    });

    it('refuse de trancher quand les moteurs se contredisent', function (): void {
        $decision = app(FactCheckScoringService::class)->decider([
            avis(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 85),
            avis(MoteurAnalyse::DeepSeek, VerdictFactCheck::Vrai, 85),
        ]);

        // Publier un faux consensus sur une plateforme de fact-checking serait
        // exactement ce qu'elle combat.
        expect($decision->statut)->toBe(StatutVerification::RevueHumaine)
            ->and($decision->reserve)->not->toBeNull();
    });

    it('qualifie de trompeuse une affirmation partagée entre vrai et faux', function (): void {
        $decision = app(FactCheckScoringService::class)->decider([
            avis(MoteurAnalyse::Gemini, VerdictFactCheck::Vrai, 80),
            avis(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 80),
        ]);

        expect($decision->verdict)->toBe(VerdictFactCheck::Trompeur);
    });

    it('annonce une analyse partielle sous deux moteurs exploitables', function (): void {
        $decision = app(FactCheckScoringService::class)->decider([
            avis(MoteurAnalyse::Gemini, VerdictFactCheck::Vrai, 95),
            VerdictMoteur::echec(MoteurAnalyse::DeepSeek, 'panne'),
        ]);

        expect($decision->partielle)->toBeTrue()
            ->and($decision->verdict)->toBe(VerdictFactCheck::NonVerifiable)
            ->and($decision->ouvreDroitAuRemboursement())->toBeTrue();
    });

    it('ignore les moteurs qui ne portent pas de verdict', function (): void {
        // Un détecteur d'IA dit qu'un texte ressemble à une production
        // automatique — pas qu'il est faux.
        $decision = app(FactCheckScoringService::class)->decider([
            avis(MoteurAnalyse::Gemini, VerdictFactCheck::Vrai, 95),
            avis(MoteurAnalyse::DetecteurIA, VerdictFactCheck::Faux, 99),
            avis(MoteurAnalyse::Transcription, VerdictFactCheck::Faux, 99),
        ]);

        expect($decision->partielle)->toBeTrue();
    });

    it('ne fait pas dépendre le score du nombre de moteurs interrogés', function (): void {
        $scoring = app(FactCheckScoringService::class);

        $troisMoteurs = $scoring->decider([
            avis(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 80),
            avis(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 80),
            avis(MoteurAnalyse::OpenAI, VerdictFactCheck::Faux, 80),
        ]);

        $sixMoteurs = $scoring->decider([
            avis(MoteurAnalyse::Gemini, VerdictFactCheck::Faux, 80),
            avis(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux, 80),
            avis(MoteurAnalyse::OpenAI, VerdictFactCheck::Faux, 80),
            avis(MoteurAnalyse::PerplexitySearch, VerdictFactCheck::Faux, 80),
            avis(MoteurAnalyse::GoogleCustomSearch, VerdictFactCheck::Faux, 80),
            avis(MoteurAnalyse::KnowledgeGraph, VerdictFactCheck::Faux, 80),
        ]);

        // Sans normalisation, une analyse plus large paraîtrait toujours plus
        // sûre — ce qui est faux si tous disent la même chose.
        expect($sixMoteurs->confiance)->toBe($troisMoteurs->confiance);
    });

    it('détecte un verdict incohérent avec les moteurs', function (): void {
        $scoring = app(FactCheckScoringService::class);

        $resultats = [
            avis(MoteurAnalyse::Gemini, VerdictFactCheck::Faux),
            avis(MoteurAnalyse::DeepSeek, VerdictFactCheck::Faux),
            avis(MoteurAnalyse::OpenAI, VerdictFactCheck::Vrai),
        ];

        expect($scoring->verdictCoherent(VerdictFactCheck::Faux, $resultats))->toBeTrue()
            // Un rapport dont les sources contredisent son propre verdict
            // détruirait la confiance plus sûrement qu'une erreur franche.
            ->and($scoring->verdictCoherent(VerdictFactCheck::Vrai, $resultats))->toBeFalse();
    });
});

describe('orchestration', function (): void {
    it('rend toujours un rapport, même avec des moteurs en panne', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'La taxe WhatsApp entre en vigueur lundi au Bénin.',
        );

        app(OrchestrateurVerification::class)->executer($verification);

        $verification->refresh();

        expect($verification->report)->not->toBeNull()
            ->and($verification->status->estFinal())->toBeTrue()
            ->and($verification->report->summary)->not->toBeEmpty();
    });

    it('conserve la trace de chaque moteur, échecs compris', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une affirmation à vérifier en profondeur.',
        );

        app(OrchestrateurVerification::class)->executer($verification);

        /*
         | Ne garder que le verdict final rendrait la conclusion invérifiable :
         | on attend une ligne par moteur, échecs compris.
         |
         | Comptés parmi les moteurs ALLUMÉS, et non parmi tous ceux que
         | `MoteurAnalyse::pour()` énumère. Le test comparait aux sept du
         | catalogue alors que six tournent : `GoogleCustomSearch` est éteint par
         | décision d'exploitation — Google a retiré « rechercher sur l'ensemble
         | du Web » des moteurs CSE récents, et l'appel échoue en silence. Un
         | moteur qu'on a choisi de ne pas appeler ne laisse pas de trace, et
         | c'est normal ; le test était rouge depuis cette extinction.
         */
        $registre = app(RegistreDesMoteurs::class);

        $allumes = array_filter(
            MoteurAnalyse::pour(TypeContenu::Texte),
            static fn (MoteurAnalyse $moteur): bool => $registre->estAllume($moteur),
        );

        expect($allumes)->not->toBeEmpty();
        expect($verification->engineRuns()->count())->toBe(count($allumes));
    });

    it('ne compte pas deux fois une source citée par plusieurs moteurs', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une rumeur largement relayée sur les réseaux.',
        );

        app(OrchestrateurVerification::class)->executer($verification);

        $urls = $verification->evidences()->pluck('source_url');

        // Cinq citations d'une même source donneraient l'illusion de cinq
        // confirmations indépendantes — le biais même que le fact-checking défait.
        expect($urls->count())->toBe($urls->unique()->count());
    });

    it('reprogramme au lieu de rendre un rapport creux', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une rumeur dont personne ne connaît l\'origine.',
        );

        // On force le cas « trop peu de moteurs répondent ».
        config()->set('factcheck.decision.moteurs_minimum', 99);

        app(OrchestrateurVerification::class)->executer($verification);

        $verification->refresh();

        /*
         | Un rapport creux est la pire réponse possible : la personne repart
         | sans rien, avec l'impression que la plateforme ne marche pas, alors
         | que le problème est passager.
         */
        expect($verification->report)->toBeNull()
            ->and($verification->status)->toBe(StatutVerification::EnAttenteDeReprise)
            ->and($verification->attempts)->toBe(1)
            ->and($verification->retry_after)->not->toBeNull()
            ->and($verification->retry_reason)->not->toBeEmpty();

        // La reprise part d'elle-même : la personne n'a rien à faire.
        Queue::assertPushed(AnalyserVerification::class);
    });

    it('annonce un délai dans la fourchette promise', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une affirmation que rien ne permet de recouper.',
        );

        config()->set('factcheck.decision.moteurs_minimum', 99);
        app(OrchestrateurVerification::class)->executer($verification);

        // Promettre dix minutes et reprendre au bout de trois donnerait
        // l'impression d'un système erratique.
        expect($verification->fresh()->minutesAvantReprise())
            ->toBeGreaterThanOrEqual(10)
            ->toBeLessThanOrEqual(15);
    });

    it('au bout des tentatives, renonce franchement et rembourse', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une rumeur dont personne ne connaît l\'origine.',
        );

        $soldeApresDebit = $this->citoyen->fresh()->units;
        config()->set('factcheck.decision.moteurs_minimum', 99);

        $orchestrateur = app(OrchestrateurVerification::class);
        $tentatives = (int) config('factcheck.runtime.reprise.tentatives_max', 3);

        // Autant de passages que de tentatives autorisées, plus celui qui
        // renonce.
        for ($i = 0; $i <= $tentatives; $i++) {
            $orchestrateur->executer($verification->fresh());
        }

        $verification->refresh();

        // Entretenir indéfiniment un espoir serait pire que de renoncer
        // franchement.
        expect($verification->report)->not->toBeNull()
            // Le coût FIGÉ à la soumission, et non un nombre recopié : le test
            // ne peut plus dériver du type employé en fixture.
            ->and($this->citoyen->fresh()->units)->toBe($soldeApresDebit + $verification->cost_units)
            ->and($verification->refunded_at)->not->toBeNull()
            ->and(UnitLog::where('reason', MotifMouvementUnite::Remboursement->value)->exists())->toBeTrue();
    });

    it('ne rembourse jamais deux fois', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une affirmation impossible à recouper.',
        );

        config()->set('factcheck.decision.moteurs_minimum', 99);

        $orchestrateur = app(OrchestrateurVerification::class);
        $orchestrateur->executer($verification);
        $solde = $this->citoyen->fresh()->units;

        // Reprise de traitement, job rejoué : le remboursement ne doit pas
        // se rejouer avec.
        $verification->fresh()->report()->delete();
        $orchestrateur->executer($verification->fresh());

        expect($this->citoyen->fresh()->units)->toBe($solde);
    });

    it('mesure le Hate Speech Index sur le contenu soumis', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte,
            'Ces gens vont envahir la ville, il faut tous les chasser avant lundi.',
        );

        app(OrchestrateurVerification::class)->executer($verification);

        $indice = $verification->fresh()->report->indiceHaine();

        // HSI = Hate Speech Index, et non « santé/sécurité » : c'est ce que
        // disent le prototype (bloc « Hate Speech Indicator ») et le moteur de
        // juillet, contre une glose ambigue de la documentation.
        expect($indice->mesure())->toBeTrue()
            ->and($indice->score)->toBeGreaterThanOrEqual(60)
            ->and($indice->tags)->not->toBeEmpty()
            ->and($indice->alerte())->toBeTrue();
    });

    it("ne prétend pas mesurer la rhétorique quand personne ne l'a évaluée", function (): void {
        // Afficher « 0 % » sans mesure reviendrait à certifier une absence
        // qu'on n'a pas constatée — sur ce sujet, l'erreur à ne pas commettre.
        $indice = IndiceHaine::agreger([
            IndiceHaine::nonMesure(),
            IndiceHaine::nonMesure(),
        ]);

        expect($indice->mesure())->toBeFalse()
            ->and($indice->libelle())->toBe('Non évalué');
    });

    it('retient le signal le plus grave plutôt que la moyenne', function (): void {
        $indice = IndiceHaine::agreger([
            new IndiceHaine(5),
            new IndiceHaine(5),
            new IndiceHaine(85, ['Ciblage ethnique']),
        ]);

        // Un seul moteur qui repère un ciblage doit faire lever le drapeau,
        // même si trois autres n'ont rien vu. Moyenner diluerait le signal.
        expect($indice->score)->toBe(85)
            ->and($indice->tags)->toContain('Ciblage ethnique');
    });

    it('ne publie pas un rapport en arbitrage', function (): void {
        Queue::fake();

        $verification = app(SoumissionVerificationService::class)->soumettre(
            $this->citoyen, TypeContenu::Texte, 'Une affirmation qui divise les moteurs.',
        );

        config()->set('factcheck.decision.ecart_minimum', 100);

        app(OrchestrateurVerification::class)->executer($verification);

        $rapport = $verification->fresh()->report;

        // Il existe et son auteur peut le lire, mais il n'engage pas encore la
        // plateforme.
        expect($rapport)->not->toBeNull()
            ->and($rapport->published_at)->toBeNull()
            ->and($verification->fresh()->status)->toBe(StatutVerification::RevueHumaine);
    });
});

describe('écran de vérification', function (): void {
    it('refuse un visiteur anonyme', function (): void {
        $this->get(route('citizen.verify'))->assertRedirect(route('login'));
    });

    it('annonce le coût et le solde restant avant de lancer', function (): void {
        /*
         | IMAGE et non plus VIDÉO. Le test employait « video » pour disposer
         | d'un type à cinq unités ; depuis le 1er septembre 2026 vidéo et audio
         | sont FERMÉS — aucun fournisseur de transcription n'est souscrit — et
         | `choisirType` les refuse en le disant.
         |
         | Ce que ce test éprouve n'a pas changé : qu'un coût supérieur à un
         | s'affiche AVANT le débit. L'image le donne (2 unités sur 34).
         */
        $html = Livewire::actingAs($this->citoyen)->test(Verifier::class)
            ->call('choisirType', 'image')->html();

        // Une personne doit savoir ce qu'elle dépense avant de le dépenser.
        expect($html)->toContain('2 unités')
            ->and($html)->toContain('32 unités');
    });

    it('ignore un type fabriqué côté client', function (): void {
        Livewire::actingAs($this->citoyen)->test(Verifier::class)
            ->call('choisirType', 'gratuit')
            ->assertSet('type', 'text');
    });

    it('exige un contenu avant de débiter', function (): void {
        Livewire::actingAs($this->citoyen)->test(Verifier::class)
            ->set('texte', 'trop court')
            ->call('lancer')
            ->assertHasErrors('texte');

        expect($this->citoyen->fresh()->units)->toBe(34);
    });

    it('met l\'analyse en file sans faire attendre la page', function (): void {
        Queue::fake();

        Livewire::actingAs($this->citoyen)->test(Verifier::class)
            ->set('texte', 'La taxe WhatsApp entre en vigueur lundi prochain au Bénin.')
            ->call('lancer')
            ->assertHasNoErrors();

        Queue::assertPushed(AnalyserVerification::class);
        expect(Verification::count())->toBe(1);
    });

    it('empêche de lancer sans solde suffisant', function (): void {
        $this->citoyen->forceFill(['units' => 0])->save();

        $html = Livewire::actingAs($this->citoyen)->test(Verifier::class)->html();

        expect($html)->toContain('solde est insuffisant')
            ->and($html)->toContain('Rechargez votre compte');
    });

    it('ne renseigne pas sur la vérification d\'autrui', function (): void {
        Queue::fake();

        $autre = User::factory()->actif()->create();
        $autre->forceFill(['units' => 10])->save();

        $verificationAutrui = app(SoumissionVerificationService::class)->soumettre(
            $autre, TypeContenu::Texte, 'Une affirmation confidentielle et personnelle.',
        );

        $html = Livewire::actingAs($this->citoyen)->test(Verifier::class)
            ->set('enCours', $verificationAutrui->uuid)->html();

        expect($html)->not->toContain('confidentielle');
    });
});

it('refuse de tourner avec des moteurs simulés en production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $adaptateur = new AdaptateurSimule(MoteurAnalyse::Gemini);

    // Rendre des verdicts inventés sur une plateforme de fact-checking serait
    // exactement ce qu'elle combat.
    expect(fn () => $adaptateur->analyse(
        new SacDePreuves('test', TypeContenu::Texte)
    ))->toThrow(RuntimeException::class);
});
