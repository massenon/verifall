<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use RuntimeException;

/**
 * On a voulu changer un verdict sans dire pourquoi.
 *
 * Ce n'est pas un incident technique, c'est un refus délibéré : le code de
 * conduite de l'IFCN demande que les corrections soient publiées AVEC leur
 * motif. Une correction muette dit qu'on a changé d'avis sans dire ce qu'on a
 * appris — exactement ce que la page `/corrections` existe pour empêcher.
 *
 * Levée par `JournalDesCorrections`, elle fait échouer la transaction : le
 * verdict n'est pas écrit non plus. C'est voulu — un verdict corrigé sans
 * entrée au journal serait pire que pas de correction du tout.
 */
final class RaisonDeCorrectionManquante extends RuntimeException
{
    public static function pourUneCorrection(): self
    {
        return new self(
            'Un changement de verdict exige une raison écrite : elle sera publiée '
            .'au journal des corrections, à côté de l\'ancien verdict.'
        );
    }
}
