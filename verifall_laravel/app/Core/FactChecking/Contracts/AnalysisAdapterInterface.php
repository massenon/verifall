<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Contracts;

use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\TypeContenu;

/**
 * Contrat commun des moteurs d'analyse.
 *
 * Trois exigences, qui tiennent lieu de discipline :
 *
 *   · `analyse()` ne LÈVE JAMAIS. Un service tiers en panne rend
 *     `VerdictMoteur::echec()` ; laisser remonter une exception ferait tomber
 *     toute la vérification parce qu'un fournisseur a hoqueté.
 *   · Le coût est déclaré, et débité par EconomyService — jamais par
 *     l'adaptateur lui-même, qui n'a pas à connaître l'économie.
 *   · Aucun secret ne sort dans un journal, une exception ou un rapport.
 */
interface AnalysisAdapterInterface
{
    public function nom(): MoteurAnalyse;

    public function supporte(TypeContenu $type): bool;

    public function analyse(SacDePreuves $entree): VerdictMoteur;

    /** Unités consommées par cet adaptateur, au-delà du coût de base. */
    public function coutUnites(): int;
}
