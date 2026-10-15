<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Annonce la version servie, et referme la porte aux clients trop anciens.
 *
 * POURQUOI CE N'EST PAS DÉCORATIF. Une application installée sur un téléphone
 * ne se met pas à jour parce qu'on le souhaite. Le jour où une version
 * ancienne devient dangereuse — un protocole de paiement changé, une faille
 * corrigée côté client — il faut pouvoir l'arrêter sans déployer de code et
 * sans casser toutes les autres. C'est le rôle du plancher `minimum_client`,
 * réglé par variable d'environnement.
 *
 * Le refus est un 426 « Upgrade Required » et non un 400 : le client mobile
 * doit pouvoir afficher « mettez à jour l'application » plutôt que « erreur
 * réseau ». C'est la différence entre un utilisateur qui agit et un
 * utilisateur qui désinstalle.
 *
 * Un client qui n'annonce PAS sa version est servi. L'en-tête est facultatif
 * au contrat, et punir un client tiers pour l'avoir cru serait un piège.
 */
final class VersionApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $plancher = config('api.minimum_client');
        $annoncee = $request->header('X-Client-Version');

        if (is_string($plancher) && $plancher !== ''
            && is_string($annoncee) && $annoncee !== ''
            && version_compare($annoncee, $plancher, '<')) {
            return Enveloppe::erreur(CodeErreur::ClientObsolete)
                ->header('X-Api-Version', (string) config('api.version'))
                ->header('X-Minimum-Client-Version', $plancher);
        }

        $reponse = $next($request);

        return $reponse->header('X-Api-Version', (string) config('api.version'));
    }
}
