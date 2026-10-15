<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expiration d'inactivité du back-office — 30 minutes (LOGIN_DECONNEXION).
 *
 * Distincte de la durée de vie de session : celle-ci court depuis la
 * connexion, celle-là depuis la DERNIÈRE ACTION. Un poste d'administration
 * laissé ouvert dans un bureau partagé se referme donc de lui-même, ce qu'une
 * simple durée de session ne garantit pas.
 *
 * Le message « déconnecté pour inactivité » est explicitement demandé : sans
 * lui, l'administrateur croit à une panne et perd du temps à chercher.
 */
final class StaffIdleTimeout
{
    /** Minutes d'inactivité tolérées. */
    public const MINUTES = 30;

    private const CLE = 'staff.last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('staff')->check()) {
            return $next($request);
        }

        $derniere = $request->session()->get(self::CLE);
        $limite = self::MINUTES * 60;

        if (is_int($derniere) && (now()->timestamp - $derniere) > $limite) {
            Auth::guard('staff')->logout();

            // La session entière est invalidée : conserver l'espace citoyen
            // ouvert alors que le back-office vient d'expirer laisserait une
            // porte entrebâillée sur le même poste.
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('staff.login')
                ->with('inactivite', true);
        }

        // Chaque requête authentifiée repousse l'échéance.
        $request->session()->put(self::CLE, now()->timestamp);

        return $next($request);
    }
}
