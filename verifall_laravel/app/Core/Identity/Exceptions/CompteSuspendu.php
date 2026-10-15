<?php

declare(strict_types=1);

namespace App\Core\Identity\Exceptions;

use RuntimeException;

/**
 * Le mot de passe était bon ; le compte est suspendu.
 *
 * ## Pourquoi une exception distincte d'`IdentifiantsInvalides`
 *
 * Répondre « identifiants invalides » à quelqu'un dont le mot de passe est
 * correct l'envoie réinitialiser un mot de passe qui n'est pas en cause,
 * échouer encore, puis écrire au support en colère. La sanction devient une
 * panne, du point de vue de la personne.
 *
 * ## Et pourquoi cela ne crée PAS d'énumération de comptes
 *
 * Elle n'est levée qu'**après** la vérification du mot de passe. Le message
 * n'apparaît donc qu'à quelqu'un qui connaît déjà le mot de passe du compte —
 * il n'apprend rien qu'il ne sache. Un compte inconnu, un mot de passe faux ou
 * un compte jamais activé continuent de rendre la même réponse indistincte.
 */
final class CompteSuspendu extends RuntimeException
{
    public function __construct(public readonly ?string $motif = null)
    {
        parent::__construct('Ce compte est suspendu.');
    }
}
