<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * Pagination PAR CURSEUR — jamais par décalage.
 *
 * POURQUOI. Sur un fil qui bouge — le forum, les vérifications, le hub — le
 * décalage ment. Trois sujets publiés entre la page 1 et la page 2 décalent
 * tout : le client revoit trois éléments déjà affichés, ou en saute trois
 * qu'il ne verra jamais. Le curseur désigne une POSITION dans l'ordre, pas un
 * rang : ce qui s'insère au-dessus ne le déplace pas.
 *
 * La contrepartie est assumée : on ne peut pas sauter à la page 7, et il n'y a
 * pas de total. Aucun écran mobile n'en a besoin — ils défilent tous.
 */
final class PageCurseur
{
    /** Par défaut, et plafond. Le client demande, le serveur borne. */
    public const DEFAUT = 20;

    public const MAXIMUM = 50;

    /**
     * Taille de page demandée, ramenée dans les bornes.
     *
     * Bornée côté serveur et non côté client : `?limit=100000` est une requête
     * parfaitement légitime en apparence et une dénégation de service en
     * pratique.
     */
    public static function taille(Request $requete, int $defaut = self::DEFAUT): int
    {
        $demande = $requete->integer('limit', $defaut);

        return max(1, min($demande, self::MAXIMUM));
    }

    /**
     * Le bloc `meta.page` renvoyé avec chaque liste.
     *
     * `next_cursor` est OPAQUE : le client le renvoie tel quel dans `?cursor=`
     * et ne cherche jamais à le lire. En faire un identifiant lisible
     * inviterait à le fabriquer, et à découvrir le jour où l'ordre de tri
     * change que le client s'était mis à en dépendre.
     *
     * @param  CursorPaginator<int, Model>  $page
     * @return array<string, mixed>
     */
    public static function meta(CursorPaginator $page): array
    {
        return [
            'per_page' => $page->perPage(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'prev_cursor' => $page->previousCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ];
    }
}
