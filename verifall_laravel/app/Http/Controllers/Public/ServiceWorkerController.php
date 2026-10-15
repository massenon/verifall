<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Http\Response;

/**
 * Le service worker, servi par une route.
 *
 * ## Pourquoi pas un fichier dans `public/`
 *
 * Deux raisons, et la seconde est la vraie.
 *
 * 1. Il a besoin d'URL absolues correctes — celles du domaine courant.
 * 2. Il a besoin d'une **version**. Un service worker ne se met à jour que si
 *    son octet change ; un fichier statique édité à la main garde donc son
 *    ancien cache tant que personne n'y touche. Ici la version se calcule sur
 *    les fichiers eux-mêmes : une feuille de style modifiée périme le cache
 *    toute seule, sans qu'on ait à y penser.
 *
 * ## La portée
 *
 * Servi depuis `/sw.js`, donc portée `/` : il voit tout le site. C'est
 * nécessaire, et c'est aussi ce qui rend la règle de vie privée ci-dessous
 * indispensable.
 */
final class ServiceWorkerController
{
    /**
     * Les ressources pré-chargées à l'installation.
     *
     * On reste volontairement court. Un pré-chargement généreux consomme le
     * forfait de quelqu'un avant même qu'il ait demandé quoi que ce soit — et
     * sur le public visé, cet arbitrage n'est pas neutre. On ne prend que ce
     * sans quoi une page ne s'affiche pas du tout.
     *
     * @var list<string>
     */
    private const SOCLE = [
        'assets/css/fonts/fonts.css',
        'assets/css/verifall.css',
        'assets/css/tailwind.css',
        'assets/css/verifall-app.css',
        'assets/images/logo.png',
        'assets/images/favicon.png',
    ];

    public function __invoke(): Response
    {
        $ressources = array_map(static fn (string $c): string => asset($c), self::SOCLE);

        $js = view('public.service-worker', [
            'version' => $this->version(),
            'socle' => $ressources,
            'pageHorsLigne' => route('public.offline'),
        ])->render();

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            /*
             | Un service worker ne se met JAMAIS en cache HTTP. S'il l'était,
             | un correctif urgent resterait invisible aussi longtemps que le
             | cache — et un service worker fautif est précisément ce qu'on ne
             | peut pas corriger depuis le serveur.
             */
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * Une version qui change quand le socle change.
     *
     * Sur la date de modification des fichiers, pas sur un numéro écrit à la
     * main : un numéro à incrémenter s'oublie, et l'oubli ne se voit qu'à
     * travers un bogue que personne ne reproduit.
     */
    private function version(): string
    {
        $empreinte = '';
        foreach (self::SOCLE as $chemin) {
            $fichier = public_path($chemin);
            $empreinte .= is_file($fichier) ? (string) filemtime($fichier) : '0';
        }

        return substr(md5($empreinte), 0, 12);
    }
}
