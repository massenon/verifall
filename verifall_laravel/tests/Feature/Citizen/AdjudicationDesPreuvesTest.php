<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\AdjudicationDesPreuves;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Enums\PositionPreuve;
use App\Enums\VerdictFactCheck;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Phase 8 — adjudication : les preuves votent, séparément des modèles
|--------------------------------------------------------------------------
| Lève D-05 et D-06. Trois scénarios du plan : deux sources officielles
| contradictoires → priorité temporelle ; preuve forte contre consensus de
| modèles ; absence de preuve ≠ FAUX (déjà tenu ailleurs, confirmé ici pour
| la corroboration spécifiquement).
*/

function preuveAdj(string $url, PositionPreuve $position, int $fiabilite = 60, ?Carbon $publieeLe = null): Preuve
{
    return new Preuve(url: $url, nomSource: $url, extrait: '', position: $position, fiabilite: $fiabilite, publieeLe: $publieeLe);
}

describe('corroboration — combien de VOIX, pas combien de modèles', function (): void {
    beforeEach(function (): void {
        $this->adjudication = app(AdjudicationDesPreuves::class);
    });

    it('compte les voix INDÉPENDANTES qui soutiennent le verdict retenu', function (): void {
        $preuves = [
            preuveAdj('https://lemonde.fr/a', PositionPreuve::Confirme),
            preuveAdj('https://rfi.fr/b', PositionPreuve::Confirme),
            preuveAdj('https://blog-anonyme.info/c', PositionPreuve::Contredit),
        ];

        expect($this->adjudication->corroboration($preuves, VerdictFactCheck::Vrai))->toBe(2);
    });

    it('ne compte PAS deux fois le même éditeur sous deux sous-domaines', function (): void {
        $preuves = [
            preuveAdj('https://www.lemonde.fr/a', PositionPreuve::Confirme),
            preuveAdj('https://m.lemonde.fr/b', PositionPreuve::Confirme),
        ];

        expect($this->adjudication->corroboration($preuves, VerdictFactCheck::Vrai))->toBe(1);
    });

    it('absence de preuve orientée : corroboration nulle, jamais confondue avec FAUX', function (): void {
        $preuves = [preuveAdj('https://lemonde.fr/a', PositionPreuve::Neutre)];

        expect($this->adjudication->corroboration($preuves, VerdictFactCheck::Vrai))->toBe(0);
    });
});

describe('une preuve forte contredit — même minoritaire en nombre', function (): void {
    beforeEach(function (): void {
        $this->adjudication = app(AdjudicationDesPreuves::class);
    });

    it('détecte une SEULE preuve fiable qui contredit, malgré quatre preuves faibles qui confirment', function (): void {
        $preuves = [
            preuveAdj('https://blog1.info/a', PositionPreuve::Confirme, fiabilite: 40),
            preuveAdj('https://blog2.info/b', PositionPreuve::Confirme, fiabilite: 40),
            preuveAdj('https://blog3.info/c', PositionPreuve::Confirme, fiabilite: 40),
            preuveAdj('https://blog4.info/d', PositionPreuve::Confirme, fiabilite: 40),
            preuveAdj('https://gouv.bj/communique', PositionPreuve::Contredit, fiabilite: 95),
        ];

        expect($this->adjudication->unePreuveForteContredit($preuves, VerdictFactCheck::Vrai))->toBeTrue();
    });

    it('ne s\'alarme pas d\'une preuve FAIBLE qui contredit', function (): void {
        $preuves = [
            preuveAdj('https://gouv.bj/a', PositionPreuve::Confirme, fiabilite: 95),
            preuveAdj('https://blog-anonyme.info/b', PositionPreuve::Contredit, fiabilite: 30),
        ];

        expect($this->adjudication->unePreuveForteContredit($preuves, VerdictFactCheck::Vrai))->toBeFalse();
    });

    it('ignore NON_VERIFIABLE et TROMPEUR — rien à contredire pour ces verdicts', function (): void {
        $preuves = [preuveAdj('https://gouv.bj/a', PositionPreuve::Contredit, fiabilite: 95)];

        expect($this->adjudication->unePreuveForteContredit($preuves, VerdictFactCheck::NonVerifiable))->toBeFalse()
            ->and($this->adjudication->unePreuveForteContredit($preuves, VerdictFactCheck::Trompeur))->toBeFalse();
    });
});

describe('priorité temporelle entre deux sources officielles contradictoires', function (): void {
    beforeEach(function (): void {
        $this->adjudication = app(AdjudicationDesPreuves::class);
    });

    it('retient la preuve la PLUS RÉCENTE entre deux contradictoires', function (): void {
        $ancienne = preuveAdj('https://gouv.bj/ancien', PositionPreuve::Confirme, 90, Carbon::parse('2020-01-01'));
        $recente = preuveAdj('https://gouv.bj/nouveau', PositionPreuve::Contredit, 90, Carbon::parse('2026-09-01'));

        $retenue = $this->adjudication->laPlusRecenteEntreContradictoires([$ancienne, $recente]);

        expect($retenue->url)->toBe('https://gouv.bj/nouveau');
    });

    it('ne départage rien si les deux camps ne sont pas tous les deux présents', function (): void {
        $preuves = [preuveAdj('https://gouv.bj/a', PositionPreuve::Confirme, 90, Carbon::now())];

        expect($this->adjudication->laPlusRecenteEntreContradictoires($preuves))->toBeNull();
    });

    it('ne départage pas sur une preuve SANS DATE CONNUE — jamais une date devinée', function (): void {
        $sansDate = preuveAdj('https://gouv.bj/sans-date', PositionPreuve::Confirme, 90, null);
        $contredit = preuveAdj('https://gouv.bj/autre', PositionPreuve::Contredit, 90, null);

        expect($this->adjudication->laPlusRecenteEntreContradictoires([$sansDate, $contredit]))->toBeNull();
    });
});

describe('preuve décisive — trancher une CONTRADICTION ENTRE MOTEURS, jamais par leur nombre (NEWINSTRUCTION28)', function (): void {
    beforeEach(function (): void {
        $this->adjudication = app(AdjudicationDesPreuves::class);
    });

    it('retient la preuve la plus récente comme DÉCISIVE quand elle est aussi FIABLE', function (): void {
        $ancienne = preuveAdj('https://gouv.bj/ancien', PositionPreuve::Confirme, 90, Carbon::parse('2020-01-01'));
        $recente = preuveAdj('https://gouv.bj/nouveau', PositionPreuve::Contredit, 95, Carbon::parse('2026-09-01'));

        $decisive = $this->adjudication->preuveDecisive([$ancienne, $recente]);

        expect($decisive?->url)->toBe('https://gouv.bj/nouveau')
            ->and($this->adjudication->verdictDeLaPreuveDecisive($decisive))->toBe(VerdictFactCheck::Faux);
    });

    it('ne tranche PAS si la preuve la plus récente est trop FAIBLE pour décider seule', function (): void {
        $ancienne = preuveAdj('https://gouv.bj/ancien', PositionPreuve::Confirme, 90, Carbon::parse('2020-01-01'));
        // Récente, mais un blog à 40 de fiabilité ne doit pas trancher à lui
        // seul — exactement le même seuil que unePreuveForteContredit().
        $recenteFaible = preuveAdj('https://blog-anonyme.info/rumeur', PositionPreuve::Contredit, 40, Carbon::parse('2026-09-01'));

        expect($this->adjudication->preuveDecisive([$ancienne, $recenteFaible]))->toBeNull();
    });

    it('ne tranche rien sans contradiction datée — le comportement d\'avant ce correctif', function (): void {
        $uneSeule = preuveAdj('https://gouv.bj/a', PositionPreuve::Confirme, 95, Carbon::now());

        expect($this->adjudication->preuveDecisive([$uneSeule]))->toBeNull();
    });

    it('une preuve NEUTRE ou CONTESTÉE ne porte aucun verdict décisif', function (): void {
        $neutre = preuveAdj('https://gouv.bj/a', PositionPreuve::Neutre, 95, Carbon::now());
        $contestee = preuveAdj('https://gouv.bj/b', PositionPreuve::Contestee, 95, Carbon::now());

        expect($this->adjudication->verdictDeLaPreuveDecisive($neutre))->toBeNull()
            ->and($this->adjudication->verdictDeLaPreuveDecisive($contestee))->toBeNull();
    });
});
