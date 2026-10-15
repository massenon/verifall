<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Application\Services\ModeMaintenance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Détourne le public vers la page de maintenance — et personne d'autre.
 *
 * ## Trois exemptions, et chacune évite de s'enfermer dehors
 *
 *   1. **le personnel connecté** — sans quoi on ne pourrait plus rouvrir depuis
 *      l'administration, et il faudrait un accès SSH que l'équipe éditoriale
 *      n'a pas ;
 *   2. **la connexion du personnel** — corollaire du premier : un membre non
 *      encore connecté doit pouvoir entrer ;
 *   3. **la page de maintenance elle-même et son formulaire**, sinon la
 *      redirection tournerait en boucle.
 *
 * C'est précisément ce que `php artisan down` ne sait pas faire.
 *
 * ## Code 503, et pas une simple page
 *
 * Un moteur d'indexation qui reçoit un 200 sur une page de maintenance conclut
 * que le site a été remplacé par ce texte, et il désindexe. Le 503 dit « revenez
 * plus tard » — et l'en-tête `Retry-After` le dit en secondes quand une fin est
 * annoncée.
 */
final class MaintenanceApplicative
{
    /**
     * Les routes qui répondent même en maintenance.
     *
     * Par NOM et non par chemin : un chemin se renomme, et l'exemption se
     * perdrait sans que rien ne le signale — on découvrirait l'enfermement le
     * jour où il faut rouvrir.
     *
     * @var list<string>
     */
    private const EXEMPTES = [
        'maintenance',
        'maintenance.watch',
        'staff.login',
        'staff.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = app(ModeMaintenance::class);

        if (! $maintenance->estActif()) {
            return $next($request);
        }

        if (Auth::guard('staff')->check() || $this->estExempte($request)) {
            return $next($request);
        }

        return response()
            ->view('maintenance', [
                'message' => $maintenance->message(),
                'finAnnoncee' => $maintenance->finAnnoncee(),
                'enRetard' => $maintenance->enRetard(),
            ], Response::HTTP_SERVICE_UNAVAILABLE)
            ->withHeaders($this->enTetes($maintenance));
    }

    private function estExempte(Request $request): bool
    {
        $nom = $request->route()?->getName();

        return $nom !== null && in_array($nom, self::EXEMPTES, true);
    }

    /**
     * @return array<string, string>
     */
    private function enTetes(ModeMaintenance $maintenance): array
    {
        $fin = $maintenance->finAnnoncee();

        if ($fin === null || $fin->isPast()) {
            return [];
        }

        // `Retry-After` en secondes : c'est ce que lisent les robots et les
        // sondes de disponibilité. Sans lui, une supervision alerte en boucle.
        return ['Retry-After' => (string) max(60, $fin->diffInSeconds(now()))];
    }
}
