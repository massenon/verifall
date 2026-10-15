<?php

declare(strict_types=1);

namespace App\Core\Identity\Exceptions;

use RuntimeException;

/**
 * Trop de tentatives : la porte est fermée pour un temps.
 *
 * Porte le nombre de SECONDES restantes plutôt qu'un message tout fait : le
 * web le formule en minutes dans une bulle d'erreur, l'API le renvoie dans
 * `Retry-After`. Un message figé ici obligerait l'un des deux à le décomposer.
 */
final class CompteVerrouille extends RuntimeException
{
    public function __construct(public readonly int $secondes)
    {
        parent::__construct('Trop de tentatives.');
    }
}
