<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use RuntimeException;

/**
 * Levée quand un message touche un mot surveillé en mode « Bloquer ».
 *
 * ELLE NE DIT PAS QUEL MOT A DÉCLENCHÉ. C'est délibéré : révéler la liste
 * transformerait chaque refus en indice, et il suffirait de quelques essais
 * pour la reconstituer puis la contourner mot à mot.
 *
 * Le message rendu à l'auteur est donc générique, et l'information précise est
 * portée par `$termes`, qui sert au journal côté serveur.
 */
final class MessageBloque extends RuntimeException
{
    /** @param list<string> $termes */
    public function __construct(public readonly array $termes)
    {
        parent::__construct(
            "Ce message ne peut pas être publié tel quel. Reformulez-le, ou écrivez à l'équipe si vous pensez qu'il s'agit d'une erreur."
        );
    }
}
