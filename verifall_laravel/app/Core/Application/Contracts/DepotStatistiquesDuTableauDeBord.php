<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Core\Application\Statistiques\PeriodeDAnalyse;
use Carbon\CarbonInterface;

/**
 * Les agrégats du centre de pilotage, sur une période.
 *
 * ## Pourquoi un dépôt
 *
 * Les quatre critères de l'arbitrage du 31 août 2026 sont réunis : lectures
 * CACHÉES, sur des volumes NON BORNÉS (vérifications, visites, inscriptions),
 * qui composent PLUSIEURS AGRÉGATS et nourrissent un tableau de bord.
 *
 * ## Ce que ce contrat promet, et ce qu'il refuse
 *
 * Chaque valeur rendue est MESURÉE en base. Quand une mesure n'existe pas — les
 * clics d'un article de blog, les partages, la durée de lecture — la clé vaut
 * `null`, jamais zéro : zéro affirmerait que personne n'a cliqué, `null` dit que
 * personne n'a compté. L'écran traduit ce `null` en « non mesuré ».
 *
 * Aucune méthode n'écrit : ce dépôt ne collecte rien.
 */
interface DepotStatistiquesDuTableauDeBord
{
    /**
     * @return array{actifs: int|null, nouveaux: int, nouveaux_precedente: int, serie_nouveaux: list<int>}
     */
    public function utilisateurs(PeriodeDAnalyse $periode): array;

    /**
     * Les vérifications SOUMISES dans la période, et ce qu'elles sont devenues.
     *
     * @return array{
     *     soumises: int, soumises_precedente: int,
     *     terminees: int, terminees_precedente: int,
     *     en_cours: int, revue_humaine: int, echouees: int,
     *     duree_moyenne_secondes: int|null,
     *     serie: list<int>,
     *     verdicts: array<string, int>,
     *     types: array<string, int>,
     * }
     */
    public function verifications(PeriodeDAnalyse $periode): array;

    /** Instantané, jamais en cache : ce sont les dossiers sur lesquels on agit. */
    public function verificationsEnAttente(): int;

    /**
     * Pages vues du site public (compteur par jour, sans donnée personnelle).
     *
     * @return array{pages_vues: int, pages_vues_precedente: int, serie: list<int>|null}
     */
    public function visites(PeriodeDAnalyse $periode): array;

    /**
     * Blog et actualités, côte à côte.
     *
     * @return array{blog: array<string, mixed>, actualites: array<string, mixed>}
     */
    public function editorial(PeriodeDAnalyse $periode): array;

    /**
     * Les contenus les plus consultés sur la période ; à défaut de toute
     * visite, les plus récemment publiés (`classement` le dit).
     *
     * @return array{classement: string, lignes: list<array<string, mixed>>}
     */
    public function contenusPerformants(PeriodeDAnalyse $periode, int $limite = 8): array;

    /**
     * @return array<string, int>
     */
    public function engagement(PeriodeDAnalyse $periode): array;

    /**
     * @return array<string, int|list<int>|null>
     */
    public function lms(PeriodeDAnalyse $periode): array;

    /**
     * Les derniers événements de chaque source demandée, bruts.
     *
     * @param  list<string>  $sources
     * @return list<array{source: string, quand: CarbonInterface, titre: string, acteur: string|null, detail: string|null, cle: string|null}>
     */
    public function evenementsRecents(array $sources, int $parSource = 5): array;

    /** Invalide tous les agrégats en cache — le bouton « Actualiser ». */
    public function oublier(): void;
}
