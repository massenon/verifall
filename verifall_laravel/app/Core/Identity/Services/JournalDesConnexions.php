<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\HistoriqueConnexion;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * L'historique des connexions d'un compte (arbitrage n° 178).
 *
 * Base légale : intérêt légitime de sécurité (RGPD art. 6.1.f et 32). On garde
 * de quoi reconnaître une prise de contrôle — date, résultat, appareil, pays,
 * réseau — et rien de plus :
 *
 *  - l'IP est TRONQUÉE pour l'affichage et HACHÉE pour comparer deux
 *    connexions ; elle n'est jamais conservée entière en clair ;
 *  - le navigateur et le système sont réduits à leur famille ; la chaîne
 *    User-Agent complète, qui identifie presque un appareil, ne l'est pas ;
 *  - un échec sur une adresse qui n'appartient à aucun compte n'est rattaché
 *    à personne : il reste dans le journal de sécurité, anonyme.
 *
 * Conservation : 12 mois, purgés par `verifall:purger-donnees-expirees`.
 */
final class JournalDesConnexions
{
    public function __construct(private readonly IdentityHasher $hasher) {}

    public function reussie(User $utilisateur, string $espace, string $methode = 'mot_de_passe'): HistoriqueConnexion
    {
        return $this->ecrire($utilisateur, $espace, 'reussie', $methode);
    }

    /**
     * Un échec ou un blocage, rattaché au compte que vise l'adresse — s'il existe.
     */
    public function echec(string $identite, string $resultat, string $espace): ?HistoriqueConnexion
    {
        $utilisateur = User::query()->where('email', mb_strtolower(trim($identite)))->first();

        return $utilisateur === null ? null : $this->ecrire($utilisateur, $espace, $resultat, 'mot_de_passe');
    }

    /**
     * @return Collection<int, HistoriqueConnexion>
     */
    public function pour(User $utilisateur, int $limite = 50): Collection
    {
        return HistoriqueConnexion::query()
            ->where('user_id', $utilisateur->id)
            ->latest('created_at')->latest('id')
            ->limit($limite)
            ->get();
    }

    public function purger(?Carbon $avant = null): int
    {
        return HistoriqueConnexion::query()
            ->where('created_at', '<', $avant ?? now()->subMonths(HistoriqueConnexion::MOIS_DE_CONSERVATION))
            ->delete();
    }

    /** L'espace d'où vient une requête : la console (`/admin`) ou le citoyen. */
    public static function espaceDe(Request $requete): string
    {
        return $requete->is('admin', 'admin/*') ? 'console' : 'citoyen';
    }

    /**
     * Tronque une IP : 41.85.12.204 → 41.85.12.0 ; en IPv6, les 48 premiers bits.
     */
    public static function tronquer(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $binaire = (string) inet_pton($ip);

            return (string) inet_ntop(substr($binaire, 0, 6).str_repeat("\0", 10));
        }

        return null;
    }

    /**
     * Familles de navigateur, de système et d'appareil, lues dans le User-Agent.
     *
     * @return array{navigateur: ?string, systeme: ?string, appareil: ?string}
     */
    public static function decrireAgent(?string $agent): array
    {
        $a = (string) $agent;

        $navigateur = match (true) {
            str_contains($a, 'VerifAll') => "Application Vérif'All",
            str_contains($a, 'Edg/') => 'Edge',
            str_contains($a, 'OPR/') || str_contains($a, 'Opera') => 'Opera',
            str_contains($a, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($a, 'Firefox/') => 'Firefox',
            str_contains($a, 'Chrome/') => 'Chrome',
            str_contains($a, 'Safari/') => 'Safari',
            default => null,
        };

        $systeme = match (true) {
            str_contains($a, 'Android') => 'Android',
            str_contains($a, 'iPhone') || str_contains($a, 'iPad') => 'iOS',
            str_contains($a, 'Windows') => 'Windows',
            str_contains($a, 'Mac OS X') || str_contains($a, 'Macintosh') => 'macOS',
            str_contains($a, 'Linux') => 'Linux',
            default => null,
        };

        $appareil = match (true) {
            $a === '' => null,
            str_contains($a, 'iPad') || str_contains($a, 'Tablet') => 'tablette',
            str_contains($a, 'Mobile') || str_contains($a, 'Android') || str_contains($a, 'iPhone') => 'mobile',
            default => 'ordinateur',
        };

        return ['navigateur' => $navigateur, 'systeme' => $systeme, 'appareil' => $appareil];
    }

    private function ecrire(User $utilisateur, string $espace, string $resultat, string $methode): HistoriqueConnexion
    {
        $requete = request();
        $ip = $requete->ip();

        // Le pays n'est lu que si un mandataire de confiance le fournit déjà
        // (Cloudflare) : aucune base de géolocalisation n'est interrogée.
        $pays = strtoupper(mb_substr((string) $requete->header('CF-IPCountry'), 0, 2));

        return HistoriqueConnexion::query()->create([
            'user_id' => $utilisateur->id,
            'espace' => $espace,
            'resultat' => $resultat,
            'methode' => $methode,
            ...self::decrireAgent($requete->userAgent()),
            'ip_tronquee' => self::tronquer($ip),
            'ip_hash' => $ip !== null ? $this->hasher->hash($ip) : null,
            'pays' => preg_match('/^[A-Z]{2}$/', $pays) === 1 && $pays !== 'XX' ? $pays : null,
        ]);
    }
}
