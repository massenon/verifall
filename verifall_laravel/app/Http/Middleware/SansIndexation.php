<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `X-Robots-Tag: noindex` sur une route publique mais non éditoriale.
 *
 * ## Pourquoi l'en-tête HTTP et pas seulement la balise `<meta>`
 *
 * `<x-seo>` pose déjà `<meta name="robots" content="noindex, follow">` quand
 * `DonneesSeo::$indexable` vaut faux. Cela suffit pour une page HTML lue par
 * un robot qui exécute le gabarit — mais pas pour tout le reste :
 *
 *  · une réponse non HTML (un PDF servi plus tard depuis la même adresse,
 *    une réponse Livewire) ne passe par aucune balise ;
 *  · certains agrégateurs ne lisent que les en-têtes ;
 *  · une erreur dans le gabarit — une variable `seo` oubliée — retirerait la
 *    balise sans que rien ne le signale, là où l'en-tête tient au niveau de la
 *    route.
 *
 * ## `follow`, et non `nofollow`
 *
 * Le rapport cite des sources : presse, portails d'État, communiqués. Laisser
 * les suivre sert ceux qui les publient et ne coûte rien — ce qu'on veut
 * éviter, c'est que la PAGE entre dans l'index, pas que les liens qu'elle
 * porte soient ignorés. C'est le choix déjà fait par `<x-seo>` ; poser
 * `nofollow` ici le contredirait à moitié, et deux réponses différentes à la
 * même question valent moins qu'une.
 */
final class SansIndexation
{
    public function handle(Request $requete, Closure $suite): Response
    {
        $reponse = $suite($requete);

        $reponse->headers->set('X-Robots-Tag', 'noindex, follow');

        return $reponse;
    }
}
