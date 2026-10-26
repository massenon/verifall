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
 * ## Les exemptions, et chacune évite de s'enfermer dehors
 *
 *   1. **le personnel connecté** — sans quoi on ne pourrait plus rouvrir depuis
 *      l'administration, et il faudrait un accès SSH que l'équipe éditoriale
 *      n'a pas ;
 *   2. **la connexion et l'écran de verrouillage du personnel** — corollaire du
 *      premier : un membre non encore connecté, ou verrouillé (donc déconnecté
 *      de la garde), doit pouvoir entrer ;
 *   3. **les appels Livewire de CES DEUX écrans-là seulement** — le formulaire
 *      de connexion poste sur `/livewire/update`, pas sur sa propre route ;
 *   4. **la page de maintenance, son formulaire et son état** — sinon la
 *      redirection tournerait en boucle ;
 *   5. **les rappels de paiement** — un opérateur qui reçoit 503 ne rejoue pas
 *      toujours, et l'unité payée ne serait jamais créditée. La route vérifie sa
 *      signature elle-même (arbitrage 181).
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
        'maintenance.etat',
        'staff.login',
        'staff.password.request',
        'staff.logout',
        'staff.verrou',
        'webhooks.momo',
    ];

    /**
     * Les seuls composants Livewire joignables en maintenance : ceux qui font
     * entrer le personnel. Tout autre composant — public ou citoyen — reste
     * derrière la page de maintenance.
     *
     * Le nom lu ici vient de l'instantané envoyé par le navigateur, donc
     * falsifiable ; ce n'est pas un problème. Livewire vérifie la somme de
     * contrôle de chaque instantané AVANT d'exécuter quoi que ce soit : un nom
     * maquillé est rejeté plus loin, et un nom honnête n'ouvre que l'écran de
     * connexion, qui est déjà public.
     *
     * @var list<string>
     */
    private const COMPOSANTS_EXEMPTES = [
        'auth.staff-login',
        'auth.staff-forgot-password',
        'auth.verrou-de-session',
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

        if ($request->expectsJson()) {
            // Un appel d'API ou un fetch n'a que faire d'une page HTML : il lit
            // un statut et un délai.
            return response()
                ->json([
                    'maintenance' => true,
                    'message' => $maintenance->message(),
                    'fin_annoncee' => $maintenance->finAnnoncee()?->toIso8601String(),
                ], Response::HTTP_SERVICE_UNAVAILABLE)
                ->withHeaders($this->enTetes($maintenance));
        }

        return response()
            ->view('maintenance', [
                'message' => $maintenance->message(),
                'finAnnoncee' => $maintenance->finAnnoncee(),
                'enRetard' => $maintenance->enRetard(),
                'debut' => $maintenance->debut(),
            ], Response::HTTP_SERVICE_UNAVAILABLE)
            ->withHeaders($this->enTetes($maintenance));
    }

    private function estExempte(Request $request): bool
    {
        $nom = $request->route()?->getName();

        if ($nom === null) {
            return false;
        }

        if (str_ends_with($nom, 'livewire.update')) {
            return $this->neVisQueLaConnexion($request);
        }

        return in_array($nom, self::EXEMPTES, true);
    }

    /**
     * Vrai seulement si TOUS les composants de l'appel sont exemptés.
     *
     * « Tous » et non « un » : Livewire regroupe plusieurs composants dans un
     * même appel, et un composant public glissé à côté de la connexion
     * passerait sinon avec elle.
     */
    private function neVisQueLaConnexion(Request $request): bool
    {
        $composants = $request->input('components');

        if (! is_array($composants) || $composants === []) {
            return false;
        }

        foreach ($composants as $composant) {
            $instantane = is_array($composant) && is_string($composant['snapshot'] ?? null)
                ? json_decode($composant['snapshot'], true)
                : null;

            $nom = is_array($instantane) ? ($instantane['memo']['name'] ?? null) : null;

            if (! in_array($nom, self::COMPOSANTS_EXEMPTES, true)) {
                return false;
            }
        }

        return true;
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
