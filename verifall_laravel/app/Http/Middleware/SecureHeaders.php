<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité (SECURITE_RGPD §3).
 *
 * Appliqués à TOUTES les réponses, erreurs comprises : une page 500 doit être
 * aussi protégée qu'un écran de connexion.
 *
 * Chaque en-tête neutralise une attaque précise :
 *  - X-Frame-Options ......... clickjacking (mise en iframe du site)
 *  - X-Content-Type-Options .. MIME sniffing (une image servie comme script)
 *  - Referrer-Policy ......... fuite d'URL — un rapport d'analyse porte un
 *                              UUID qui ne doit pas partir chez un tiers
 *  - Permissions-Policy ...... accès matériel non sollicité
 *  - HSTS .................... rétrogradation HTTPS → HTTP
 */
final class SecureHeaders
{
    /** @var array<string, string> */
    private const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Permissions-Policy' => 'accelerometer=(), autoplay=(), camera=(self), '
            .'display-capture=(), encrypted-media=(), geolocation=(), gyroscope=(), '
            .'magnetometer=(), microphone=(self), midi=(), payment=(), usb=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        foreach (self::HEADERS as $header => $valeur) {
            $response->headers->set($header, $valeur);
        }

        // HSTS uniquement sur une connexion réellement chiffrée : l'émettre en
        // HTTP local rendrait le domaine de développement inaccessible en clair
        // pour toute la durée du max-age, sans moyen simple de revenir en arrière.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
        }

        // Ne rien révéler de la pile technique.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Les réponses authentifiées ne doivent jamais être mises en cache par
        // un proxy partagé : un rapport de vérification est une donnée
        // personnelle au sens du RGPD.
        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
