<?php

declare(strict_types=1);

namespace App\Core\Marketing\Seo;

use App\Core\Application\Reglages\Reglages;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lecture de Google Analytics 4 et de Search Console par un COMPTE DE SERVICE.
 *
 * ## Pourquoi un compte de service
 *
 * Aucune connexion Google à faire à chaque visite, aucun jeton personnel qui
 * expire : une clé JSON, déposée une fois dans Paramètres › SEO, que Google
 * accepte en échange d'un jeton de lecture de une heure. La clé est un secret
 * (chiffrée par `Reglages`) ; elle ne sort ni vers l'écran ni vers le journal.
 *
 * ## Ce que ce service ne fait pas
 *
 * - il ne lit QUE : portées `*.readonly` ;
 * - il n'invente rien : un service non configuré ou en panne lève
 *   {@see GoogleIndisponible}, et l'écran le dit ;
 * - il ne met pas de réponse en cache plus de trente minutes : un tableau de
 *   bord SEO n'a pas à être à la seconde, mais pas à la veille non plus.
 */
final class DonneesGoogle
{
    private const JETON = 'https://oauth2.googleapis.com/token';

    private const PORTEES = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly';

    private const TTL_MINUTES = 30;

    public function __construct(private readonly Reglages $reglages) {}

    /**
     * Ce qui est renseigné — sans rien appeler.
     *
     * @return array{compte: ?string, ga4: bool, gsc: bool}
     */
    public function statut(): array
    {
        $cle = $this->cle();

        return [
            'compte' => $cle['client_email'] ?? null,
            'ga4' => $cle !== null && $this->propriete() !== '',
            'gsc' => $cle !== null && $this->site() !== '',
        ];
    }

    /**
     * Essaie chaque service avec la plus petite requête possible.
     *
     * @return array{ga4: array{ok: bool, message: string}, gsc: array{ok: bool, message: string}}
     */
    public function tester(): array
    {
        $essai = function (callable $appel, bool $configure, string $manque): array {
            if (! $configure) {
                return ['ok' => false, 'message' => $manque];
            }

            try {
                $appel();

                return ['ok' => true, 'message' => 'Connexion établie.'];
            } catch (GoogleIndisponible $e) {
                return ['ok' => false, 'message' => $e->getMessage()];
            }
        };

        $statut = $this->statut();

        return [
            'ga4' => $essai(fn () => $this->ga4Rapport(['dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']], 'metrics' => [['name' => 'sessions']]]), $statut['ga4'], 'Clé JSON ou identifiant de propriété manquant.'),
            'gsc' => $essai(fn () => $this->gscRequete(['startDate' => now()->subDays(7)->toDateString(), 'endDate' => now()->toDateString(), 'rowLimit' => 1]), $statut['gsc'], 'Clé JSON ou site manquant.'),
        ];
    }

    /**
     * @return array{sessions: int, utilisateurs: int, vues: int, engagement: int, sources: list<array{source: string, sessions: int}>, pages: list<array{page: string, vues: int}>}
     *
     * @throws GoogleIndisponible
     */
    public function analytics(int $jours = 28): array
    {
        return Cache::remember("google.ga4.{$jours}", now()->addMinutes(self::TTL_MINUTES), function () use ($jours): array {
            $periode = [['startDate' => $jours.'daysAgo', 'endDate' => 'today']];

            $totaux = $this->ga4Rapport(['dateRanges' => $periode, 'metrics' => [
                ['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'screenPageViews'], ['name' => 'engagementRate'],
            ]]);
            $sources = $this->ga4Rapport(['dateRanges' => $periode, 'dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'metrics' => [['name' => 'sessions']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 6]);
            $pages = $this->ga4Rapport(['dateRanges' => $periode, 'dimensions' => [['name' => 'pagePath']], 'metrics' => [['name' => 'screenPageViews']], 'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 8]);

            $ligne = $totaux['rows'][0]['metricValues'] ?? [];

            return [
                'sessions' => (int) ($ligne[0]['value'] ?? 0),
                'utilisateurs' => (int) ($ligne[1]['value'] ?? 0),
                'vues' => (int) ($ligne[2]['value'] ?? 0),
                'engagement' => (int) round(((float) ($ligne[3]['value'] ?? 0)) * 100),
                'sources' => array_map(fn (array $r): array => ['source' => (string) ($r['dimensionValues'][0]['value'] ?? '—'), 'sessions' => (int) ($r['metricValues'][0]['value'] ?? 0)], $sources['rows'] ?? []),
                'pages' => array_map(fn (array $r): array => ['page' => (string) ($r['dimensionValues'][0]['value'] ?? '—'), 'vues' => (int) ($r['metricValues'][0]['value'] ?? 0)], $pages['rows'] ?? []),
            ];
        });
    }

    /**
     * @return array{clics: int, impressions: int, ctr: float, position: float, requetes: list<array{libelle: string, clics: int, impressions: int, position: float}>, pages: list<array{libelle: string, clics: int, impressions: int, position: float}>}
     *
     * @throws GoogleIndisponible
     */
    public function searchConsole(int $jours = 28): array
    {
        return Cache::remember("google.gsc.{$jours}", now()->addMinutes(self::TTL_MINUTES), function () use ($jours): array {
            // Search Console a deux jours de retard : finir à J-2 évite une dernière colonne vide.
            $fin = Carbon::now()->subDays(2)->toDateString();
            $debut = Carbon::now()->subDays($jours + 2)->toDateString();

            $total = $this->gscRequete(['startDate' => $debut, 'endDate' => $fin]);
            $requetes = $this->gscRequete(['startDate' => $debut, 'endDate' => $fin, 'dimensions' => ['query'], 'rowLimit' => 10]);
            $pages = $this->gscRequete(['startDate' => $debut, 'endDate' => $fin, 'dimensions' => ['page'], 'rowLimit' => 10]);

            $lignes = static fn (array $r): array => array_map(static fn (array $l): array => [
                'libelle' => (string) ($l['keys'][0] ?? '—'),
                'clics' => (int) ($l['clicks'] ?? 0),
                'impressions' => (int) ($l['impressions'] ?? 0),
                'position' => round((float) ($l['position'] ?? 0), 1),
            ], $r['rows'] ?? []);

            $t = $total['rows'][0] ?? [];

            return [
                'clics' => (int) ($t['clicks'] ?? 0),
                'impressions' => (int) ($t['impressions'] ?? 0),
                'ctr' => round(((float) ($t['ctr'] ?? 0)) * 100, 1),
                'position' => round((float) ($t['position'] ?? 0), 1),
                'requetes' => $lignes($requetes),
                'pages' => $lignes($pages),
            ];
        });
    }

    // ————————————————————————————————————————————————————————— appels

    /**
     * @param  array<string, mixed>  $corps
     * @return array<string, mixed>
     */
    private function ga4Rapport(array $corps): array
    {
        return $this->appeler("https://analyticsdata.googleapis.com/v1beta/properties/{$this->propriete()}:runReport", $corps);
    }

    /**
     * @param  array<string, mixed>  $corps
     * @return array<string, mixed>
     */
    private function gscRequete(array $corps): array
    {
        return $this->appeler('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($this->site()).'/searchAnalytics/query', $corps);
    }

    /**
     * @param  array<string, mixed>  $corps
     * @return array<string, mixed>
     */
    private function appeler(string $url, array $corps): array
    {
        try {
            $reponse = Http::withToken($this->jeton())->timeout(15)->acceptJson()->asJson()->post($url, $corps);
        } catch (Throwable $e) {
            throw new GoogleIndisponible('Google ne répond pas : '.class_basename($e).'.', previous: $e);
        }

        if ($reponse->status() === 403) {
            throw new GoogleIndisponible('Accès refusé : ajoutez l’adresse du compte de service en lecteur dans Google Analytics et dans Search Console.');
        }

        if ($reponse->status() === 404) {
            throw new GoogleIndisponible('Propriété ou site introuvable : vérifiez l’identifiant saisi.');
        }

        if (! $reponse->successful()) {
            throw new GoogleIndisponible('Google a répondu '.$reponse->status().'.');
        }

        return (array) $reponse->json();
    }

    /** Le jeton de lecture, échangé contre la signature du compte de service (JWT RS256). */
    private function jeton(): string
    {
        $cle = $this->cle() ?? throw new GoogleIndisponible('Clé JSON du compte de service absente ou illisible.');

        return Cache::remember('google.jeton', now()->addMinutes(50), function () use ($cle): string {
            $maintenant = time();
            $entete = $this->base64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $charge = $this->base64(json_encode([
                'iss' => $cle['client_email'], 'scope' => self::PORTEES, 'aud' => self::JETON,
                'iat' => $maintenant, 'exp' => $maintenant + 3600,
            ], JSON_THROW_ON_ERROR));

            $signature = '';
            $privee = @openssl_pkey_get_private((string) $cle['private_key']);

            if ($privee === false || ! openssl_sign("{$entete}.{$charge}", $signature, $privee, OPENSSL_ALGO_SHA256)) {
                throw new GoogleIndisponible('La clé privée du compte de service est illisible.');
            }

            try {
                $reponse = Http::asForm()->timeout(15)->post(self::JETON, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => "{$entete}.{$charge}.".$this->base64($signature),
                ]);
            } catch (Throwable $e) {
                throw new GoogleIndisponible('Google ne répond pas : '.class_basename($e).'.', previous: $e);
            }

            $jeton = $reponse->json('access_token');

            if (! $reponse->successful() || ! is_string($jeton)) {
                throw new GoogleIndisponible('Google a refusé la clé du compte de service ('.($reponse->json('error') ?? $reponse->status()).').');
            }

            return $jeton;
        });
    }

    private function base64(string $donnees): string
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }

    /** @return array{client_email: string, private_key: string}|null */
    private function cle(): ?array
    {
        $brut = $this->reglages->get('google_service_account_json');

        if ($brut === null || trim($brut) === '') {
            return null;
        }

        $cle = json_decode($brut, true);

        return is_array($cle) && is_string($cle['client_email'] ?? null) && is_string($cle['private_key'] ?? null)
            ? ['client_email' => $cle['client_email'], 'private_key' => $cle['private_key']]
            : null;
    }

    private function propriete(): string
    {
        return trim((string) $this->reglages->get('ga4_property_id'));
    }

    private function site(): string
    {
        return trim((string) $this->reglages->get('gsc_site_url'));
    }
}
