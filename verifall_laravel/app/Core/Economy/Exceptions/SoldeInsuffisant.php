<?php

declare(strict_types=1);

namespace App\Core\Economy\Exceptions;

use RuntimeException;

/**
 * Le solde ne couvre pas la dépense.
 *
 * Une exception plutôt qu'un booléen de retour : un appelant peut ignorer un
 * `false`, il ne peut pas ignorer une exception. S'agissant d'argent, le
 * silence n'est pas une option.
 */
final class SoldeInsuffisant extends RuntimeException
{
    public function __construct(
        public readonly int $solde,
        public readonly int $requis,
    ) {
        parent::__construct(sprintf(
            'Solde insuffisant : %d unité(s) disponible(s), %d requise(s).',
            $solde,
            $requis,
        ));
    }

    /** Message destiné à la personne, pas au journal. */
    public function messageUtilisateur(): string
    {
        return sprintf(
            'Il vous manque %d unité%s. Rechargez votre compte pour continuer.',
            $this->requis - $this->solde,
            $this->requis - $this->solde > 1 ? 's' : '',
        );
    }
}
