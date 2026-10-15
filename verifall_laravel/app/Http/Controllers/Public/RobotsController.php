<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Http\Response;

/**
 * `robots.txt`, engendré plutôt que déposé.
 *
 * ## Pourquoi pas un fichier statique dans `public/`
 *
 * Parce qu'il doit dire l'inverse selon l'environnement. Sur la préproduction,
 * un `robots.txt` permissif fait indexer un site de test : on se retrouve avec
 * deux versions du même contenu dans Google, qui les traite comme du contenu
 * dupliqué et choisit lui-même laquelle garder. Ce n'est pas une hypothèse
 * d'école — c'est le mode d'échec le plus courant d'une mise en ligne.
 *
 * Un fichier statique ne peut pas connaître son environnement. Cette route, si.
 */
final class RobotsController
{
    public function __invoke(): Response
    {
        $texte = app()->environment('production')
            ? $this->production()
            : $this->horsProduction();

        return response($texte, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function production(): string
    {
        /*
         | On n'interdit QUE ce qui n'a rien à faire dans un index : les espaces
         | connectés, l'administration, les routes signées. Interdire davantage
         | « par prudence » prive le site de pages qu'il a intérêt à voir
         | référencées.
         */
        return <<<TXT
        User-agent: *
        Disallow: /admin/
        Disallow: /mon-espace/
        Disallow: /connexion
        Disallow: /inscription
        Disallow: /mot-de-passe-oublie
        Disallow: /auth/
        Allow: /

        Sitemap: {$this->plan()}
        TXT;
    }

    private function horsProduction(): string
    {
        return <<<'TXT'
        # Environnement hors production : rien ne doit être indexé.
        # Deux copies du même site dans un index se font concurrence, et c'est
        # le moteur qui choisit laquelle survit.
        User-agent: *
        Disallow: /
        TXT;
    }

    private function plan(): string
    {
        return route('public.sitemap');
    }
}
