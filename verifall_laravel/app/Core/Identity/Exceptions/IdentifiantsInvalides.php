<?php

declare(strict_types=1);

namespace App\Core\Identity\Exceptions;

use RuntimeException;

/**
 * Identifiants refusés.
 *
 * Une SEULE exception pour trois causes distinctes — adresse inconnue, mot de
 * passe faux, compte jamais activé — et c'est délibéré : distinguer les cas
 * transformerait l'écran de connexion en oracle permettant d'énumérer les
 * inscrits sans jamais deviner un mot de passe. Les appelants ne peuvent donc
 * pas, même par inadvertance, afficher un message qui trahirait la différence.
 */
final class IdentifiantsInvalides extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Identifiants invalides.');
    }
}
