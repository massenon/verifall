<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expiration GLISSANTE du jeton personnel.
 *
 * LE PROBLÈME QUE ÇA RÉSOUT. Un jeton à durée fixe pose un dilemme sans bonne
 * réponse : court, il redemande le mot de passe à quelqu'un qui se sert de
 * l'application tous les jours — et sur un téléphone, saisir douze caractères
 * mêlant casse, chiffres et symboles est une punition ; long, il laisse un
 * téléphone perdu ouvert pendant des mois.
 *
 * L'expiration glissante tranche : tant que l'application sert, l'échéance
 * recule ; dès qu'elle cesse de servir, le compte à rebours court.
 *
 * POURQUOI PAS DE JETON DE RAFRAÎCHISSEMENT. Un couple accès/rafraîchissement
 * n'apporte ici rien de plus : il protège surtout les architectures où le
 * jeton d'accès traverse des services tiers, ce qui n'est pas le cas — il n'y
 * a qu'un serveur. Il ajouterait en revanche un second secret à stocker sur le
 * téléphone, une route de plus et un état de plus à tester.
 *
 * L'ÉCRITURE EST RARE, et c'est le point : repousser l'échéance à chaque appel
 * ferait un UPDATE par requête, y compris pour les écrans qui interrogent
 * l'avancement toutes les trois secondes. On n'écrit qu'au-delà de la moitié
 * de la durée consommée.
 */
final class RafraichirLeJeton
{
    public function handle(Request $request, Closure $next): Response
    {
        $jeton = $request->user()?->currentAccessToken();

        if ($jeton instanceof PersonalAccessToken && $jeton->expires_at !== null) {
            $duree = (int) config('api.token_ttl_minutes');

            // Il reste moins de la moitié de la durée devant nous : on repousse.
            if ($jeton->expires_at->lessThan(now()->addMinutes(intdiv($duree, 2)))) {
                $jeton->forceFill(['expires_at' => now()->addMinutes($duree)])->save();
            }
        }

        return $next($request);
    }
}
