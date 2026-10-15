<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;

/**
 * Le manifeste d'application web.
 *
 * ## Pourquoi une PWA ici, et pas ailleurs
 *
 * Le produit s'adresse à des gens qui consultent sur téléphone, souvent en 3G,
 * parfois avec un forfait compté. Une PWA leur donne trois choses concrètes :
 * une icône sur l'écran d'accueil sans passer par un magasin d'applications,
 * un démarrage sans barre d'adresse, et surtout des pages déjà lues qui
 * restent lisibles quand le réseau tombe.
 *
 * ## Pourquoi une route et non un fichier dans `public/`
 *
 * Parce que `start_url`, `scope` et les chemins d'icônes doivent être des URL
 * réelles. Un manifeste statique fige un domaine ; celui-ci suit `APP_URL`,
 * et fonctionne donc à l'identique en local, en recette et en production.
 */
final class ManifesteController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => "Vérif'All — la boussole contre la désinformation numérique",
            'short_name' => "Vérif'All",
            'description' => 'Vérifiez textes, images, vidéos et audios avant de les partager.',
            'lang' => 'fr',
            'dir' => 'ltr',

            'start_url' => route('public.home'),
            'scope' => url('/').'/',

            /*
             | `standalone` et non `fullscreen` : on retire la barre d'adresse,
             | pas la barre d'état. Masquer l'heure et la batterie d'un
             | téléphone pour afficher un site est une prise d'espace que rien
             | ne justifie ici.
             */
            'display' => 'standalone',
            'orientation' => 'portrait-primary',

            'background_color' => '#FFFFFF',
            'theme_color' => '#09B850',

            'icons' => [
                ['src' => asset('assets/images/icon-192x192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('assets/images/icon-512x512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                /*
                 | L'icône « maskable » est celle qu'Android recadre selon la
                 | forme du lanceur — cercle, losange, carré arrondi. Elle porte
                 | une marge de sécurité de 20 % : sans elle, le logo se fait
                 | rogner par le masque.
                 */
                ['src' => asset('assets/images/maskable-icon-512x512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],

            /*
             | Les raccourcis du menu long-appui. On n'en met que deux, et ce
             | sont les deux gestes réels du produit : vérifier quelque chose,
             | ou lire ce qui a été vérifié.
             */
            'shortcuts' => [
                [
                    'name' => 'Vérifier une information',
                    'short_name' => 'Vérifier',
                    'url' => route('public.verify'),
                    'icons' => [['src' => asset('assets/images/icon-192x192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name' => 'Dernières analyses',
                    'short_name' => 'Actualités',
                    'url' => route('public.facts'),
                    'icons' => [['src' => asset('assets/images/icon-192x192.png'), 'sizes' => '192x192']],
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
