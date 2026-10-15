<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fait du guard `staff` le guard par défaut, le temps du back-office.
 *
 * Sans cela, `$request->user()`, `Gate` et donc TOUTES les Policies
 * interrogent le guard `web` — vide dans le back-office, puisqu'on y est
 * connecté sur `staff`. Une Policy parfaitement écrite refuserait alors un
 * administrateur légitime, par un 403 que rien n'explique.
 *
 * C'est le pendant de `User::getDefaultGuardName()`, qui règle le problème
 * symétrique côté permissions : les rôles sont déclarés une fois, sur `web`,
 * et valent pour les deux espaces.
 *
 * Portée strictement locale : le guard par défaut est rétabli à la requête
 * suivante, l'application n'étant pas partagée entre requêtes.
 */
final class GuardStaffParDefaut
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('staff');

        return $next($request);
    }
}
