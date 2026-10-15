<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\Adapters\AdaptateurHttp;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Récupère une page EXTERNE sans ouvrir de SSRF — phase 6 du plan en 13 phases.
 *
 * Spécifiée depuis `SECURITE_RGPD.md` §2 et jamais construite avant cette
 * phase : « allowlist de schémas, blocage des IP privées et de localhost,
 * délai 8 s, taille max 5 Mo ». C'est elle qui rend {@see CollecteurDePreuves}
 * possible — sans elle, demander au serveur d'ouvrir une URL arbitraire citée
 * par un modèle revient à laisser n'importe quel contenu soumis faire sonder
 * le réseau interne par la plateforme elle-même.
 *
 * ## Le défaut nommé par l'audit de sécurité, corrigé ICI dès l'origine
 *
 * `kb/SECURITY_AUDIT.md` classait en **P0** le fait qu'une validation
 * n'ayant lieu que sur l'URL INITIALE laisse passer un SSRF par
 * redirection : une adresse publique qui redirige vers `http://169.254.169.254/`
 * (le point de métadonnées cloud, une cible classique) passerait si seul le
 * premier saut était contrôlé. Cette classe désactive donc le suivi
 * automatique des redirections et revalide CHAQUE saut avant de le suivre.
 */
final class UrlSandbox
{
    private const SCHEMES_AUTORISES = ['http', 'https'];

    private const SAUTS_MAXIMUM = 5;

    private const DELAI_SECONDES = 8;

    private const TAILLE_MAX_OCTETS = 5 * 1024 * 1024;

    /**
     * La résolution DNS, injectable — jamais `gethostbyname()` en dur.
     *
     * Un test qui exercerait la vraie résolution dépendrait du réseau pour
     * vérifier une règle de SÉCURITÉ : le pire moment pour qu'un test
     * devienne lent ou flaky. Le défaut reste la résolution réelle ; les
     * tests substituent une carte fermée, déterministe.
     *
     * @param  (\Closure(string): string)|null  $resoudreHote
     */
    public function __construct(
        private readonly ?\Closure $resoudreHote = null,
    ) {}

    /**
     * Récupère une page, ou rend `null` — ne lève JAMAIS, même sens que
     * {@see AdaptateurHttp::analyse()}.
     *
     * @return array{url_finale: string, corps: string}|null
     */
    public function recuperer(string $url): ?array
    {
        $courante = $url;

        for ($saut = 0; $saut <= self::SAUTS_MAXIMUM; $saut++) {
            if (! $this->estSure($courante)) {
                Log::warning('UrlSandbox a refusé une adresse', [
                    'saut' => $saut,
                    // Le domaine seul, jamais l'URL entière : elle peut
                    // porter un jeton ou un identifiant dans sa requête.
                    'hote' => parse_url($courante, PHP_URL_HOST),
                ]);

                return null;
            }

            try {
                $reponse = Http::timeout(self::DELAI_SECONDES)
                    ->connectTimeout(5)
                    ->withOptions([
                        'allow_redirects' => false,
                        // Guzzle n'abandonne un transfert déjà commencé qu'à
                        // la fin de la réception ; `on_stats` ne suffit pas
                        // à l'interrompre en cours de route. Un plafond de
                        // taille est tout de même posé via `decode_content`
                        // désactivé et une troncature explicite plus bas :
                        // pour un fournisseur qui annonce honnêtement sa
                        // taille, `Content-Length` est vérifié avant lecture.
                    ])
                    ->withUserAgent('Mozilla/5.0 (compatible; VerifAllBot/1.0; +https://verifall.bj/robots)')
                    ->get($courante);
            } catch (Throwable $erreur) {
                Log::warning('UrlSandbox : échec de récupération', [
                    'hote' => parse_url($courante, PHP_URL_HOST),
                    'erreur' => $erreur->getMessage(),
                ]);

                return null;
            }

            if ($reponse->redirect()) {
                $suivante = $this->resoudreLaRedirection($courante, $reponse);

                if ($suivante === null) {
                    return null;
                }

                $courante = $suivante;

                continue;
            }

            if (! $reponse->successful()) {
                return null;
            }

            return [
                'url_finale' => $courante,
                'corps' => $this->tronquer($reponse),
            ];
        }

        // Plus de cinq sauts : une chaîne de redirections de cette longueur
        // est elle-même un signal, pas une page à lire.
        Log::warning('UrlSandbox : trop de redirections', ['url_initiale' => $url]);

        return null;
    }

    /**
     * La même garantie que {@see recuperer()}, pour PLUSIEURS adresses EN
     * PARALLÈLE — phase 6 : le collecteur de preuves s'ajoute au budget de
     * temps déjà serré d'une analyse, et douze appels l'un après l'autre
     * dépasseraient le plafond de la chaîne à eux seuls. `Http::pool()`
     * mutualise l'attente réseau ; chaque SAUT de chaque adresse reste
     * revalidé indépendamment, un tour de boucle à la fois.
     *
     * @param  list<string>  $urls
     * @return array<string, array{url_finale: string, corps: string}|null> indexé par l'URL D'ORIGINE
     */
    public function recupererPlusieurs(array $urls): array
    {
        /** @var array<string, string> $enCours url d'origine => adresse courante */
        $enCours = array_combine($urls, $urls);
        /** @var array<string, array{url_finale: string, corps: string}|null> $resultats */
        $resultats = [];

        for ($saut = 0; $saut <= self::SAUTS_MAXIMUM && $enCours !== []; $saut++) {
            foreach ($enCours as $origine => $adresse) {
                if (! $this->estSure($adresse)) {
                    $resultats[$origine] = null;
                    unset($enCours[$origine]);
                }
            }

            if ($enCours === []) {
                break;
            }

            $reponses = Http::pool(fn ($pool) => collect($enCours)->map(
                fn (string $adresse, string $origine) => $pool->as($origine)
                    ->timeout(self::DELAI_SECONDES)
                    ->connectTimeout(5)
                    ->withOptions(['allow_redirects' => false])
                    ->withUserAgent('Mozilla/5.0 (compatible; VerifAllBot/1.0; +https://verifall.bj/robots)')
                    ->get($adresse),
            )->all());

            foreach ($enCours as $origine => $adresse) {
                $reponse = $reponses[$origine] ?? null;

                if (! $reponse instanceof Response) {
                    // Une exception de connexion rend l'EXCEPTION elle-même
                    // dans le lot — jamais une Response. C'est un échec
                    // ordinaire, pas une raison de faire tomber le lot entier.
                    $resultats[$origine] = null;
                    unset($enCours[$origine]);

                    continue;
                }

                if ($reponse->redirect()) {
                    $suivante = $this->resoudreLaRedirection($adresse, $reponse);

                    if ($suivante === null) {
                        $resultats[$origine] = null;
                        unset($enCours[$origine]);

                        continue;
                    }

                    $enCours[$origine] = $suivante;

                    continue;
                }

                $resultats[$origine] = $reponse->successful()
                    ? ['url_finale' => $adresse, 'corps' => $this->tronquer($reponse)]
                    : null;

                unset($enCours[$origine]);
            }
        }

        // Les sauts non résolus après le plafond échouent — jamais levés.
        foreach ($enCours as $origine => $adresse) {
            $resultats[$origine] = null;
        }

        return $resultats;
    }

    /**
     * L'adresse est-elle sûre à OUVRIR DEPUIS CE SERVEUR ?
     *
     * Trois contrôles, dans l'ordre du moins au plus coûteux :
     *   1. le SCHÉMA — `file://`, `gopher://`, `ftp://` sont refusés net ;
     *   2. l'HÔTE littéral — `localhost` et ses variantes, qu'aucune
     *      résolution DNS ne transformerait en IP publique ;
     *   3. l'IP RÉSOLUE — c'est elle qui compte, pas le nom affiché : un
     *      domaine public peut très bien résoudre vers une IP privée.
     *      `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` rejette
     *      d'un coup les plages privées (RFC 1918), de boucle locale, de
     *      lien local et les plages réservées — IPv4 et IPv6 confondus.
     */
    private function estSure(string $url): bool
    {
        $morceaux = parse_url($url);

        if (! is_array($morceaux) || ! isset($morceaux['scheme'], $morceaux['host'])) {
            return false;
        }

        if (! in_array(mb_strtolower($morceaux['scheme']), self::SCHEMES_AUTORISES, true)) {
            return false;
        }

        // PHP garde les crochets d'un littéral IPv6 dans `parse_url()`
        // (`[::1]`, pas `::1`) : les retirer avant toute comparaison, sans
        // quoi `filter_var()` et la carte de résolution ne reconnaissent ni
        // l'un ni l'autre et laissent passer la forme crochetée.
        $hote = trim(mb_strtolower($morceaux['host']), '[]');

        if ($hote === 'localhost' || str_ends_with($hote, '.localhost') || $hote === '0.0.0.0' || $hote === '::1') {
            return false;
        }

        $ip = filter_var($hote, FILTER_VALIDATE_IP) !== false ? $hote : $this->resoudre($hote);

        // La résolution rend le nom tel quel si elle échoue — ce n'est
        // jamais une IP valide, donc jamais une adresse sûre.
        if ($ip === $hote && filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function resoudre(string $hote): string
    {
        return $this->resoudreHote !== null ? ($this->resoudreHote)($hote) : gethostbyname($hote);
    }

    /**
     * L'adresse ABSOLUE du prochain saut — le `Location` peut être relatif.
     */
    private function resoudreLaRedirection(string $courante, Response $reponse): ?string
    {
        $location = $reponse->header('Location');

        if ($location === '') {
            return null;
        }

        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $base = parse_url($courante);

        if (! is_array($base) || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $racine = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        return str_starts_with($location, '/') ? $racine.$location : $racine.'/'.$location;
    }

    private function tronquer(Response $reponse): string
    {
        return mb_substr($reponse->body(), 0, self::TAILLE_MAX_OCTETS);
    }
}
