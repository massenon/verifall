<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\VisitorHit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte une visite par famille de navigateur et par jour. Rien d'autre.
 *
 * ## Le compteur est incrémenté EN BASE, pas lu puis réécrit
 *
 * `INSERT … ON DUPLICATE KEY UPDATE hits = hits + 1` en une seule requête
 * atomique. Un `find()` suivi d'un `save()` perdrait des visites dès que deux
 * requêtes arrivent en même temps — et c'est précisément quand le trafic monte
 * que le compteur doit rester juste.
 *
 * ## Ce qui n'est PAS compté, et pourquoi
 *
 * Les robots, les requêtes non-GET, les appels d'API et les ressources internes
 * (`/livewire/*`, `/sw.js`). Sans ces exclusions, le panneau afficherait le
 * trafic de l'application contre elle-même : un écran Livewire qui interroge
 * toutes les trois secondes pèserait plus lourd que tous les visiteurs réunis.
 *
 * ## Aucune donnée personnelle
 *
 * Voir {@see VisitorHit}. On ne conserve que la famille (« Chrome »), jamais
 * l'en-tête complet — qui porte version du système, du navigateur et parfois du
 * matériel, et redevient identifiant s'il est stocké tel quel.
 */
final class CompterLeNavigateur
{
    /**
     * Les familles reconnues, dans l'ORDRE OÙ ELLES DOIVENT ÊTRE TESTÉES.
     *
     * L'ordre fait tout, parce que les navigateurs se déclarent les uns comme
     * les autres pour raisons de compatibilité :
     *
     *   · Edge annonce `Edg/… Chrome/… Safari/…` ;
     *   · Chrome annonce `Chrome/… Safari/…` ;
     *   · Opera annonce `OPR/… Chrome/… Safari/…`.
     *
     * Testé dans l'autre sens, TOUT deviendrait « Safari », et le panneau
     * rendrait un classement faux avec l'aplomb d'une mesure.
     *
     * @var array<string, string>
     */
    private const FAMILLES = [
        'edg/' => 'Edge',
        'opr/' => 'Opera',
        'opera' => 'Opera',
        'samsungbrowser' => 'Samsung Internet',
        'ucbrowser' => 'UC Browser',
        'firefox' => 'Firefox',
        'chrome' => 'Chrome',
        'safari' => 'Safari',
    ];

    /**
     * Ce qui n'est pas un visiteur.
     *
     * @var list<string>
     */
    private const ROBOTS = [
        'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit',
        'whatsapp', 'telegram', 'preview', 'monitor', 'curl', 'wget',
        'python', 'headless', 'lighthouse', 'pingdom',
    ];

    public function handle(Request $requete, Closure $suivant): Response
    {
        $reponse = $suivant($requete);

        // APRÈS la réponse : le comptage ne doit jamais retarder la page, ni
        // la faire échouer. Une erreur ici est sans conséquence pour le visiteur.
        try {
            $this->compter($requete);
        } catch (\Throwable) {
            // Silence assumé : un compteur de tableau de bord ne casse pas une
            // page publique. La perte se voit dans le panneau, nulle part ailleurs.
        }

        return $reponse;
    }

    private function compter(Request $requete): void
    {
        if (! $this->estUnVisiteur($requete)) {
            return;
        }

        $famille = $this->famille((string) $requete->userAgent());

        DB::table('visitor_hits')->upsert(
            [[
                'browser' => $famille,
                'day' => now()->toDateString(),
                'hits' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['browser', 'day'],
            // `hits + 1` côté BASE : une addition faite en PHP écraserait la
            // valeur écrite entre-temps par une requête concurrente.
            ['hits' => DB::raw('hits + 1'), 'updated_at' => now()],
        );
    }

    private function estUnVisiteur(Request $requete): bool
    {
        if (! $requete->isMethod('GET') || $requete->ajax()) {
            return false;
        }

        // Une page, pas une ressource ni un appel interne.
        if ($requete->is('livewire/*', 'api/*', 'sw.js', 'assets/*', 'storage/*', 'up')) {
            return false;
        }

        // Une réponse HTML — pas un téléchargement, pas une image.
        if (! $requete->acceptsHtml()) {
            return false;
        }

        $agent = mb_strtolower((string) $requete->userAgent());

        if ($agent === '') {
            return false;
        }

        foreach (self::ROBOTS as $robot) {
            if (str_contains($agent, $robot)) {
                return false;
            }
        }

        return true;
    }

    private function famille(string $agent): string
    {
        $agent = mb_strtolower($agent);

        foreach (self::FAMILLES as $marqueur => $nom) {
            if (str_contains($agent, $marqueur)) {
                return $nom;
            }
        }

        return 'Autre';
    }
}
