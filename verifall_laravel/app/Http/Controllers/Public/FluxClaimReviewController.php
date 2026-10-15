<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Marketing\Seo\FluxClaimReview;
use Illuminate\Http\JsonResponse;

/**
 * Sert le flux de vérifications, à une adresse stable.
 *
 * L'ADRESSE NE DOIT PLUS JAMAIS CHANGER une fois déclarée à Google : un flux
 * déplacé n'est pas suivi, il est simplement perdu, et le silence qui suit
 * ressemble à s'y méprendre à un flux vide.
 *
 * Une heure de cache côté serveur, une heure côté client. Les vérifications ne
 * paraissent pas au rythme des minutes, et un flux relu trop souvent coûte
 * pour rien.
 */
final class FluxClaimReviewController
{
    public function __invoke(FluxClaimReview $flux): JsonResponse
    {
        return response()
            ->json(
                $flux->construire(),
                200,
                [],
                /*
                 | `UNESCAPED_UNICODE` : sans lui, « Bénin » sort en
                 | « B\u00e9nin ». C'est valide, mais illisible pour qui vérifie
                 | le flux à l'œil — et c'est exactement ce qu'on fait le jour
                 | où l'on cherche pourquoi une vérification n'est pas reprise.
                 |
                 | `UNESCAPED_SLASHES` : les adresses restent lisibles.
                 */
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
