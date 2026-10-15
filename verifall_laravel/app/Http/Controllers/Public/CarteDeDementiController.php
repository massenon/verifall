<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Marketing\Cartes\CarteDeDementi;
use App\Models\HubArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ReponseHttp;

/**
 * Sert la carte de démenti d'une brève publiée.
 *
 * ## Pourquoi une route et non `storage/…`
 *
 * Parce que `public/storage` est un lien symbolique créé par
 * `php artisan storage:link`, et qu'il n'existe pas ici — sur beaucoup
 * d'hébergements mutualisés il ne peut même pas exister. Une `og:image` qui
 * renvoie 404 ne casse aucune page : elle fait simplement disparaître l'aperçu,
 * silencieusement, et c'est la panne la plus coûteuse du lot puisqu'elle
 * annule exactement ce que la carte devait apporter.
 *
 * ## Ce que fait le contrôleur, et rien de plus
 *
 * Il ne dessine pas — la fabrique dessine, dans la file. Il ne cherche pas
 * l'article — la liaison de route le fait, par `slug`, jamais par identifiant
 * auto-incrémenté. Il lit un fichier et pose trois en-têtes.
 */
final class CarteDeDementiController
{
    public function __invoke(Request $requete, HubArticle $article, CarteDeDementi $fabrique): ReponseHttp
    {
        $octets = $fabrique->octets($article);

        // Pas de carte : brouillon, verdict absent, ou file jamais passée.
        // 404 plutôt qu'une image de remplacement — une carte de démenti
        // générique ne dément rien, et prêterait notre marque à un verdict que
        // nous n'avons pas rendu.
        abort_if($octets === null, ReponseHttp::HTTP_NOT_FOUND);

        $reponse = new Response($octets, ReponseHttp::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($octets),
            // `inline` : l'image s'affiche dans l'onglet. Le nom sert quand
            // quelqu'un l'enregistre pour la renvoyer à la main.
            'Content-Disposition' => 'inline; filename="verifall-'.$article->slug.'.png"',
            /*
             | Une journée, pas un an. L'URL de la carte est STABLE — c'est
             | l'`og:image` qui porte l'empreinte en paramètre — donc un cache
             | perpétuel figerait un verdict corrigé chez tous les
             | intermédiaires, et personne n'aurait le moyen de le purger.
             */
            'Cache-Control' => 'public, max-age=86400',
        ]);

        // L'ETag, lui, est exact : il change à la seconde où le verdict change.
        // Un navigateur qui revient repart avec un 304 de quelques octets.
        $reponse->setEtag($fabrique->empreinte($article));
        $reponse->isNotModified($requete);

        return $reponse;
    }
}
