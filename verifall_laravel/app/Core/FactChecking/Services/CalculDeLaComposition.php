<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Enums\CompositionDuRapport;
use App\Enums\VerdictFactCheck;

/**
 * La règle d'arbitrage entre plusieurs claims — contrat V-8, une fois pour toutes.
 *
 * Fonction PURE et TOTALE : mêmes verdicts en entrée, même composition en
 * sortie, sans accès base ni horloge, sans pondération d'aucun claim sur un
 * autre. Appelée UNE SEULE fois, à la consolidation — jamais sur un ensemble
 * de claims partiellement analysés.
 *
 * `NonVerifiable` ne participe pas à l'arbitrage entre verdicts tranchés : il
 * ne crée ni n'empêche une mixité. C'est le cas E du contrat, verrouillé par
 * NEWINSTRUCTION24 : `VRAI + NON_VERIFIABLE` reste `HOMOGENE`.
 */
final class CalculDeLaComposition
{
    /**
     * @param  list<VerdictFactCheck>  $verdicts  un verdict PAR CLAIM TRANCHABLE — les
     *                                            claims Question/Opinion/Prediction/NonFactuel
     *                                            n'en produisent pas et ne sont pas transmis ici.
     */
    public function pour(array $verdicts): CompositionDuRapport
    {
        $tranches = array_values(array_filter(
            $verdicts,
            static fn (VerdictFactCheck $v): bool => $v !== VerdictFactCheck::NonVerifiable,
        ));

        if ($tranches === []) {
            return CompositionDuRapport::Insuffisante;
        }

        $distincts = array_unique(array_map(
            static fn (VerdictFactCheck $v): string => $v->value,
            $tranches,
        ));

        return count($distincts) === 1 ? CompositionDuRapport::Homogene : CompositionDuRapport::Mixte;
    }

    /**
     * Le verdict SCALAIRE du rapport — `null` dès que la composition est `MIXTE`.
     *
     * `NULL` ne veut jamais dire « mixte » : il veut dire qu'aucun verdict
     * factuel unique ne s'applique. C'est `composition_status`, pas cette
     * colonne, qui porte l'information de mixité — décision verrouillée par
     * NEWINSTRUCTION24.
     *
     * @param  list<VerdictFactCheck>  $verdicts
     */
    public function verdictScalaire(array $verdicts): ?VerdictFactCheck
    {
        $tranches = array_values(array_filter(
            $verdicts,
            static fn (VerdictFactCheck $v): bool => $v !== VerdictFactCheck::NonVerifiable,
        ));

        if ($tranches === []) {
            // Soit zéro claim tranchable, soit tous non-vérifiables : le seul
            // verdict honnête est NON_VERIFIABLE lui-même, pas l'absence.
            return $verdicts === [] ? null : VerdictFactCheck::NonVerifiable;
        }

        $distincts = array_unique(array_map(
            static fn (VerdictFactCheck $v): string => $v->value,
            $tranches,
        ));

        return count($distincts) === 1 ? $tranches[0] : null;
    }
}
