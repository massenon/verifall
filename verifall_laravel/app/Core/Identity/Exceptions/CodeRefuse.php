<?php

declare(strict_types=1);

namespace App\Core\Identity\Exceptions;

use App\Core\Identity\Services\OtpVerification;
use RuntimeException;

/**
 * Un code à usage unique a été refusé.
 *
 * Porte le RÉSULTAT de la vérification et non un simple message : l'écran doit
 * pouvoir dire pourquoi — expiré, déjà utilisé, faux — et le journal de
 * sécurité doit pouvoir classer l'incident par sévérité. Un booléen aurait
 * obligé chaque appelant à re-deviner l'un et l'autre.
 */
final class CodeRefuse extends RuntimeException
{
    public function __construct(public readonly OtpVerification $resultat)
    {
        parent::__construct($resultat->message());
    }
}
