<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\LoginAttempt;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Support\Carbon;

/**
 * Verrouillage progressif des connexions (SECURITE_LOGIN).
 *
 *   5 échecs  → 1 minute
 *   10 échecs → 15 minutes
 *   20 échecs → notification administrateur, verrouillage prolongé
 *
 * La progressivité est délibérée : un blocage sec dès la 5ᵉ tentative
 * pénalise l'utilisateur qui se trompe de casse, alors qu'une escalade laisse
 * respirer l'erreur humaine tout en rendant le forçage automatisé inopérant.
 *
 * La clé est COMPOSITE (identité + IP) :
 *  - sur la seule identité, un tiers pourrait bloquer volontairement le compte
 *    d'un citoyen en saisissant dix mauvais mots de passe ;
 *  - sur la seule IP, un cybercafé ou un partage de connexion verrouillerait
 *    tout un quartier.
 */
final class LoginThrottleService
{
    /** Paliers : seuil d'échecs => durée de verrouillage en secondes. */
    private const PALIERS = [
        20 => 3600,
        10 => 900,
        5 => 60,
    ];

    /** Seuil au-delà duquel l'administrateur est alerté. */
    public const SEUIL_ALERTE = 20;

    /** Au bout de ce délai sans échec, le compteur repart de zéro. */
    private const FENETRE_HEURES = 24;

    public function __construct(private readonly IdentityHasher $hasher) {}

    /**
     * Le couple est-il actuellement verrouillé ?
     */
    public function estVerrouille(string $identity, string $ip): bool
    {
        return $this->secondesRestantes($identity, $ip) > 0;
    }

    /**
     * Secondes avant réouverture — 0 si libre.
     */
    public function secondesRestantes(string $identity, string $ip): int
    {
        $tentative = $this->trouver($identity, $ip);

        if ($tentative?->locked_until === null) {
            return 0;
        }

        $restant = (int) now()->diffInSeconds($tentative->locked_until, absolute: false);

        return max(0, $restant);
    }

    /**
     * Enregistre un échec et applique le palier atteint.
     *
     * Renvoie le nombre total d'échecs, pour que l'appelant décide d'alerter.
     */
    public function enregistrerEchec(string $identity, string $ip): int
    {
        $tentative = $this->trouver($identity, $ip);

        if ($tentative === null) {
            $tentative = new LoginAttempt([
                'identity_hash' => $this->hasher->hash($identity),
                'ip_hash' => $this->hasher->hash($ip),
            ]);
            $tentative->failures = 0;
        }

        // Les échecs anciens ne comptent plus : sans fenêtre glissante, un
        // compte finirait verrouillé par des erreurs vieilles de six mois.
        if ($tentative->last_failure_at?->lt(now()->subHours(self::FENETRE_HEURES)) === true) {
            $tentative->failures = 0;
        }

        $tentative->failures++;
        $tentative->last_failure_at = now();
        $tentative->locked_until = $this->calculerVerrou($tentative->failures);
        $tentative->save();

        return $tentative->failures;
    }

    /**
     * Connexion réussie : le compteur est effacé.
     */
    public function reinitialiser(string $identity, string $ip): void
    {
        $this->trouver($identity, $ip)?->delete();
    }

    /**
     * Échecs cumulés sur la fenêtre courante.
     */
    public function echecs(string $identity, string $ip): int
    {
        return $this->trouver($identity, $ip)?->failures ?? 0;
    }

    /**
     * Palier atteint : on prend le seuil le plus élevé franchi.
     */
    private function calculerVerrou(int $echecs): ?Carbon
    {
        foreach (self::PALIERS as $seuil => $secondes) {
            if ($echecs >= $seuil) {
                return now()->addSeconds($secondes);
            }
        }

        return null;
    }

    private function trouver(string $identity, string $ip): ?LoginAttempt
    {
        return LoginAttempt::query()
            ->where('identity_hash', $this->hasher->hash($identity))
            ->where('ip_hash', $this->hasher->hash($ip))
            ->first();
    }
}
