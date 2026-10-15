<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\ValueObjects\Preuve;
use App\Enums\PositionPreuve;
use App\Enums\VerdictFactCheck;

/**
 * Les preuves VOTENT, séparément des modèles — phase 8 du plan en 13 phases.
 *
 * Lève D-05 et D-06 : « le nombre de modèles d'IA ne constitue pas le nombre
 * de sources indépendantes » (AC-006). Avant cette classe,
 * {@see FactCheckScoringService} comptait des VOIX DE MODÈLES — Gemini,
 * DeepSeek, OpenAI — pondérées par leur fiabilité déclarée ; les preuves
 * elles-mêmes n'intervenaient que dans {@see CoherenceRapport}, qui les
 * compte toutes à POIDS ÉGAL. Une preuve d'une rédaction accréditée et un
 * domaine jamais référencé pesaient donc pareil dans ce contrôle.
 *
 * ## Deux nombres, jamais un seul
 *
 * `consensus` dit CE QUE LES MODÈLES PENSENT. `corroboration` dit COMBIEN DE
 * VOIX INDÉPENDANTES l'attestent par une preuve. Un verdict peut avoir un
 * consensus de modèles élevé et une corroboration nulle — trois modèles qui
 * s'accordent sans qu'aucun n'ait trouvé la moindre preuve ouverte. Les
 * confondre laisserait croire à un recoupement qui n'a pas eu lieu.
 *
 * ## Ce qu'elle NE fait PAS
 *
 * Elle ne remplace ni ne modifie {@see FactCheckScoringService::decider()},
 * qui reste l'unique source du VERDICT retenu — « jeu de non-régression
 * obligatoire » sur ce point précis, selon le plan. Cette classe calcule des
 * métriques ADDITIVES, stockées à côté dans `analysis_reports.scores`
 * (aucune migration), et un signal d'ESCALADE que l'orchestrateur confronte
 * au même titre qu'une incohérence de {@see CoherenceRapport}.
 */
final class AdjudicationDesPreuves
{
    /**
     * En dessous, une preuve n'est pas assez fiable pour, À ELLE SEULE,
     * mettre en doute un consensus de modèles. Le seuil des rédactions
     * accréditées et des sources institutionnelles référencées.
     */
    private const FIABILITE_FORTE = 85;

    public function __construct(
        private readonly IndependanceDesSources $independance = new IndependanceDesSources,
    ) {}

    /**
     * Combien de VOIX INDÉPENDANTES soutiennent le verdict retenu — pas
     * combien de modèles. Une preuve `NonVerifiable`/`Trompeur` n'a pas de
     * camp : elle ne corrobore rien, elle ne contredit rien.
     *
     * @param  list<Preuve>  $preuves
     */
    public function corroboration(array $preuves, VerdictFactCheck $verdict): int
    {
        $enFaveur = array_values(array_filter(
            $preuves,
            fn (Preuve $p): bool => $this->positionSoutient($p->position, $verdict),
        ));

        return $this->independance->nombreDeVoixIndependantes($enFaveur);
    }

    /**
     * Une SEULE preuve forte contredit-elle le verdict, quel que soit le
     * nombre de preuves faibles qui vont dans l'autre sens ?
     *
     * C'est le cas que le comptage à poids égal de
     * {@see CoherenceRapport::preuvesSuiventLeVerdict()} ne peut PAS voir :
     * une dépêche officielle contre quatre blogs qui se recopient reste, à
     * poids égal, « quatre contre une ». À poids réel, c'est l'inverse.
     *
     * @param  list<Preuve>  $preuves
     */
    public function unePreuveForteContredit(array $preuves, VerdictFactCheck $verdict): bool
    {
        foreach ($preuves as $preuve) {
            if ($preuve->fiabilite < self::FIABILITE_FORTE) {
                continue;
            }

            if ($this->positionContredit($preuve->position, $verdict)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Entre deux preuves ouvertement contradictoires, laquelle doit
     * l'emporter — la plus RÉCENTE, jamais la plus nombreuse.
     *
     * Code le principe qu'`analyse.md` n'énonçait jusqu'ici qu'en prose :
     * « une preuve institutionnelle RÉCENTE prévaut toujours sur une preuve
     * institutionnelle ancienne ». Une preuve sans date connue ne peut pas
     * gagner ce départage — on ne sait pas si elle est la plus récente.
     *
     * @param  list<Preuve>  $preuves
     */
    public function laPlusRecenteEntreContradictoires(array $preuves): ?Preuve
    {
        $confirment = array_values(array_filter($preuves, fn (Preuve $p): bool => $p->position === PositionPreuve::Confirme));
        $contredisent = array_values(array_filter($preuves, fn (Preuve $p): bool => $p->position === PositionPreuve::Contredit));

        if ($confirment === [] || $contredisent === []) {
            return null;
        }

        $datees = array_values(array_filter($preuves, fn (Preuve $p): bool => $p->publieeLe !== null
            && in_array($p->position, [PositionPreuve::Confirme, PositionPreuve::Contredit], true)));

        if ($datees === []) {
            return null;
        }

        usort($datees, fn (Preuve $a, Preuve $b): int => $b->publieeLe <=> $a->publieeLe);

        return $datees[0];
    }

    /**
     * Tranche une CONTRADICTION ENTRE MOTEURS par la preuve — jamais par leur
     * nombre. NEWINSTRUCTION28 : un claim sensible au temps (« l'actuel
     * président ») peut diviser Gemini/OpenAI/DeepSeek alors qu'une preuve
     * institutionnelle, directe et plus récente que sa contradictoire
     * tranche déjà la question sans ambiguïté.
     *
     * Deux gardes, jamais une seule :
     *   1. {@see laPlusRecenteEntreContradictoires()} — il faut que DEUX
     *      camps existent ET que la plus récente soit DATÉE, jamais devinée ;
     *   2. le seuil de fiabilité forte ci-dessus — une preuve récente mais
     *      faible (un blog qui a mis à jour sa page hier) ne doit pas trancher
     *      à elle seule, pas plus qu'une preuve ancienne et fiable.
     *
     * Rend `null` dès que l'une des deux manque : l'appelant garde alors le
     * comportement de contradiction d'avant ce correctif, à l'identique.
     *
     * @param  list<Preuve>  $preuves
     */
    public function preuveDecisive(array $preuves): ?Preuve
    {
        $retenue = $this->laPlusRecenteEntreContradictoires($preuves);

        if ($retenue === null || $retenue->fiabilite < self::FIABILITE_FORTE) {
            return null;
        }

        return $retenue;
    }

    /**
     * Le verdict que porte une preuve DÉCISIVE — jamais pour une preuve
     * neutre ou contestée, qui ne prend parti pour aucun camp.
     */
    public function verdictDeLaPreuveDecisive(Preuve $preuve): ?VerdictFactCheck
    {
        return match ($preuve->position) {
            PositionPreuve::Confirme => VerdictFactCheck::Vrai,
            PositionPreuve::Contredit => VerdictFactCheck::Faux,
            default => null,
        };
    }

    private function positionSoutient(PositionPreuve $position, VerdictFactCheck $verdict): bool
    {
        return match ($verdict) {
            VerdictFactCheck::Vrai => $position === PositionPreuve::Confirme,
            VerdictFactCheck::Faux => $position === PositionPreuve::Contredit,
            default => false,
        };
    }

    private function positionContredit(PositionPreuve $position, VerdictFactCheck $verdict): bool
    {
        return match ($verdict) {
            VerdictFactCheck::Vrai => $position === PositionPreuve::Contredit,
            VerdictFactCheck::Faux => $position === PositionPreuve::Confirme,
            default => false,
        };
    }
}
